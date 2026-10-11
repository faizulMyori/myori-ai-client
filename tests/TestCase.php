<?php

namespace Myori\AiClient\Tests;

use Myori\AiClient\AiClientServiceProvider;
use Myori\AiClient\Gateway;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AiClientServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('myori-ai.gateway_url', 'https://ai.myori.test');
        $app['config']->set('myori-ai.token', '1|test-token');
        $app['config']->set('myori-ai.callback_secret', 'test-callback-secret');
        $app['config']->set('cache.default', 'array');
    }

    protected function tearDown(): void
    {
        Gateway::resolveUserUsing(null);

        parent::tearDown();
    }
}
