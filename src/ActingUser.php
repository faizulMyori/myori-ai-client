<?php

namespace Myori\AiClient;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Finds the user the gateway is acting for, in this system's own database.
 * By default it uses the "web" guard's user provider; call findUsing() from
 * a service provider if users live elsewhere.
 */
final class ActingUser
{
    /** @var (Closure(string): ?Authenticatable)|null */
    private static ?Closure $finder = null;

    /**
     * @param  (callable(string): ?Authenticatable)|null  $finder
     */
    public static function findUsing(?callable $finder): void
    {
        self::$finder = $finder ? Closure::fromCallable($finder) : null;
    }

    public static function find(string $id): ?Authenticatable
    {
        if (self::$finder) {
            return (self::$finder)($id);
        }

        $provider = config('auth.guards.'.config('myori-ai.guard', 'web').'.provider');

        return app('auth')->createUserProvider($provider)?->retrieveById($id);
    }
}
