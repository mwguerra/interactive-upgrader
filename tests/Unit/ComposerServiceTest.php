<?php

use MWGuerra\InteractiveUpgrader\Services\ComposerService;
use MWGuerra\InteractiveUpgrader\Services\FilesystemService;
use Symfony\Component\Process\Process;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\StreamInterface;

// Mock the FilesystemService
beforeEach(function () {
    $this->mockFilesystem = Mockery::mock(FilesystemService::class);
});

test('getOutdated returns formatted array of outdated packages', function () {
    // Mock executeJsonCommand output for composer outdated
    $mockOutput = [
        'installed' => [
            [
                'name' => 'example/package',
                'version' => '1.0.0',
                'latest' => '2.0.0',
                'latest-status' => 'update-available'
            ]
        ]
    ];

    $this->mockFilesystem->shouldReceive('executeJsonCommand')
        ->with(['composer', 'outdated', '--direct', '--format=json'])
        ->andReturn($mockOutput);

    // Mock getJson output for dev version
    $mockPackageData = [
        'packages' => [
            'example/package' => [
                [
                    'version' => 'dev-main'
                ]
            ]
        ]
    ];

    $this->mockFilesystem->shouldReceive('getJson')
        ->with('/p2/example/package.json', ['base_uri' => 'https://repo.packagist.org'])
        ->andReturn($mockPackageData);

    $composerService = new ComposerService($this->mockFilesystem);
    $result = $composerService->getOutdated();

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0]['name'])->toBe('example/package');
    expect($result[0]['current'])->toBe('1.0.0');
    expect($result[0]['wanted'])->toBe('2.0.0');
    expect($result[0]['latest'])->toBe('2.0.0');
    expect($result[0]['latest_dev'])->toBe('dev-main');
});

test('getOutdated handles empty response', function () {
    $this->mockFilesystem->shouldReceive('executeJsonCommand')
        ->with(['composer', 'outdated', '--direct', '--format=json'])
        ->andReturn([]);

    $composerService = new ComposerService($this->mockFilesystem);
    $result = $composerService->getOutdated();

    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

afterEach(function () {
    Mockery::close();
});
