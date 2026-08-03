<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\TernaryStatementDetector;
use PHPUnit\Framework\TestCase;

final class TernaryStatementDetectorTest extends TestCase
{
    public function test_flags_a_ternary_whose_result_nothing_reads(): void
    {
        $code = <<<'PHP'
        <?php
        class T {
            public function walk(array $children, array $gone): array {
                foreach ($children as $child) {
                    $this->holds($child->id)
                        ? array_push($gone, ...$this->walk($child->kids, $gone))
                        : $gone[] = $child->id;
                }

                return $gone;
            }
            public function short(?string $name): void {
                $name ?: $this->warn();
            }
            public function asserted(?string $name): void {
                $name ?: throw NameMissing::of();
            }
            public function throwsOneArmOfTwo(?string $name): void {
                $this->known($name) ? $this->accept($name) : throw NameMissing::of();
            }
            public function assigned(array $row): string {
                $label = $row['name'] ? $row['name'] : 'anonymous';

                return $label;
            }
            public function returned(array $row): string {
                return $row['name'] ? $row['name'] : 'anonymous';
            }
            public function argument(array $row): void {
                $this->write($row['name'] ? 'named' : 'anonymous');
            }
            public function branched(array $row, array $gone): array {
                if ($this->holds($row['id'])) {
                    return $this->walk($row['kids'], $gone);
                }

                $gone[] = $row['id'];

                return $gone;
            }
        }
        PHP;

        $hits = (new TernaryStatementDetector)->find(Codebase::fromString($code));

        // `asserted` is the `?:` twin of `$name || throw …` — one action, and it is leaving.
        // `throwsOneArmOfTwo` still has a real arm doing work, so the branch is genuinely hidden.
        $this->assertSame(
            ['T::walk', 'T::short', 'T::throwsOneArmOfTwo'],
            array_map(static fn ($m): string => $m->scope(), $hits),
        );
    }
}
