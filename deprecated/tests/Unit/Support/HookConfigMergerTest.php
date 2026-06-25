<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\HookConfigMerger;
use JesseGall\CodeCommandments\Tests\TestCase;

class HookConfigMergerTest extends TestCase
{
    public function test_preserves_user_added_hooks_under_the_same_event(): void
    {
        $existing = [
            'Stop' => [
                ['hooks' => [['type' => 'command', 'command' => 'judge --git']]],
                ['hooks' => [['type' => 'command', 'command' => 'sh keep-going.sh']]], // user-added
            ],
        ];
        $ours = [
            'Stop' => [
                ['hooks' => [['type' => 'command', 'command' => 'judge --git']]],
            ],
        ];

        $merged = HookConfigMerger::merge($existing, $ours);

        $commands = [];
        foreach ($merged['Stop'] as $entry) {
            foreach ($entry['hooks'] as $h) {
                $commands[] = $h['command'];
            }
        }

        $this->assertContains('sh keep-going.sh', $commands, 'user hook must survive');
        $this->assertSame(1, count(array_filter($commands, fn ($c) => $c === 'judge --git')), 'ours must not duplicate');
    }

    public function test_adds_our_event_when_absent(): void
    {
        $merged = HookConfigMerger::merge([], [
            'PostToolUse' => [['matcher' => 'Bash', 'hooks' => [['type' => 'command', 'command' => 'x']]]],
        ]);

        $this->assertArrayHasKey('PostToolUse', $merged);
    }

    public function test_preserves_other_events_untouched(): void
    {
        $existing = ['CustomEvent' => [['hooks' => [['type' => 'command', 'command' => 'mine']]]]];

        $merged = HookConfigMerger::merge($existing, ['Stop' => [['hooks' => [['type' => 'command', 'command' => 'judge']]]]]);

        $this->assertArrayHasKey('CustomEvent', $merged);
        $this->assertArrayHasKey('Stop', $merged);
    }
}
