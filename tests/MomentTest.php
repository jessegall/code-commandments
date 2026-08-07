<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Moment;
use PHPUnit\Framework\TestCase;

final class MomentTest extends TestCase
{
    public function test_token_round_trips(): void
    {
        foreach (Moment::cases() as $moment) {
            $this->assertSame($moment, Moment::fromToken($moment->token()));
        }
    }

    public function test_an_unknown_token_falls_back_to_complete(): void
    {
        // A token that names no moment runs the end gate. A bare `checks` — no token at all — is
        // the CALLER's default now (`Checks::run`), so this reader never has to answer for absence.
        $this->assertSame(Moment::Complete, Moment::fromToken('nonsense'));
        $this->assertSame(Moment::Start, Moment::fromToken('start'));
    }

    public function test_only_complete_appends_judge(): void
    {
        $this->assertTrue(Moment::Complete->hasJudgeGate());
        $this->assertFalse(Moment::Start->hasJudgeGate());
        $this->assertFalse(Moment::Phase->hasJudgeGate());
    }
}
