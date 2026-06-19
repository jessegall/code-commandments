<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionChainOverGuardProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferOptionChainOverGuardProphetTest extends TestCase
{
    private PreferOptionChainOverGuardProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferOptionChainOverGuardProphet;
    }

    public function test_flags_early_return_error_guard(): void
    {
        $j = $this->judge(<<<'PHP'
        class C {
            public function run($id): int {
                $workflow = $this->findWorkflow($id);

                if ($workflow->isEmpty()) {
                    return $this->respondError($id);
                }

                return $this->report($workflow->getOrThrow()->graph, $workflow->getOrThrow());
            }
        }
        PHP);

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('->transform', $j->warnings[0]->message);
        $this->assertStringContainsString('->orElse', $j->warnings[0]->message);
    }

    public function test_flags_throw_guard(): void
    {
        $j = $this->judge(<<<'PHP'
        class C {
            public function run($file): int {
                $graph = $this->graphFromFile($file);

                if ($graph->isEmpty()) {
                    throw new \RuntimeException('x');
                }

                return $this->compiler->compileSnapshot($graph->getOrThrow());
            }
        }
        PHP);

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('getOrThrow(fn () => <exception>)', $j->warnings[0]->message);
    }

    public function test_flags_negated_has_value_form(): void
    {
        $j = $this->judge(<<<'PHP'
        class C {
            public function run($id): int {
                $w = $this->find($id);
                if (! $w->hasValue()) {
                    return 0;
                }
                return $this->use($w->getOrThrow());
            }
        }
        PHP);

        $this->assertCount(1, $j->warnings);
    }

    public function test_ignores_void_return_guard_with_assignment(): void
    {
        // UnwrapOptionWithGuard's shape (void return + `$x = ...getOrThrow()`),
        // not this prophet's diverging-return shape.
        $j = $this->judge(<<<'PHP'
        class C {
            public function run($id): void {
                $w = $this->find($id);
                if ($w->isEmpty()) {
                    return;
                }
                $x = $w->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(0, $j->warnings);
    }

    public function test_ignores_assignment_unwrap_fallthrough(): void
    {
        // Fall-through is an assignment, not a `return` that unwraps inline.
        $j = $this->judge(<<<'PHP'
        class C {
            public function run($id): int {
                $w = $this->find($id);
                if ($w->isEmpty()) {
                    return 0;
                }
                $v = $w->getOrThrow();
                return $v;
            }
        }
        PHP);

        $this->assertCount(0, $j->warnings);
    }

    public function test_ignores_when_fallthrough_does_not_unwrap_same_option(): void
    {
        $j = $this->judge(<<<'PHP'
        class C {
            public function run($id): int {
                $w = $this->find($id);
                if ($w->isEmpty()) {
                    return 0;
                }
                return $this->other->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(0, $j->warnings);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
