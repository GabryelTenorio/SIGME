<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');

        if (! $app->environment('testing')
            || $app['config']->get("database.connections.{$connection}.database") !== 'sigme_testing'
            || $app['config']->get("database.connections.{$connection}.url")) {
            throw new RuntimeException('Testes SIGME exigem APP_ENV=testing, DB_DATABASE=sigme_testing e DB_URL vazio.');
        }

        return $app;
    }
}
