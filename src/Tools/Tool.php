<?php

namespace Myori\AiClient\Tools;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A read-only question the AI assistant can ask this system on a user's
 * behalf, such as "how many stickers does my station hold?". The system
 * stays in charge: authorize() and handle() run with the system's own user
 * record, never with anything the gateway claims about the user.
 */
abstract class Tool
{
    /**
     * Unique snake_case name, for example "sticker_summary".
     */
    abstract public function name(): string;

    /**
     * What the tool returns and when to use it. The assistant reads this to
     * decide which tool answers a question, so be specific.
     */
    abstract public function description(): string;

    /**
     * JSON Schema for the arguments. Supported keywords: type (string,
     * integer, number, boolean), description, enum, minimum, maximum,
     * maxLength, format "date" (Y-m-d) and the object's "required" list.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    /**
     * Whether this user may use the tool at all. Tools a user may not use
     * are not even offered to the assistant.
     */
    public function authorize(Authenticatable $user): bool
    {
        return true;
    }

    /**
     * Run the tool. Return plain data (numbers, short lists), already limited
     * to what this user may see. Never return secrets or personal data the
     * user couldn't see in the app.
     *
     * @param  array<string, mixed>  $arguments  validated against parameters()
     * @return array<string, mixed>
     */
    abstract public function handle(Authenticatable $user, array $arguments): array;
}
