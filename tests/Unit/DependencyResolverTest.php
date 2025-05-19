<?php

use MWGuerra\InteractiveUpgrader\Services\DependencyResolver;
use MWGuerra\InteractiveUpgrader\Services\ComposerService;
use MWGuerra\InteractiveUpgrader\Services\FilesystemService;
use MWGuerra\InteractiveUpgrader\Services\NpmService;
use Composer\Semver\Semver;

beforeEach(function () {
    // Mock the ComposerService
    $this->mockComposerService = Mockery::mock(ComposerService::class);

    // Mock the NpmService
    $this->mockNpmService = Mockery::mock(NpmService::class);

    // Mock the FilesystemService
    $this->mockFilesystem = Mockery::mock(FilesystemService::class);
    $this->mockFilesystem->shouldReceive('fileExists')->byDefault()->andReturn(false);
    $this->mockFilesystem->shouldReceive('getFileContents')->byDefault()->andReturn('');

    // Create the resolver with mocked services
    $this->resolver = new DependencyResolver(
        $this->mockComposerService,
        $this->mockNpmService,
        $this->mockFilesystem
    );
});

test('suggest returns empty array when no lock files exist', function () {
    // FilesystemService is already set to return false for fileExists by default
    $result = $this->resolver->suggest('example/package', '2.0.0');

    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

test('suggest returns packages that would be affected by composer upgrade', function () {
    // Mock fileExists to return true for composer.lock
    $this->mockFilesystem->shouldReceive('fileExists')
        ->with(getcwd() . '/composer.lock')
        ->andReturn(true);

    // Mock fileExists to return false for package-lock.json
    $this->mockFilesystem->shouldReceive('fileExists')
        ->with(getcwd() . '/package-lock.json')
        ->andReturn(false);

    // Mock getFileContents to return a sample composer.lock content
    $composerLockContent = json_encode([
        'packages' => [
            [
                'name' => 'dependent/package',
                'version' => '1.0.0',
                'require' => [
                    'example/package' => '^1.0'
                ]
            ]
        ],
        'packages-dev' => []
    ]);

    $this->mockFilesystem->shouldReceive('getFileContents')
        ->with(getcwd() . '/composer.lock')
        ->andReturn($composerLockContent);

    // Mock ComposerService->getOutdated to return sample data
    $this->mockComposerService->shouldReceive('getOutdated')
        ->andReturn([
            [
                'name' => 'dependent/package',
                'current' => '1.0.0',
                'wanted' => '1.1.0',
                'latest' => '2.0.0'
            ]
        ]);

    $result = $this->resolver->suggest('example/package', '2.0.0');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0]['name'])->toBe('dependent/package');
    expect($result[0]['required'])->toBe('^1.0');
    expect($result[0]['target'])->toBe('1.1.0');
});

test('suggest returns packages that would be affected by npm upgrade', function () {
    // Mock fileExists to return false for composer.lock
    $this->mockFilesystem->shouldReceive('fileExists')
        ->with(getcwd() . '/composer.lock')
        ->andReturn(false);

    // Mock fileExists to return true for package-lock.json
    $this->mockFilesystem->shouldReceive('fileExists')
        ->with(getcwd() . '/package-lock.json')
        ->andReturn(true);

    // Mock getFileContents to return a sample package-lock.json content
    $npmLockContent = json_encode([
        'dependencies' => [
            'dependent-package' => [
                'version' => '1.0.0',
                'requires' => [
                    'example-package' => '^1.0.0'
                ]
            ]
        ]
    ]);

    $this->mockFilesystem->shouldReceive('getFileContents')
        ->with(getcwd() . '/package-lock.json')
        ->andReturn($npmLockContent);

    // Mock NpmService->getOutdated to return sample data
    $this->mockNpmService->shouldReceive('getOutdated')
        ->andReturn([
            [
                'name' => 'dependent-package',
                'current' => '1.0.0',
                'wanted' => '1.1.0',
                'latest' => '2.0.0'
            ]
        ]);

    $result = $this->resolver->suggest('example-package', '2.0.0');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0]['name'])->toBe('dependent-package');
    expect($result[0]['required'])->toBe('^1.0.0');
    expect($result[0]['target'])->toBe('1.1.0');
});

afterEach(function () {
    Mockery::close();
});
