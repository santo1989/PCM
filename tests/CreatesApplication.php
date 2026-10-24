<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Feature tests use RefreshDatabase, which wipes the database. Refuse to run
        // against anything but a dedicated *_test database so real data can't be lost.
        $database = (string) $app['config']->get('database.connections.' . $app['config']->get('database.default') . '.database');
        if (!str_ends_with($database, '_test') && $database !== ':memory:') {
            throw new \RuntimeException("Refusing to run tests against database '{$database}': use a *_test database (see phpunit.xml).");
        }

        return $app;
    }
}
