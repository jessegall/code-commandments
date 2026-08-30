<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\Handlers\ModelChoiceReminder;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use PHPUnit\Framework\TestCase;

/**
 * A binding's matcher scopes the hook, not merely the wiring. The dispatcher hands every registered
 * handler every moment, so before this a hook that declared `PreToolUse/Agent` was asked about `Bash`,
 * `Read` and everything else — and stayed quiet only if it happened to re-check the tool itself. Found
 * by running the thing: the model reminder fired on a `grep` within a minute of being written.
 */
final class ABindingsMatcherScopesTheHookTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-match-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function said(array $payload): array
    {
        $io = new RecordingHookIO($payload, new FakeGit($this->root));

        new ModelChoiceReminder($io)->run([]);

        return array_values(array_filter(array_map(fn ($r) => $r->context->unwrapOr(''), $io->emitted)));
    }

    public function test_a_hook_bound_to_one_tool_says_nothing_about_another(): void
    {
        $this->assertSame([], $this->said([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => 'grep -n foo bar'],
        ]), 'a Bash call is not an agent dispatch, whatever the hook would say about one');
    }

    public function test_it_speaks_on_the_tool_it_bound_itself_to(): void
    {
        $said = $this->said([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Agent',
            'tool_input' => ['subagent_type' => 'ponytail', 'prompt' => 'read the commit'],
        ]);

        $this->assertCount(1, $said);
        $this->assertStringContainsString('ponytail', $said[0], 'it names the agent about to be started');
    }

    /**
     * The whole point of the reminder: an unnamed model is the expensive one, so a dispatch that HAS
     * chosen is left alone.
     */
    public function test_a_dispatch_that_named_a_model_is_left_alone(): void
    {
        $this->assertSame([], $this->said([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Agent',
            'tool_input' => ['subagent_type' => 'ponytail', 'model' => 'haiku'],
        ]));
    }
}
