<?php

namespace Myori\AiClient\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class GatewayException extends RuntimeException
{
    public static function notConfigured(string $key): self
    {
        return new self("MyORI AI is not configured: set myori-ai.{$key} (see config/myori-ai.php).");
    }

    public static function fromResponse(Response $response): self
    {
        $message = $response->json('message') ?: $response->reason();

        return new self("The MyORI AI gateway answered {$response->status()}: {$message}", $response->status());
    }
}
