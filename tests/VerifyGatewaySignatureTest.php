<?php

namespace Myori\AiClient\Tests;

use Illuminate\Support\Facades\Route;
use Myori\AiClient\Exceptions\GatewayException;
use Myori\AiClient\Signature;

class VerifyGatewaySignatureTest extends TestCase
{
    private const BODY = '{"tool":"product_count","arguments":{}}';

    protected function defineRoutes($router): void
    {
        Route::post('/myori-ai/ping', fn () => response()->json(['ok' => true]))->middleware('myori-ai.signed');
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function send(array $headers, string $body = self::BODY)
    {
        return $this->call('POST', '/myori-ai/ping', [], [], [], $this->transformHeadersToServerVars($headers + [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), $body);
    }

    private function signed(?int $timestamp = null, string $secret = 'test-callback-secret', string $body = self::BODY): array
    {
        return Signature::headers($secret, $timestamp ?? now()->getTimestamp(), $body);
    }

    public function test_a_correctly_signed_request_gets_through(): void
    {
        $this->send($this->signed())->assertOk()->assertExactJson(['ok' => true]);
    }

    public function test_a_request_without_a_signature_is_refused(): void
    {
        $this->send([])->assertUnauthorized();
    }

    public function test_a_request_signed_with_the_wrong_secret_is_refused(): void
    {
        $this->send($this->signed(secret: 'someone-elses-secret'))->assertUnauthorized();
    }

    public function test_a_tampered_body_is_refused(): void
    {
        $this->send($this->signed(), '{"tool":"monthly_sales","arguments":{}}')->assertUnauthorized();
    }

    public function test_an_old_signature_is_refused(): void
    {
        $this->send($this->signed(now()->subMinutes(6)->getTimestamp()))->assertUnauthorized();
    }

    public function test_a_signature_from_the_future_is_refused(): void
    {
        $this->send($this->signed(now()->addMinutes(6)->getTimestamp()))->assertUnauthorized();
    }

    public function test_a_replayed_request_is_refused(): void
    {
        $headers = $this->signed();

        $this->send($headers)->assertOk();
        $this->send($headers)->assertUnauthorized();
    }

    public function test_a_missing_callback_secret_is_a_loud_error(): void
    {
        config(['myori-ai.callback_secret' => null]);

        $this->withoutExceptionHandling();
        $this->expectException(GatewayException::class);

        $this->send($this->signed());
    }
}
