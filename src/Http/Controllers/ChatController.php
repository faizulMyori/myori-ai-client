<?php

namespace Myori\AiClient\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Myori\AiClient\Exceptions\GatewayException;
use Myori\AiClient\Gateway;

/**
 * The chat endpoint for this system's own pages: the signed-in user asks,
 * this system forwards to the gateway with its token, and returns the answer.
 */
class ChatController
{
    public function __invoke(Request $request, Gateway $gateway): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:10'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $answer = $gateway->chat($request->user(), $validated['message'], $validated['history'] ?? []);
        } catch (GatewayException $e) {
            Log::warning('myori-ai chat failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => $e->getCode() === 429
                    ? 'Too many questions in a short time. Please wait a minute.'
                    : 'The assistant is unavailable right now. Please try again in a moment.',
            ], $e->getCode() === 429 ? 429 : 503);
        }

        return response()->json([
            'answer' => $answer['answer'] ?? '',
            'sources' => $answer['sources'] ?? [],
            'tools_used' => $answer['tools_used'] ?? [],
        ]);
    }
}
