<?php

use MWGuerra\InteractiveUpgrader\Services\ComposerService;
use Symfony\Component\Process\Process;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

// Mock the Process class
beforeEach(function () {
    $this->mockProcess = Mockery::mock('overload:Symfony\Component\Process\Process');
    $this->mockProcess->shouldReceive('run')->andReturn(0);
    
    $this->mockClient = Mockery::mock('overload:GuzzleHttp\Client');
});

test('getOutdated returns formatted array of outdated packages', function () {
    // Mock process output
    $mockOutput = json_encode([
        'installed' => [
            [
                'name' => 'example/package',
                'version' => '1.0.0',
                'latest' => '2.0.0',
                'latest-status' => 'update-available'
            ]
        ]
    ]);
    
    $this->mockProcess->shouldReceive('getOutput')->andReturn($mockOutput);
    
    // Mock client response for dev version
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getBody')->andReturn(json_encode([
        'packages' => [
            'example/package' => [
                [
                    'version' => 'dev-main'
                ]
            ]
        ]
    ]));
    
    $this->mockClient->shouldReceive('get')->with('/p2/example/package.json')->andReturn($mockResponse);
    
    $composerService = new ComposerService();
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
    $this->mockProcess->shouldReceive('getOutput')->andReturn('{}');
    
    $composerService = new ComposerService();
    $result = $composerService->getOutdated();
    
    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

afterEach(function () {
    Mockery::close();
});