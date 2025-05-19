<?php

use MWGuerra\InteractiveUpgrader\Console\UpgradeInteractiveCommand;
use MWGuerra\InteractiveUpgrader\Services\ComposerService;
use MWGuerra\InteractiveUpgrader\Services\NpmService;
use MWGuerra\InteractiveUpgrader\Services\DependencyResolver;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

beforeEach(function () {
    // Mock Laravel Prompts functions
    $this->mockPrompts = Mockery::mock('alias:Laravel\Prompts');
    
    // Mock the ComposerService
    $this->mockComposerService = Mockery::mock(ComposerService::class);
    $this->mockComposerService->shouldReceive('getOutdated')->andReturn([
        [
            'name' => 'example/package',
            'current' => '1.0.0',
            'wanted' => '1.1.0',
            'latest' => '2.0.0',
            'latest_dev' => 'dev-main'
        ]
    ]);
    
    // Mock the NpmService
    $this->mockNpmService = Mockery::mock(NpmService::class);
    $this->mockNpmService->shouldReceive('getOutdated')->andReturn([
        [
            'name' => 'example-package',
            'current' => '1.0.0',
            'wanted' => '1.1.0',
            'latest' => '2.0.0',
            'latest_dev' => 'next-2.1.0'
        ]
    ]);
    
    // Mock the DependencyResolver
    $this->mockDependencyResolver = Mockery::mock(DependencyResolver::class);
    $this->mockDependencyResolver->shouldReceive('suggest')->andReturn([]);
    
    // Mock Process for backup creation
    $this->mockProcess = Mockery::mock('overload:Symfony\Component\Process\Process');
    $this->mockProcess->shouldReceive('run')->andReturn(0);
});

test('command displays outdated packages', function () {
    // Mock Laravel Prompts functions
    $this->mockPrompts->shouldReceive('intro')->once();
    $this->mockPrompts->shouldReceive('table')->once();
    $this->mockPrompts->shouldReceive('select')->andReturn('Skip');
    $this->mockPrompts->shouldReceive('outro')->once();
    
    // Create a mock application and add our command
    $app = new \Illuminate\Foundation\Application();
    $app->singleton('console', function ($app) {
        return new \Illuminate\Console\Application($app, $app['events'], $app->version());
    });
    
    // Create and register the command
    $command = new UpgradeInteractiveCommand();
    $command->setLaravel($app);
    
    // Run the command
    $exitCode = $command->handle(
        $this->mockComposerService,
        $this->mockNpmService,
        $this->mockDependencyResolver
    );
    
    expect($exitCode)->toBe(Command::SUCCESS);
});

test('command handles --latest option', function () {
    // Mock Laravel Prompts functions
    $this->mockPrompts->shouldReceive('intro')->once();
    $this->mockPrompts->shouldReceive('table')->once();
    $this->mockPrompts->shouldReceive('select')->andReturn('Skip');
    $this->mockPrompts->shouldReceive('outro')->once();
    
    // Create a mock application and add our command
    $app = new \Illuminate\Foundation\Application();
    $app->singleton('console', function ($app) {
        return new \Illuminate\Console\Application($app, $app['events'], $app->version());
    });
    
    // Create and register the command with --latest option
    $command = new UpgradeInteractiveCommand();
    $command->setLaravel($app);
    
    // Set the --latest option
    $reflection = new ReflectionClass($command);
    $property = $reflection->getProperty('options');
    $property->setAccessible(true);
    $property->setValue($command, ['latest' => true]);
    
    // Run the command
    $exitCode = $command->handle(
        $this->mockComposerService,
        $this->mockNpmService,
        $this->mockDependencyResolver
    );
    
    expect($exitCode)->toBe(Command::SUCCESS);
});

afterEach(function () {
    Mockery::close();
});