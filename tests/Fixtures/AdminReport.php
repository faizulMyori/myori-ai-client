<?php

namespace Myori\AiClient\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Myori\AiClient\Tools\Tool;
use RuntimeException;

class AdminReport extends Tool
{
    public function name(): string
    {
        return 'admin_report';
    }

    public function description(): string
    {
        return 'Totals across every branch. Admins only.';
    }

    public function authorize(Authenticatable $user): bool
    {
        return $user->role === 'admin';
    }

    public function handle(Authenticatable $user, array $arguments): array
    {
        throw new RuntimeException('database password is hunter2');
    }
}
