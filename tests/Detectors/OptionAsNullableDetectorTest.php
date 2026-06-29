<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\OptionAsNullableDetector;
use PHPUnit\Framework\TestCase;

final class OptionAsNullableDetectorTest extends TestCase
{
    public function test_flags_option_worn_as_a_nullable(): void
    {
        $code = <<<'PHP'
        <?php
        class Repo {
            public function find(int $id): ?Option { return Option::none(); }
            public function total(int $id): Option { return Option::none(); }
            public function name(int $id): string {
                return $this->find($id)->unwrapOr(null) ?? 'x';
            }
            public function honest(int $id): string {
                return $this->find($id)->unwrapOr('default');
            }
        }
        PHP;

        $hits = (new OptionAsNullableDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        // ?Option return (find) + unwrapOr(null) (name) — not the total() Option,
        // not the unwrapOr('default').
        $this->assertSame(['Repo::find', 'Repo::name'], $scopes);
    }
}
