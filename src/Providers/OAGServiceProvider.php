<?php

declare(strict_types=1);

namespace MoonShine\OAG\Providers;

use Illuminate\Support\ServiceProvider;
use MoonShine\OAG\Console\Commands\GenerateCommand;

final class OAGServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/oag.php' => config_path('oag.php'),
        ]);

        $this->mergeConfigFrom(__DIR__ . '/../../config/oag.php', 'oag');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/oag.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'oag');

        $this->commands([
            GenerateCommand::class
        ]);
    }
}
