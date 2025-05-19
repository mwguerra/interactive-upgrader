<?php

namespace MWGuerra\InteractiveUpgrader;

use Illuminate\Support\ServiceProvider;
use MWGuerra\InteractiveUpgrader\Console\UpgradeInteractiveCommand;

class InteractiveUpgraderServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([UpgradeInteractiveCommand::class]);
    }
}
