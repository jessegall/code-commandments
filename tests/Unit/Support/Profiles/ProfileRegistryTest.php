<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Profiles;

use JesseGall\CodeCommandments\Support\Profiles\GitGateStage;
use JesseGall\CodeCommandments\Support\Profiles\JudgeScope;
use JesseGall\CodeCommandments\Support\Profiles\ProfileRegistry;
use PHPUnit\Framework\TestCase;

class ProfileRegistryTest extends TestCase
{
    public function test_default_is_disabled(): void
    {
        $this->assertSame('disabled', ProfileRegistry::default()->name());
        $this->assertSame('disabled', ProfileRegistry::DEFAULT);
    }

    public function test_catalogue(): void
    {
        $this->assertSame(['disabled', 'grind', 'phased', 'sins-only', 'penance'], ProfileRegistry::names());
        $this->assertNull(ProfileRegistry::get('nope'));
        $this->assertFalse(ProfileRegistry::has('nope'));
    }

    public function test_grind_is_a_cadence_profile_that_blocks_warnings_at_the_end(): void
    {
        $o = ProfileRegistry::get('grind')->options();

        $this->assertTrue($o->allowWarnings, 'grind must still flag warnings');
        $this->assertSame(JudgeScope::Branch, $o->scope);
        $this->assertSame(GitGateStage::PrePush, $o->gate);
        $this->assertFalse($o->perPhaseNudges, 'grind has no per-phase checks');
        // The pre-push reckoning gate blocks sins AND warnings — clean before push.
        $this->assertTrue($o->gateBlocksOnWarnings());
    }

    public function test_phased_gates_on_warnings_at_pre_commit(): void
    {
        $o = ProfileRegistry::get('phased')->options();

        $this->assertSame(GitGateStage::PreCommit, $o->gate);
        $this->assertTrue($o->gateBlocksOnWarnings());
        $this->assertTrue($o->perPhaseNudges);
    }

    public function test_sins_only_silences_warnings(): void
    {
        $o = ProfileRegistry::get('sins-only')->options();

        $this->assertFalse($o->allowWarnings);
        // With no warnings to gate on, the staged gate effectively blocks sins only.
        $this->assertFalse($o->gateBlocksOnWarnings());
    }

    public function test_penance_is_a_cleanup_with_no_commit_gate(): void
    {
        $o = ProfileRegistry::get('penance')->options();

        $this->assertTrue($o->allowWarnings, 'penance fixes warnings too');
        $this->assertSame(JudgeScope::None, $o->scope, 'penance audits the whole codebase');
        $this->assertSame(GitGateStage::PrePush, $o->gate, 'no commit gate; push-when-clean');
        $this->assertFalse($o->perPhaseNudges);
        $this->assertTrue($o->keepGoing, 'penance keeps going until righteous');
    }

    public function test_disabled_is_inert(): void
    {
        $o = ProfileRegistry::get('disabled')->options();

        $this->assertFalse($o->briefAgent);
        $this->assertSame(GitGateStage::None, $o->gate);
        $this->assertSame(JudgeScope::None, $o->scope);
    }
}
