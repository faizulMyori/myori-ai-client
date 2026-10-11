<?php

namespace Myori\AiClient\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Myori\AiClient\Tools\Tool;

class StockCount extends Tool
{
    public function name(): string
    {
        return 'stock_count';
    }

    public function description(): string
    {
        return 'Number of items in stock at the user\'s branch.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['available', 'reserved']],
                'since' => ['type' => 'string', 'format' => 'date'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
            'required' => ['status'],
        ];
    }

    public function handle(Authenticatable $user, array $arguments): array
    {
        return ['branch' => $user->branch, 'count' => 42, 'arguments' => $arguments];
    }
}
