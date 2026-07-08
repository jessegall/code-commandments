<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\RepeatedTypeGuardDetector;
use PHPUnit\Framework\TestCase;

final class RepeatedTypeGuardDetectorTest extends TestCase
{
    public function test_flags_the_same_instanceof_chain_repeated(): void
    {
        // The same `$n instanceof New_ && $n->class instanceof Name` guard in two methods.
        $this->assertSame(2, $this->hits(<<<'PHP'
        class Runner {
            public function a($n): bool {
                return $n instanceof New_ && $n->class instanceof Name ? true : false;
            }
            public function b($n): bool {
                if ($n instanceof New_ && $n->class instanceof Name) { return true; }
                return false;
            }
        }
        PHP));
    }

    public function test_does_not_flag_a_one_off_chain(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Runner {
            public function a($n): bool {
                return $n instanceof New_ && $n->class instanceof Name;
            }
        }
        PHP));
    }

    public function test_does_not_flag_a_single_instanceof(): void
    {
        // One `instanceof` (even repeated) is not a multi-part narrowing guard.
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Runner {
            public function a($n): bool { return $n instanceof New_ && $n !== null; }
            public function b($n): bool { return $n instanceof New_ && $n !== null; }
        }
        PHP));
    }

    public function test_does_not_group_two_different_guards(): void
    {
        // Different receivers/classes → different fingerprints → not "the same guard repeated".
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Runner {
            public function a($n): bool { return $n instanceof New_ && $n->class instanceof Name; }
            public function b($x): bool { return $x instanceof Assign && $x->var instanceof Variable; }
        }
        PHP));
    }

    private function hits(string $php): int
    {
        return count((new RepeatedTypeGuardDetector)->find(Codebase::fromString("<?php\n" . $php, '/proj/app/File.php')));
    }
}
