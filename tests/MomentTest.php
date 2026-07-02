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

    public function test_from_token_defaults_to_complete(): void
    {
        $this->assertSame(Moment::Complete, Moment::fromToken(null), 'a bare `checks` runs the end gate');
        $this->assertSame(Moment::Complete, Moment::fromToken('nonsense'));
        $this->assertSame(Moment::Start, Moment::fromToken('start'));
    }

    public function test_only_complete_appends_judge(): void
    {
        $this->assertTrue(Moment::Complete->appendsJudge());
        $this->assertFalse(Moment::Start->appendsJudge());
        $this->assertFalse(Moment::Phase->appendsJudge());
    }
}
