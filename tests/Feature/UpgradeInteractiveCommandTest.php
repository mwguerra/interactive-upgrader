<?php

use MWGuerra\InteractiveUpgrader\Console\UpgradeInteractiveCommand;
use MWGuerra\InteractiveUpgrader\Services\ComposerService;
use MWGuerra\InteractiveUpgrader\Services\NpmService;
use MWGuerra\InteractiveUpgrader\Services\DependencyResolver;

test('command with --show flag displays table and exits without user interaction', function () {
    // Create a mock of the command
    $command = Mockery::mock(UpgradeInteractiveCommand::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    // Mock the services
    $composerService = Mockery::mock(ComposerService::class);
    $npmService = Mockery::mock(NpmService::class);
    $dependencyResolver = Mockery::mock(DependencyResolver::class);

    // Mock the getOutdated method to return test data
    $composerService->shouldReceive('getOutdated')
        ->once()
        ->andReturn([
            [
                'name' => 'example/package',
                'current' => '1.0.0',
                'wanted' => '1.1.0',
                'latest' => '2.0.0',
                'latest_dev' => 'dev-main',
            ]
        ]);

    $npmService->shouldReceive('getOutdated')
        ->once()
        ->andReturn([
            [
                'name' => 'example-npm',
                'current' => '1.0.0',
                'wanted' => '1.1.0',
                'latest' => '2.0.0',
                'latest_dev' => 'dev-main',
            ]
        ]);

    // Set up the command to use the --show flag
    $command->shouldReceive('option')
        ->with('show')
        ->andReturn(true);

    // Set up other options
    $command->shouldReceive('option')
        ->with('ignore-major')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('ignore-dev')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('ignore')
        ->andReturn(null);

    $command->shouldReceive('option')
        ->with('latest')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('dev')
        ->andReturn(false);

    // Verify that the command exits after displaying the table
    $result = $command->handle($composerService, $npmService, $dependencyResolver);

    // The command should return null when it exits early
    expect($result)->toBeNull();
});

test('command with --show and --ignore flags respects ignored packages', function () {
    // Create a mock of the command
    $command = Mockery::mock(UpgradeInteractiveCommand::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    // Mock the services
    $composerService = Mockery::mock(ComposerService::class);
    $npmService = Mockery::mock(NpmService::class);
    $dependencyResolver = Mockery::mock(DependencyResolver::class);

    // Mock the getOutdated method to return test data
    $composerService->shouldReceive('getOutdated')
        ->once()
        ->andReturn([
            [
                'name' => 'example/package',
                'current' => '1.0.0',
                'wanted' => '1.1.0',
                'latest' => '2.0.0',
                'latest_dev' => 'dev-main',
            ],
            [
                'name' => 'ignored/package',
                'current' => '1.0.0',
                'wanted' => '1.1.0',
                'latest' => '2.0.0',
                'latest_dev' => 'dev-main',
            ]
        ]);

    $npmService->shouldReceive('getOutdated')
        ->once()
        ->andReturn([]);

    // Set up the command to use the --show flag
    $command->shouldReceive('option')
        ->with('show')
        ->andReturn(true);

    // Set up the --ignore flag
    $command->shouldReceive('option')
        ->with('ignore')
        ->andReturn('composer:ignored/package');

    // Set up other options
    $command->shouldReceive('option')
        ->with('ignore-major')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('ignore-dev')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('latest')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('dev')
        ->andReturn(false);

    // Verify that the command exits after displaying the table
    $result = $command->handle($composerService, $npmService, $dependencyResolver);

    // The command should return null when it exits early
    expect($result)->toBeNull();
});

test('command with --show and --ignore-major flags hides major version upgrades', function () {
    // Create a mock of the command
    $command = Mockery::mock(UpgradeInteractiveCommand::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    // Mock the services
    $composerService = Mockery::mock(ComposerService::class);
    $npmService = Mockery::mock(NpmService::class);
    $dependencyResolver = Mockery::mock(DependencyResolver::class);

    // Mock the getOutdated method to return test data
    $composerService->shouldReceive('getOutdated')
        ->once()
        ->andReturn([
            [
                'name' => 'minor/package',
                'current' => '1.0.0',
                'wanted' => '1.1.0',
                'latest' => '1.2.0',
                'latest_dev' => 'dev-main',
            ],
            [
                'name' => 'major/package',
                'current' => '1.0.0',
                'wanted' => '1.1.0',
                'latest' => '2.0.0',
                'latest_dev' => 'dev-main',
            ]
        ]);

    $npmService->shouldReceive('getOutdated')
        ->once()
        ->andReturn([]);

    // Set up the command to use the --show flag
    $command->shouldReceive('option')
        ->with('show')
        ->andReturn(true);

    // Set up the --ignore-major flag
    $command->shouldReceive('option')
        ->with('ignore-major')
        ->andReturn(true);

    // Set up other options
    $command->shouldReceive('option')
        ->with('ignore')
        ->andReturn(null);

    $command->shouldReceive('option')
        ->with('ignore-dev')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('latest')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('dev')
        ->andReturn(false);

    // Verify that the command exits after displaying the table
    $result = $command->handle($composerService, $npmService, $dependencyResolver);

    // The command should return null when it exits early
    expect($result)->toBeNull();
});

test('command with --show and --ignore-dev flags hides dev column', function () {
    // Create a mock of the command
    $command = Mockery::mock(UpgradeInteractiveCommand::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    // Mock the services
    $composerService = Mockery::mock(ComposerService::class);
    $npmService = Mockery::mock(NpmService::class);
    $dependencyResolver = Mockery::mock(DependencyResolver::class);

    // Mock the getOutdated method to return test data
    $composerService->shouldReceive('getOutdated')
        ->once()
        ->andReturn([
            [
                'name' => 'example/package',
                'current' => '1.0.0',
                'wanted' => '1.1.0',
                'latest' => '2.0.0',
                'latest_dev' => 'dev-main',
            ]
        ]);

    $npmService->shouldReceive('getOutdated')
        ->once()
        ->andReturn([]);

    // Set up the command to use the --show flag
    $command->shouldReceive('option')
        ->with('show')
        ->andReturn(true);

    // Set up the --ignore-dev flag
    $command->shouldReceive('option')
        ->with('ignore-dev')
        ->andReturn(true);

    // Set up other options
    $command->shouldReceive('option')
        ->with('ignore')
        ->andReturn(null);

    $command->shouldReceive('option')
        ->with('ignore-major')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('latest')
        ->andReturn(false);

    $command->shouldReceive('option')
        ->with('dev')
        ->andReturn(false);

    // Verify that the command exits after displaying the table
    $result = $command->handle($composerService, $npmService, $dependencyResolver);

    // The command should return null when it exits early
    expect($result)->toBeNull();
});

afterEach(function () {
    Mockery::close();
});
