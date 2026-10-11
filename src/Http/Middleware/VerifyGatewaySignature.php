<?php

namespace Myori\AiClient\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Myori\AiClient\Exceptions\GatewayException;
use Myori\AiClient\Signature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only let a request through if the MyORI AI gateway signed it recently and it has
 * not been seen before. Protects the routes the gateway calls back into.
 */
class VerifyGatewaySignature
{
    public function __construct(
        private readonly Config $config,
        private readonly Cache $cache,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->config->get('myori-ai.callback_secret')
            ?: throw GatewayException::notConfigured('callback_secret');
        $tolerance = (int) $this->config->get('myori-ai.signature_tolerance', 300);

        $timestamp = (string) $request->header(Signature::TIMESTAMP_HEADER);
        $signature = (string) $request->header(Signature::SIGNATURE_HEADER);

        if (! ctype_digit($timestamp) || $signature === '') {
            abort(401, 'Missing gateway signature.');
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > $tolerance) {
            abort(401, 'Gateway signature has expired.');
        }

        $expected = Signature::make($secret, (int) $timestamp, $request->getContent());

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid gateway signature.');
        }

        // A valid signature is accepted once. Remember it for longer than it stays valid.
        if (! $this->cache->add('myori-ai:signature:'.$signature, true, $tolerance * 2)) {
            abort(401, 'Gateway signature was already used.');
        }

        return $next($request);
    }
}
