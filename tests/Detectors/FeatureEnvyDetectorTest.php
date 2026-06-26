<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\FeatureEnvyDetector;
use PHPUnit\Framework\TestCase;

final class FeatureEnvyDetectorTest extends TestCase
{
    public function test_flags_a_method_that_iterates_a_foreign_objects_collection(): void
    {
        $code = <<<'PHP'
        <?php
        class Basket { public array $amounts = []; public string $currency = ''; }
        class Totaller {
            public function total(Basket $basket): int {
                $sum = 0;
                foreach ($basket->amounts as $amount) {
                    $sum += $amount;
                }
                return $sum;
            }
        }
        PHP;

        $hits = (new FeatureEnvyDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Totaller::total'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_flags_an_external_collection_query_through_a_nested_object(): void
    {
        $code = <<<'PHP'
        <?php
        final class Descriptor {
            public function handleNames(): array { return ['then', 'else']; }
        }
        final class Context {
            public function __construct(public readonly Descriptor $descriptor) {}
        }
        final class Guard {
            public function permits(Context $context, string $branch): bool {
                return in_array($branch, $context->descriptor->handleNames(), true);
            }
        }
        PHP;

        $hits = (new FeatureEnvyDetector)->find(Codebase::fromString($code));

        // The chain resolver follows $context->descriptor to the real owner.
        $this->assertSame(['Guard::permits'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_flags_a_method_that_mutates_a_foreign_objects_state(): void
    {
        $code = <<<'PHP'
        <?php
        final class Account { public bool $frozen = false; public int $strikes = 0; }
        final class Sanctioner {
            public function freeze(Account $account): void {
                $account->strikes = $account->strikes + 1;
                $account->frozen = true;
            }
        }
        PHP;

        $hits = (new FeatureEnvyDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Sanctioner::freeze'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_the_righteous_twins_alone(): void
    {
        $code = <<<'PHP'
        <?php
        interface Pipe { public function run(Basket $b): int; }
        class Basket { public array $amounts = []; public string $currency = ''; }
        class Dims { public int $grams = 0; public int $w = 0; public int $h = 0; public int $l = 0; }

        // flat-scalar policy (Strategy) — no structural traversal
        class Grader {
            public function oversize(Dims $dims): bool {
                return $dims->grams > 1000 || $dims->w > 100 || $dims->h > 100 || $dims->l > 100;
            }
        }
        // pure formatting of the object's fields
        class Labeller {
            public function label(Dims $dims): string {
                return sprintf('%dg %d×%d×%d', $dims->grams, $dims->l, $dims->w, $dims->h);
            }
        }
        // iterates the collection but CONSTRUCTS a new type — a mapper/factory
        class Mapper {
            public function rows(Basket $basket): array {
                $rows = [];
                foreach ($basket->amounts as $amount) {
                    $rows[] = Money::of($amount);
                }
                return $rows;
            }
        }
        // polymorphic contract method — Strategy dispatch, can't move onto the data
        class SumPipe implements Pipe {
            public function run(Basket $basket): int {
                $sum = 0;
                foreach ($basket->amounts as $amount) {
                    $sum += $amount;
                }
                return $sum;
            }
        }
        PHP;

        $this->assertSame([], (new FeatureEnvyDetector)->find(Codebase::fromString($code)));
    }
}
