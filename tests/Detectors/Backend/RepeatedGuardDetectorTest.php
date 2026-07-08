<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\RepeatedGuardDetector;
use PHPUnit\Framework\TestCase;

final class RepeatedGuardDetectorTest extends TestCase
{
    public function test_flags_the_same_compound_guard_repeated(): void
    {
        $this->assertSame(2, $this->hits(<<<'PHP'
        class Runner {
            public function a($obj, $other): bool {
                return $obj->some && $other->some ? true : false;
            }
            public function b($obj, $other): bool {
                if ($obj->some && $other->some) { return true; }
                return false;
            }
        }
        PHP));
    }

    public function test_sees_through_local_aliases(): void
    {
        // One site inlines the reaches, the other stores them in locals first — the SAME guard.
        $this->assertSame(2, $this->hits(<<<'PHP'
        class Runner {
            public function a($obj, $other): bool {
                return $obj->some && $other->some;
            }
            public function b($obj, $other): bool {
                $objSome = $obj->some;
                $otherSome = $other->some;
                return $objSome && $otherSome;
            }
        }
        PHP));
    }

    public function test_is_order_independent(): void
    {
        // Reordered conjuncts are the same guard.
        $this->assertSame(2, $this->hits(<<<'PHP'
        class Runner {
            public function a($obj, $other): bool {
                return $obj->some && $other->some;
            }
            public function b($obj, $other): bool {
                return $other->some && $obj->some;
            }
        }
        PHP));
    }

    public function test_flags_the_same_guard_across_two_classes(): void
    {
        // The guard is copied into a DIFFERENT class — buckets by fingerprint over the whole codebase, not
        // per-class, so both copies are caught even though neither class repeats the guard on its own.
        $this->assertSame(2, $this->hits(<<<'PHP'
        class PublishGate {
            public function visible($item): bool {
                return $item->published && $item->approved;
            }
        }
        class ReviewQueue {
            public function promote($item): bool {
                return $item->published && $item->approved;
            }
        }
        PHP));
    }

    public function test_does_not_flag_a_one_off_guard(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Runner {
            public function a($obj, $other): bool { return $obj->some && $other->some; }
        }
        PHP));
    }

    public function test_does_not_flag_a_trivial_bare_boolean_guard(): void
    {
        // `$a && $b` on bare booleans has no substance — extracting it names nothing.
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Runner {
            public function a(bool $a, bool $b): bool { return $a && $b; }
            public function c(bool $a, bool $b): bool { return $a && $b; }
        }
        PHP));
    }

    public function test_does_not_flag_a_pure_instanceof_chain(): void
    {
        // Pure-type chains belong to repeated-type-guard, not here.
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Runner {
            public function a($n): bool { return $n instanceof New_ && $n->class instanceof Name; }
            public function b($n): bool { return $n instanceof New_ && $n->class instanceof Name; }
        }
        PHP));
    }

    private function hits(string $php): int
    {
        return count((new RepeatedGuardDetector)->find(Codebase::fromString("<?php\n" . $php, '/proj/app/File.php')));
    }
}
