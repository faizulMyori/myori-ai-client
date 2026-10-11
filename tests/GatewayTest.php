<?php

namespace Myori\AiClient\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Myori\AiClient\Exceptions\GatewayException;
use Myori\AiClient\Gateway;

class GatewayTest extends TestCase
{
    private const WHOAMI = [
        'system' => ['slug' => 'inventory', 'name' => 'Inventory', 'abilities' => ['chat']],
        'user' => ['id' => '42', 'name' => 'Aisyah Rahman', 'roles' => []],
    ];

    public function test_whoami_sends_the_token_and_the_signed_in_user(): void
    {
        Http::fake(['ai.myori.test/*' => Http::response(self::WHOAMI)]);

        $result = app(Gateway::class)->whoami(new GenericUser(['id' => 42, 'name' => 'Aisyah Rahman']));

        $this->assertSame(self::WHOAMI, $result);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://ai.myori.test/api/v1/whoami'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer 1|test-token')
            && $request['user'] === ['id' => '42', 'name' => 'Aisyah Rahman', 'roles' => []]);
    }

    public function test_a_system_can_decide_what_the_gateway_is_told_about_a_user(): void
    {
        Http::fake(['ai.myori.test/*' => Http::response(self::WHOAMI)]);

        Gateway::resolveUserUsing(fn ($user) => [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->name,
            'roles' => ['store-manager', 'buyer'],
        ]);

        app(Gateway::class)->whoami(new GenericUser(['id' => 42, 'name' => 'Aisyah Rahman']));

        Http::assertSent(fn (Request $request) => $request['user']['roles'] === ['store-manager', 'buyer']);
    }

    public function test_a_trailing_slash_in_the_gateway_url_is_fine(): void
    {
        config(['myori-ai.gateway_url' => 'https://ai.myori.test/']);
        Http::fake(['ai.myori.test/*' => Http::response(self::WHOAMI)]);

        app(Gateway::class)->whoami(['id' => 42]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://ai.myori.test/api/v1/whoami');
    }

    public function test_a_gateway_error_becomes_a_gateway_exception(): void
    {
        Http::fake(['ai.myori.test/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('The MyORI AI gateway answered 401: Unauthenticated.');

        app(Gateway::class)->whoami(['id' => 42]);
    }

    public function test_an_unreachable_gateway_becomes_a_gateway_exception(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('Could not reach the MyORI AI gateway');

        app(Gateway::class)->whoami(['id' => 42]);
    }

    public function test_missing_config_is_reported_clearly(): void
    {
        config(['myori-ai.token' => null]);
        Http::fake();

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('set myori-ai.token');

        app(Gateway::class)->whoami(['id' => 42]);
    }

    public function test_the_check_command_prints_what_the_gateway_sees(): void
    {
        Http::fake(['ai.myori.test/*' => Http::response(self::WHOAMI)]);

        $this->artisan('myori-ai:check', ['user_id' => '42', '--name' => 'Aisyah Rahman'])
            ->expectsOutput('Connected to the MyORI AI gateway.')
            ->assertSuccessful();
    }

    public function test_the_check_command_fails_when_the_gateway_refuses(): void
    {
        Http::fake(['ai.myori.test/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->artisan('myori-ai:check', ['user_id' => '42'])
            ->expectsOutput('The MyORI AI gateway answered 401: Unauthenticated.')
            ->assertFailed();
    }
}
