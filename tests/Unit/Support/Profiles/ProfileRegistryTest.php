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
        $this->assertSame(['disabled', 'grind', 'phased', 'sins-only'], ProfileRegistry::names());
        $this->assertNull(ProfileRegistry::get('nope'));
        $this->assertFalse(ProfileRegistry::has('nope'));
    }

    public function test_grind_is_a_cadence_profile_that_still_flags_warnings(): void
    {
        $o = ProfileRegistry::get('grind')->options();

        $this->assertTrue($o->allowWarnings, 'grind must still flag warnings');
        $this->assertSame(JudgeScope::Branch, $o->scope);
        $this->assertSame(GitGateStage::PrePush, $o->gate);
        $this->assertFalse($o->perPhaseNudges, 'grind has no per-phase checks');
        // Branch gate blocks sins only (warnings shown, not blocked).
        $this->assertFalse($o->gateBlocksOnWarnings());
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

    public function test_disabled_is_inert(): void
    {
        $o = ProfileRegistry::get('disabled')->options();

        $this->assertFalse($o->briefAgent);
        $this->assertSame(GitGateStage::None, $o->gate);
        $this->assertSame(JudgeScope::None, $o->scope);
    }
}
