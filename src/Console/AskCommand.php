<?php

namespace Myori\AiClient\Console;

use Illuminate\Console\Command;
use Myori\AiClient\ActingUser;
use Myori\AiClient\Exceptions\GatewayException;
use Myori\AiClient\Gateway;

class AskCommand extends Command
{
    protected $signature = 'myori-ai:ask
                            {user_id : Ask as this user (their permissions apply)}
                            {question : What to ask}';

    protected $description = 'Ask the MyORI AI assistant a question as one of this system\'s users';

    public function handle(Gateway $gateway): int
    {
        $user = ActingUser::find((string) $this->argument('user_id'));

        if (! $user) {
            $this->error("No user with id {$this->argument('user_id')}.");

            return self::FAILURE;
        }

        try {
            $answer = $gateway->chat($user, $this->argument('question'));
        } catch (GatewayException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($answer['answer'] ?? '');

        if (! empty($answer['tools_used'])) {
            $this->newLine();
            $this->comment('Live data used: '.implode(', ', $answer['tools_used']));
        }

        if (! empty($answer['sources'])) {
            $this->comment('Sources: '.implode(', ', array_column($answer['sources'], 'url')));
        }

        return self::SUCCESS;
    }
}
