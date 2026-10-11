<?php

namespace Myori\AiClient\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Myori\AiClient\ActingUser;

class ChatRelayTest extends TestCase
{
    private const ANSWER = ['answer' => 'Your station holds 120 unused stickers.', 'sources' => [], 'tools_used' => ['sticker_summary']];

    private function user(): GenericUser
    {
        return new GenericUser(['id' => 7, 'name' => 'BBP Shah Alam']);
    }

    public function test_a_signed_in_user_s_question_is_forwarded_with_the_system_token(): void
    {
        Http::fake(['ai.myori.test/*' => Http::response(self::ANSWER)]);

        $this->actingAs($this->user())
            ->postJson('/myori-ai/chat', [
                'message' => 'How many unused stickers do we have?',
                'history' => [['role' => 'user', 'content' => 'Hi'], ['role' => 'assistant', 'content' => 'Hello!']],
            ])
            ->assertOk()
            ->assertExactJson(['answer' => self::ANSWER['answer'], 'sources' => [], 'tools_used' => ['sticker_summary']]);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://ai.myori.test/api/v1/chat'
            && $r->hasHeader('Authorization', 'Bearer 1|test-token')
            && $r['user']['id'] === '7'
            && $r['message'] === 'How many unused stickers do we have?'
            && count($r['history']) === 2);
    }

    public function test_guests_cannot_use_the_chat(): void
    {
        Http::fake();

        $this->postJson('/myori-ai/chat', ['message' => 'Hi'])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_the_question_is_validated(): void
    {
        Http::fake();

        $this->actingAs($this->user())->postJson('/myori-ai/chat', [])->assertUnprocessable();
        $this->actingAs($this->user())->postJson('/myori-ai/chat', ['message' => 'Hi', 'history' => [['role' => 'system', 'content' => 'x']]])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_a_gateway_outage_becomes_a_friendly_503(): void
    {
        Http::fake(['ai.myori.test/*' => Http::response(['message' => 'Server Error'], 500)]);

        $this->actingAs($this->user())
            ->postJson('/myori-ai/chat', ['message' => 'Hi'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The assistant is unavailable right now. Please try again in a moment.');
    }

    public function test_the_gateway_s_rate_limit_is_passed_on(): void
    {
        Http::fake(['ai.myori.test/*' => Http::response(['message' => 'Too Many Attempts.'], 429)]);

        $this->actingAs($this->user())->postJson('/myori-ai/chat', ['message' => 'Hi'])->assertStatus(429);
    }

    public function test_the_ask_command_asks_as_the_given_user(): void
    {
        Http::fake(['ai.myori.test/*' => Http::response(self::ANSWER)]);
        ActingUser::findUsing(fn (string $id) => $id === '7' ? $this->user() : null);

        try {
            $this->artisan('myori-ai:ask', ['user_id' => '7', 'question' => 'How many unused stickers?'])
                ->expectsOutput(self::ANSWER['answer'])
                ->expectsOutput('Live data used: sticker_summary')
                ->assertSuccessful();

            $this->artisan('myori-ai:ask', ['user_id' => '999', 'question' => 'Hi'])->assertFailed();
        } finally {
            ActingUser::findUsing(null);
        }
    }
}
