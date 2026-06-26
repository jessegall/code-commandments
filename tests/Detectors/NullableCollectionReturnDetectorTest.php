<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NullableCollectionReturnDetector;
use PHPUnit\Framework\TestCase;

final class NullableCollectionReturnDetectorTest extends TestCase
{
    public function test_flags_nullable_array_returns_only(): void
    {
        $code = <<<'PHP'
        <?php
        class Repo {
            public function tags(): ?array { return null; }
            public function labels(): array | null { return null; }
            public function items(): array { return []; }
            public function name(): ?string { return null; }
        }
        PHP;

        $hits = (new NullableCollectionReturnDetector)->find(Codebase::fromString($code));
        $names = array_map(static fn ($m): string => $m->enclosingFunctionName() ?? '?', $hits);
        sort($names);

        $this->assertSame(['labels', 'tags'], $names);
    }
}
