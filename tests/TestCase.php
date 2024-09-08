<?php

namespace MoonShine\OAG\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Providers\MoonShineServiceProvider;
use MoonShine\OAG\Providers\OAGServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('optimize:clear');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.debug', 'true');
        $app['config']->set('moonshine.cache', 'array');
    }

    protected function getPackageProviders($app): array
    {
        return [
            MoonShineServiceProvider::class,
            OAGServiceProvider::class,
        ];
    }
}
