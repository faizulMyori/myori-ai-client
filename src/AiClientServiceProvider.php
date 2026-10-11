<?php

namespace Myori\AiClient;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Myori\AiClient\Console\AskCommand;
use Myori\AiClient\Console\CheckCommand;
use Myori\AiClient\Http\Middleware\VerifyGatewaySignature;
use Myori\AiClient\Tools\ToolRegistry;

class AiClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/myori-ai.php', 'myori-ai');

        $this->app->singleton(Gateway::class, fn ($app) => new Gateway(
            $app->make(HttpFactory::class),
            $app['config'],
        ));

        $this->app->singleton(ToolRegistry::class, fn ($app) => new ToolRegistry(
            $app,
            $app['config']->get('myori-ai.tools', []),
        ));
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('myori-ai.signed', VerifyGatewaySignature::class);

        if ($this->app['config']->get('myori-ai.register_routes', true) && ! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/myori-ai.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/myori-ai.php' => config_path('myori-ai.php'),
            ], 'myori-ai-config');

            $this->commands([CheckCommand::class, AskCommand::class]);
        }
    }
}
