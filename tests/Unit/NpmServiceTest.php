<?php

use MWGuerra\InteractiveUpgrader\Services\NpmService;
use Symfony\Component\Process\Process;

// Mock the Process class
beforeEach(function () {
    $this->mockProcess = Mockery::mock('overload:Symfony\Component\Process\Process');
    $this->mockProcess->shouldReceive('run')->andReturn(0);
});

test('getOutdated returns formatted array of outdated packages', function () {
    // Mock process output for npm outdated
    $mockOutput = json_encode([
        'tailwindcss' => [
            'current' => '3.0.0',
            'wanted' => '3.1.0',
            'latest' => '4.0.0',
        ]
    ]);
    
    $this->mockProcess->shouldReceive('getOutput')
        ->once()
        ->andReturn($mockOutput);
    
    // Mock process output for fetchDevTag
    $mockTagsOutput = json_encode([
        'next' => 'next-4.1.0'
    ]);
    
    $this->mockProcess->shouldReceive('getOutput')
        ->once()
        ->andReturn($mockTagsOutput);
    
    $npmService = new NpmService();
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
    $this->mockProcess->shouldReceive('getOutput')->andReturn('');
    
    $npmService = new NpmService();
    $result = $npmService->getOutdated();
    
    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

test('allVersions returns array of available versions', function () {
    $mockVersionsOutput = json_encode(['1.0.0', '1.1.0', '2.0.0']);
    
    $this->mockProcess->shouldReceive('getOutput')
        ->once()
        ->andReturn($mockVersionsOutput);
    
    $npmService = new NpmService();
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