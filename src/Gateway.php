<?php

namespace Myori\AiClient;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Myori\AiClient\Exceptions\GatewayException;

/**
 * Calls the MyORI AI gateway on behalf of one of this system's signed-in users.
 * The token stays on the server; the browser never sees it.
 */
class Gateway
{
    /** @var (Closure(Authenticatable): array{id: string, name?: string|null, roles?: list<string>})|null */
    private static ?Closure $userResolver = null;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly Config $config,
    ) {}

    /**
     * Decide what the gateway is told about a user, typically their roles.
     * Call this from a service provider's boot method.
     *
     * @param  (callable(Authenticatable): array{id: string, name?: string|null, roles?: list<string>})|null  $resolver
     */
    public static function resolveUserUsing(?callable $resolver): void
    {
        self::$userResolver = $resolver ? Closure::fromCallable($resolver) : null;
    }

    /**
     * Ask the gateway which system and which user it sees. Useful for checking setup.
     *
     * @param  Authenticatable|array{id: string|int, name?: string|null, roles?: list<string>}  $user
     * @return array{system: array{slug: string, name: string, abilities: list<string>}, user: array{id: string, name: string|null, roles: list<string>}}
     */
    public function whoami(Authenticatable|array $user): array
    {
        return $this->post('whoami', $user);
    }

    /**
     * Ask the system's assistant a question for this user. The gateway answers
     * from the knowledge base and, if this system's token allows live data,
     * from this system's tools (called back as this same user).
     *
     * @param  Authenticatable|array{id: string|int, name?: string|null, roles?: list<string>}  $user
     * @param  list<array{role: 'user'|'assistant', content: string}>  $history  earlier turns, oldest first
     * @return array{answer: string, sources: list<array{title: string|null, url: string}>, tools_used: list<string>}
     */
    public function chat(Authenticatable|array $user, string $message, array $history = []): array
    {
        return $this->post('chat', $user, ['message' => $message, 'history' => $history]);
    }

    /**
     * @return array{id: string, name: string|null, roles: list<string>}
     */
    public function userContext(Authenticatable|array $user): array
    {
        if ($user instanceof Authenticatable) {
            $user = self::$userResolver
                ? (self::$userResolver)($user)
                : ['id' => $user->getAuthIdentifier(), 'name' => $user->name ?? null];
        }

        return [
            'id' => (string) $user['id'],
            'name' => $user['name'] ?? null,
            'roles' => array_values(array_map('strval', $user['roles'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function post(string $path, Authenticatable|array $user, array $payload = []): array
    {
        try {
            $response = $this->http
                ->baseUrl(rtrim($this->config('gateway_url'), '/').'/api/v1')
                ->withToken($this->config('token'))
                ->acceptJson()
                ->timeout((int) $this->config->get('myori-ai.timeout', 30))
                ->post($path, ['user' => $this->userContext($user)] + $payload);
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach the MyORI AI gateway: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw GatewayException::fromResponse($response);
        }

        return $response->json();
    }

    private function config(string $key): string
    {
        $value = $this->config->get("myori-ai.{$key}");

        if (! is_string($value) || $value === '') {
            throw GatewayException::notConfigured($key);
        }

        return $value;
    }
}
