<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Profiles;

use JesseGall\CodeCommandments\Support\Profiles\GitGateStage;
use JesseGall\CodeCommandments\Support\Profiles\Phase;
use JesseGall\CodeCommandments\Support\Profiles\ProfileRegistry;
use JesseGall\CodeCommandments\Support\Profiles\StopHookBuilder;
use PHPUnit\Framework\TestCase;

class StopHookBuilderTest extends TestCase
{
    private function build(string $profile): ?string
    {
        return StopHookBuilder::build($profile, ProfileRegistry::get($profile)->options());
    }

    public function test_grind_never_invokes_judge_and_defers_tests(): void
    {
        $script = $this->build('grind');

        $this->assertNotNull($script);
        // No keep-going judge invocation — the reckon is the pre-push gate (#197).
        $this->assertDoesNotMatchRegularExpression('/\$\{run\}judge|eval .*judge/', $script);
        $this->assertStringContainsString('Do NOT run judge or tests between phases', $script);
        $this->assertStringStartsWith('#!/usr/bin/env sh', $script);
    }

    public function test_phased_judges_the_current_changes(): void
    {
        $script = $this->build('phased');

        // The gate PROBE scopes to the staged changes (--git); bypass lets it run
        // even mid-pilgrimage.
        $this->assertStringContainsString('COMMANDMENTS_PILGRIMAGE_BYPASS=1 eval "${run}judge --git --no-cache"', $script);
        $this->assertStringContainsString('and the test suite', $script); // test: EachPhase
    }

    public function test_penance_judges_the_whole_codebase(): void
    {
        $script = $this->build('penance');

        // The gate PROBE judges the whole codebase (no --git scope).
        $this->assertStringContainsString('COMMANDMENTS_PILGRIMAGE_BYPASS=1 eval "${run}judge --no-cache"', $script);
    }

    public function test_disabled_generates_no_script(): void
    {
        $this->assertNull($this->build('disabled'));
    }

    public function test_grind_autonomy_is_when_blocked(): void
    {
        $script = $this->build('grind');

        // WhenBlocked: release only for a genuine blocker, no "pause to ask".
        $this->assertStringContainsString('ONLY for a genuine blocker', $script);
        $this->assertStringNotContainsString('PAUSE to ask', $script);
        // The release reason quotes are escaped for the double-quoted sh string.
        $this->assertStringContainsString('plan-release.sh \\"<reason>\\"', $script);
    }

    public function test_phased_autonomy_pauses_on_decisions(): void
    {
        $script = $this->build('phased');

        $this->assertStringContainsString('PAUSE to ask the user before a consequential or ambiguous decision', $script);
    }

    public function test_behaviour_derives_gate_and_nudges(): void
    {
        $grind = ProfileRegistry::get('grind')->options();
        $this->assertSame(GitGateStage::PrePush, $grind->gate);
        $this->assertFalse($grind->perPhaseNudges);
        $this->assertSame(Phase::AtEnd, $grind->behaviour->test);

        $phased = ProfileRegistry::get('phased')->options();
        $this->assertSame(GitGateStage::PreCommit, $phased->gate);
        $this->assertTrue($phased->perPhaseNudges);

        $disabled = ProfileRegistry::get('disabled')->options();
        $this->assertSame(GitGateStage::None, $disabled->gate);
        $this->assertFalse($disabled->hasStopHook());
    }
}
