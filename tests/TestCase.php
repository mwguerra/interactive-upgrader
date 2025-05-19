<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MWGuerra\InteractiveUpgrader\InteractiveUpgraderServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            InteractiveUpgraderServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        // (include __DIR__.'/../database/migrations/your_migration_name.php.stub')->up();
    }
}