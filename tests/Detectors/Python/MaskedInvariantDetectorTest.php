<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\MaskedInvariantDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use PHPUnit\Framework\TestCase;

final class MaskedInvariantDetectorTest extends TestCase
{
    private const string MODEL = <<<'PY'
        class Period:
            def includes(self, day: str) -> bool:
                return True

            label: str = ""

        PY;

    public function test_flags_a_fake_literal_answering_for_scratch_state_the_operation_set(): void
    {
        $this->assertSame([17, 20, 23], $this->lines(<<<'PY'
            class Grader:
                def __init__(self) -> None:
                    self.period: Period | None = None

                def grade(self, period: Period, days: list[str]) -> list[str]:
                    self.period = period
                    return [day for day in days if self.covers(day)]

                def covers(self, day: str) -> bool:
                    return self.period.includes(day) if self.period else False

                def caption(self) -> str:
                    return self.period.label if self.period is not None else ""

                def label(self) -> str:
                    return getattr(self.period, "label", "none")
            PY));
    }

    public function test_leaves_a_field_set_only_at_construction_and_a_real_fallback(): void
    {
        $this->assertSame([], $this->lines(<<<'PY'
            class Viewer:
                def __init__(self, period: Period | None = None) -> None:
                    self.period = period

                def covers(self, day: str) -> bool:
                    return self.period.includes(day) if self.period else False


            class Cache:
                def __init__(self) -> None:
                    self.last: Period | None = None

                def remember(self, period: Period) -> None:
                    self.last = period

                def described(self, fallback: str) -> str:
                    return self.last.label if self.last else fallback
            PY));
    }

    /**
     * @return list<int>
     */
    private function lines(string $classes): array
    {
        return array_map(static fn (ExprMatch $match): int => $match->line(), new MaskedInvariantDetector()->find(Codebase::fromString(self::MODEL . "\n\n" . $classes)));
    }
}
