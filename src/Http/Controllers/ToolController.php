<?php

namespace Myori\AiClient\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Myori\AiClient\ActingUser;
use Myori\AiClient\Tools\ArgumentValidator;
use Myori\AiClient\Tools\ToolRegistry;
use Throwable;

/**
 * The endpoints the MyORI AI gateway calls (behind the myori-ai.signed
 * middleware) to list and run this system's tools for one user.
 */
class ToolController
{
    public function __construct(private readonly ToolRegistry $tools) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->actingUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json(['tools' => $this->tools->definitionsFor($user)]);
    }

    public function call(Request $request, ArgumentValidator $validator, string $tool): JsonResponse
    {
        $user = $this->actingUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $found = $this->tools->find($tool);

        if (! $found) {
            return response()->json(['error' => 'unknown_tool', 'message' => "There is no tool named \"{$tool}\"."], 404);
        }

        if (! $found->authorize($user)) {
            return response()->json(['error' => 'not_allowed', 'message' => 'This user is not allowed to use this tool.'], 403);
        }

        try {
            $arguments = $validator->validate($found, (array) $request->input('arguments', []));
        } catch (ValidationException $e) {
            return response()->json(['error' => 'invalid_arguments', 'message' => 'The arguments are not valid.', 'errors' => $e->errors()], 422);
        }

        try {
            return response()->json(['result' => $found->handle($user, $arguments)]);
        } catch (Throwable $e) {
            Log::error('myori-ai tool failed', ['tool' => $tool, 'user' => $user->getAuthIdentifier(), 'error' => $e->getMessage()]);

            return response()->json(['error' => 'tool_failed', 'message' => 'The tool could not run.'], 500);
        }
    }

    private function actingUser(Request $request): Authenticatable|JsonResponse
    {
        $id = $request->input('user.id');

        if (! is_scalar($id) || (string) $id === '') {
            return response()->json(['error' => 'invalid_user', 'message' => 'user.id is required.'], 422);
        }

        return ActingUser::find((string) $id)
            ?? response()->json(['error' => 'unknown_user', 'message' => 'This system has no such user.'], 404);
    }
}
