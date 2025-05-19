<?php

use MWGuerra\InteractiveUpgrader\Services\NpmService;
use MWGuerra\InteractiveUpgrader\Services\FilesystemService;
use Symfony\Component\Process\Process;

// Mock the FilesystemService
beforeEach(function () {
    $this->mockFilesystem = Mockery::mock(FilesystemService::class);
});

test('getOutdated returns formatted array of outdated packages', function () {
    // Mock output for npm outdated
    $mockOutput = [
        'tailwindcss' => [
            'current' => '3.0.0',
            'wanted' => '3.1.0',
            'latest' => '4.0.0',
        ]
    ];

    // Mock output for fetchDevTag
    $mockTagsOutput = [
        'next' => 'next-4.1.0'
    ];

    // Set up expectations for executeJsonCommand
    $this->mockFilesystem->shouldReceive('executeJsonCommand')
        ->with(['npm', 'outdated', '--json'])
        ->andReturn($mockOutput);

    $this->mockFilesystem->shouldReceive('executeJsonCommand')
        ->with(['npm', 'view', 'tailwindcss', 'dist-tags', '--json'])
        ->andReturn($mockTagsOutput);

    $npmService = new NpmService($this->mockFilesystem);
    $result = $npmService->getOutdated();

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0]['name'])->toBe('tailwindcss');
    expect($result[0]['current'])->toBe('3.0.0');
    expect($result[0]['wanted'])->toBe('3.1.0');
    expect($result[0]['latest'])->toBe('4.0.0');
    expect($result[0]['latest_dev'])->toBe('next-4.1.0');
});

test('getOutdated handles empty response', function () {
    $this->mockFilesystem->shouldReceive('executeJsonCommand')
        ->with(['npm', 'outdated', '--json'])
        ->andReturn([]);

    $npmService = new NpmService($this->mockFilesystem);
    $result = $npmService->getOutdated();

    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

test('allVersions returns array of available versions', function () {
    $mockVersionsOutput = ['1.0.0', '1.1.0', '2.0.0'];

    $this->mockFilesystem->shouldReceive('executeJsonCommand')
        ->with(['npm', 'view', 'tailwindcss', 'versions', '--json'])
        ->once()
        ->andReturn($mockVersionsOutput);

    $npmService = new NpmService($this->mockFilesystem);
    $result = $npmService->allVersions('tailwindcss');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(3);
    expect($result)->toContain('1.0.0');
    expect($result)->toContain('1.1.0');
    expect($result)->toContain('2.0.0');
});

afterEach(function () {
    Mockery::close();
});
