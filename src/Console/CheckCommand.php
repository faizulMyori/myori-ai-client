<?php

namespace Myori\AiClient\Console;

use Illuminate\Console\Command;
use Myori\AiClient\Exceptions\GatewayException;
use Myori\AiClient\Gateway;

class CheckCommand extends Command
{
    protected $signature = 'myori-ai:check
                            {user_id : The user id to send, as this system knows it}
                            {--name= : The user\'s name}
                            {--role=* : One of the user\'s roles (repeat for several)}';

    protected $description = 'Check that this system can reach the MyORI AI gateway and is recognised';

    public function handle(Gateway $gateway): int
    {
        try {
            $result = $gateway->whoami([
                'id' => $this->argument('user_id'),
                'name' => $this->option('name'),
                'roles' => $this->option('role'),
            ]);
        } catch (GatewayException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Connected to the MyORI AI gateway.');
        $this->table(['', ''], [
            ['System', "{$result['system']['name']} ({$result['system']['slug']})"],
            ['Allowed to', implode(', ', $result['system']['abilities']) ?: 'nothing'],
            ['User id', $result['user']['id']],
            ['User name', $result['user']['name'] ?? '(none)'],
            ['Roles', implode(', ', $result['user']['roles']) ?: '(none)'],
        ]);

        return self::SUCCESS;
    }
}
