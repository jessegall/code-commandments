<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\CoupledFieldsDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class CoupledFieldsDetectorTest extends TestCase
{
    public function test_flags_fields_assembled_together_again_and_again(): void
    {
        $this->assertSame(['Booking'], $this->flagged(<<<'PY'
            from dataclasses import dataclass


            @dataclass(frozen=True)
            class Window:
                start: int
                end: int


            class Booking:
                def __init__(self, guest: str, start: int, end: int) -> None:
                    self.guest = guest
                    self.start = start
                    self.end = end

                def window(self) -> Window:
                    return Window(self.start, self.end)

                def overlaps(self, other: Window) -> bool:
                    mine = (self.start, self.end)
                    return mine[0] < other.end

                def shifted(self, days: int) -> Window:
                    return Window(self.start + days, self.end + days)

                def span(self) -> tuple[int, int]:
                    return (self.start, self.end)
            PY));
    }

    public function test_flags_fields_guarded_for_absence_together_and_a_mirrored_field(): void
    {
        $this->assertSame(['Transfer', 'Step'], $this->flagged(<<<'PY'
            class Edge:
                def __init__(self, source: str, target: str) -> None:
                    self.source = source
                    self.target = target


            class Transfer:
                def __init__(self, amount: int, source: str | None, target: str | None) -> None:
                    self.amount = amount
                    self.source = source
                    self.target = target

                def edge(self) -> Edge | None:
                    if self.source is None or self.target is None:
                        return None
                    return Edge(self.source, self.target)


            class Workflow:
                def __init__(self, id: str) -> None:
                    self.id = id


            class Step:
                def __init__(self, workflow: Workflow, workflow_id: str, name: str) -> None:
                    self.workflow = workflow
                    self.workflow_id = workflow_id
                    self.name = name
            PY));
    }

    public function test_leaves_a_one_off_mapping_services_and_a_whole_class_projection(): void
    {
        $this->assertSame([], $this->flagged(<<<'PY'
            class Row:
                def __init__(self, a: str, b: str, c: str) -> None:
                    self.a = a
                    self.b = b
                    self.c = c

                def pair(self) -> tuple[str, str]:
                    return (self.a, self.b)

                def whole(self) -> tuple[str, str, str]:
                    return (self.a, self.b, self.c)

                def again(self) -> tuple[str, str, str]:
                    return (self.a, self.b, self.c)
            PY));
    }

    /**
     * @return list<string>
     */
    private function flagged(string $source): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->name(), new CoupledFieldsDetector()->find(Codebase::fromString($source)));
    }
}
