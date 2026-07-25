<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Backend\FlagArgumentDetector;
use PHPUnit\Framework\TestCase;

final class FlagArgumentDetectorTest extends TestCase
{
    public function test_flags_a_body_that_is_nothing_but_a_branch_on_its_flag(): void
    {
        $code = <<<'PHP'
        <?php
        class Renderer {
            public function render(string $order, bool $compact): string {
                if ($compact) {
                    return $this->short($order);
                } else {
                    return $this->long($order);
                }
            }
            private function short(string $o): string { return $o; }
            private function long(string $o): string { return $o . '!'; }
        }
        PHP;

        $this->assertSame(['Renderer::render'], $this->scopes($code));
    }

    public function test_flags_a_match_with_a_true_and_a_false_arm(): void
    {
        $code = <<<'PHP'
        <?php
        class Mailer {
            public function send(string $to, bool $draft): string {
                return match ($draft) {
                    true => 'queued:' . $to,
                    false => 'sent:' . $to,
                };
            }
        }
        PHP;

        $this->assertSame(['Mailer::send'], $this->scopes($code));
    }

    public function test_a_negated_flag_is_the_same_switch(): void
    {
        $code = <<<'PHP'
        <?php
        class Exporter {
            public function write(string $rows, bool $raw): string {
                if (! $raw) {
                    return strtoupper($rows);
                } else {
                    return $rows;
                }
            }
        }
        PHP;

        $this->assertSame(['Exporter::write'], $this->scopes($code));
    }

    public function test_a_flag_the_method_stores_is_data_not_a_switch(): void
    {
        // `setVisible(true)` keeps the value; it does not obey it. The bool IS the point.
        $code = <<<'PHP'
        <?php
        class Widget {
            private bool $visible = false;
            public function setVisible(bool $visible): void { $this->visible = $visible; }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_guard_clause_is_not_a_two_way_branch(): void
    {
        // An early return that guards is the shape this rule must NOT touch — the codebase teaches
        // exactly that shape in guard-clauses-and-flow.
        $code = <<<'PHP'
        <?php
        class Saver {
            public function save(string $row, bool $force): string {
                if ($force) {
                    return 'overwritten';
                }

                $trimmed = trim($row);

                return 'saved:' . $trimmed;
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_branch_inside_a_method_doing_other_work_is_not_the_method(): void
    {
        // The method has a job and a conditional in it — it is not itself the conditional.
        $code = <<<'PHP'
        <?php
        class Report {
            public function build(string $rows, bool $compact): string {
                $header = 'REPORT';

                if ($compact) {
                    $body = trim($rows);
                } else {
                    $body = $rows;
                }

                return $header . $body;
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_condition_richer_than_the_flag_is_the_method_reasoning(): void
    {
        // `$compact && $rows !== ''` is a decision the method makes, not one it was handed.
        $code = <<<'PHP'
        <?php
        class Trimmer {
            public function run(string $rows, bool $compact): string {
                if ($compact && $rows !== '') {
                    return trim($rows);
                } else {
                    return $rows;
                }
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_two_arms_returning_different_constants_are_a_mapping_not_two_behaviours(): void
    {
        // A bool in, a value out. There is no pair of methods hiding in a lookup table.
        $code = <<<'PHP'
        <?php
        class Density {
            public function forDraft(bool $draft): int {
                return match ($draft) {
                    true => 150,
                    false => 300,
                };
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_an_arm_that_exits_with_a_bare_constant_is_a_guard(): void
    {
        // Found on the fixture: a precondition answered with `return 0` reads as a two-way branch,
        // but it is a guard wearing a redundant `else` — the else is the sin there, not the flag.
        $code = <<<'PHP'
        <?php
        class AccessLevel {
            public function resolve(bool $authenticated, int $role): int {
                if (! $authenticated) {
                    return 0;
                } else {
                    return $role;
                }
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_constructor_is_fields_being_born(): void
    {
        $code = <<<'PHP'
        <?php
        class Flagged {
            public array $modes;
            public function __construct(bool $compact) {
                if ($compact) {
                    $this->modes = ['compact'];
                } else {
                    $this->modes = ['full'];
                }
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    /**
     * @return list<string>
     */
    private function scopes(string $code): array
    {
        $hits = new FlagArgumentDetector()->find(Codebase::fromString($code));
        $scopes = array_map(static fn (NodeMatch $m): string => $m->scope(), $hits);
        sort($scopes);

        return $scopes;
    }
}
