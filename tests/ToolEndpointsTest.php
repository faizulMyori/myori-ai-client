<?php

namespace Myori\AiClient\Tests;

use Illuminate\Auth\GenericUser;
use Myori\AiClient\ActingUser;
use Myori\AiClient\Signature;
use Myori\AiClient\Tests\Fixtures\AdminReport;
use Myori\AiClient\Tests\Fixtures\StockCount;

class ToolEndpointsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('myori-ai.tools', [StockCount::class, AdminReport::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $users = [
            '7' => new GenericUser(['id' => 7, 'name' => 'Station staff', 'role' => 'staff', 'branch' => 'BBP Shah Alam']),
            '1' => new GenericUser(['id' => 1, 'name' => 'Admin', 'role' => 'admin', 'branch' => 'HQ']),
        ];
        ActingUser::findUsing(fn (string $id) => $users[$id] ?? null);
    }

    protected function tearDown(): void
    {
        ActingUser::findUsing(null);

        parent::tearDown();
    }

    private function signedPost(string $uri, array $payload)
    {
        $body = json_encode($payload);
        $headers = Signature::headers('test-callback-secret', now()->getTimestamp(), $body) + [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        return $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    public function test_the_tool_list_only_offers_what_the_user_may_use(): void
    {
        $staff = $this->signedPost('/myori-ai/tools', ['user' => ['id' => '7']])->assertOk();
        $admin = $this->signedPost('/myori-ai/tools', ['user' => ['id' => '1']])->assertOk();

        $this->assertSame(['stock_count'], array_column($staff->json('tools'), 'name'));
        $this->assertSame(['stock_count', 'admin_report'], array_column($admin->json('tools'), 'name'));
        $this->assertSame(['status'], $staff->json('tools.0.input_schema.required'));
    }

    public function test_a_tool_runs_as_the_system_s_own_user_record(): void
    {
        $this->signedPost('/myori-ai/tools/stock_count', [
            'user' => ['id' => '7', 'roles' => ['admin']], // what the gateway claims is ignored
            'arguments' => ['status' => 'available', 'limit' => 5],
        ])
            ->assertOk()
            ->assertJsonPath('result.branch', 'BBP Shah Alam')
            ->assertJsonPath('result.count', 42);
    }

    public function test_arguments_not_in_the_schema_are_dropped(): void
    {
        $this->signedPost('/myori-ai/tools/stock_count', [
            'user' => ['id' => '7'],
            'arguments' => ['status' => 'available', 'branch' => 'HQ'],
        ])
            ->assertOk()
            ->assertJsonPath('result.arguments', ['status' => 'available']);
    }

    public function test_invalid_arguments_are_refused(): void
    {
        foreach ([[], ['status' => 'stolen'], ['status' => 'available', 'limit' => 500], ['status' => 'available', 'since' => '12/10/2026']] as $arguments) {
            $this->signedPost('/myori-ai/tools/stock_count', ['user' => ['id' => '7'], 'arguments' => $arguments])
                ->assertStatus(422)
                ->assertJsonPath('error', 'invalid_arguments');
        }
    }

    public function test_a_tool_the_user_may_not_use_is_refused(): void
    {
        $this->signedPost('/myori-ai/tools/admin_report', ['user' => ['id' => '7']])
            ->assertForbidden()
            ->assertJsonPath('error', 'not_allowed');
    }

    public function test_a_failing_tool_does_not_leak_its_error(): void
    {
        $this->signedPost('/myori-ai/tools/admin_report', ['user' => ['id' => '1']])
            ->assertStatus(500)
            ->assertJsonPath('error', 'tool_failed')
            ->assertDontSee('hunter2');
    }

    public function test_unknown_tools_and_users_are_reported(): void
    {
        $this->signedPost('/myori-ai/tools/delete_everything', ['user' => ['id' => '7']])->assertNotFound()->assertJsonPath('error', 'unknown_tool');
        $this->signedPost('/myori-ai/tools', ['user' => ['id' => '999']])->assertNotFound()->assertJsonPath('error', 'unknown_user');
        $this->signedPost('/myori-ai/tools', [])->assertStatus(422)->assertJsonPath('error', 'invalid_user');
    }

    public function test_unsigned_calls_are_refused(): void
    {
        $this->postJson('/myori-ai/tools', ['user' => ['id' => '1']])->assertUnauthorized();
        $this->postJson('/myori-ai/tools/stock_count', ['user' => ['id' => '1'], 'arguments' => ['status' => 'available']])->assertUnauthorized();
    }
}
