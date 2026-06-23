<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Profiles;

use JesseGall\CodeCommandments\Support\Profiles\GitGateStage;
use JesseGall\CodeCommandments\Support\Profiles\Inquiry;
use JesseGall\CodeCommandments\Support\Profiles\Phase;
use JesseGall\CodeCommandments\Support\Profiles\ProfileBehaviour;
use PHPUnit\Framework\TestCase;

class ProfileBehaviourTest extends TestCase
{
    public function test_gate_and_nudges_derive_from_judge_cadence(): void
    {
        $eachPhase = new ProfileBehaviour(Phase::EachPhase, Phase::EachPhase);
        $this->assertSame(GitGateStage::PreCommit, $eachPhase->gate());
        $this->assertTrue($eachPhase->nudgesEachPhase());
        $this->assertTrue($eachPhase->stopHookJudges());

        $atEnd = new ProfileBehaviour(Phase::AtEnd, Phase::AtEnd);
        $this->assertSame(GitGateStage::PrePush, $atEnd->gate());
        $this->assertFalse($atEnd->nudgesEachPhase());
        $this->assertFalse($atEnd->stopHookJudges(), 'AtEnd never judges in the keep-going loop');

        $never = new ProfileBehaviour(Phase::Never, Phase::Never);
        $this->assertSame(GitGateStage::None, $never->gate());
        $this->assertFalse($never->hasStopHook());
    }

    public function test_autonomy_briefing_is_implementation_scoped_with_a_plan_mode_exception(): void
    {
        $b = new ProfileBehaviour(Phase::AtEnd, Phase::AtEnd, Inquiry::WhenBlocked);
        $text = $b->autonomyBriefing();

        $this->assertStringContainsString('DURING IMPLEMENTATION', $text);
        $this->assertStringContainsString('PLAN MODE', $text);
        $this->assertStringContainsString('ask whatever you need', $text);
        $this->assertStringContainsString('genuine blocker', $text);
    }

    public function test_autonomy_briefing_varies_by_inquiry(): void
    {
        $decisions = (new ProfileBehaviour(Phase::EachPhase, Phase::EachPhase, Inquiry::OnDecisions))->autonomyBriefing();
        $this->assertStringContainsString('pause to ask before a consequential or ambiguous decision', $decisions);

        $freely = (new ProfileBehaviour(Phase::EachPhase, Phase::EachPhase, Inquiry::Freely))->autonomyBriefing();
        $this->assertStringContainsString('whenever a question would', $freely);
    }

    public function test_test_briefing_derives_from_test_cadence(): void
    {
        $this->assertStringContainsString('ONCE before pushing', (new ProfileBehaviour(Phase::AtEnd, Phase::AtEnd))->testBriefing());
        $this->assertStringContainsString('each phase', (new ProfileBehaviour(Phase::EachPhase, Phase::EachPhase))->testBriefing());
        $this->assertNull((new ProfileBehaviour(Phase::Never, Phase::Never))->testBriefing());
    }
}
