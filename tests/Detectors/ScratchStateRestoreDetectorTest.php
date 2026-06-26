<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ScratchStateRestoreDetector;
use PHPUnit\Framework\TestCase;

final class ScratchStateRestoreDetectorTest extends TestCase
{
    public function test_flags_a_method_that_saves_and_restores_its_own_field(): void
    {
        $code = <<<'PHP'
        <?php
        final class Compiler {
            private string $scope = '';
            public function compile(string $scope): void {
                $previous = $this->scope;
                $this->scope = $scope;
                $this->emit();
                $this->scope = $previous;
            }
            private function emit(): void {}
        }
        PHP;

        $hits = (new ScratchStateRestoreDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Compiler::compile'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_the_righteous_twins_alone(): void
    {
        $code = <<<'PHP'
        <?php
        // dynamic scope: brackets a callable it was handed — the Context pattern, not a smuggled input
        final class Scoped {
            private string $scope = '';
            public function within(string $scope, callable $body): void {
                $previous = $this->scope;
                $this->scope = $scope;
                try { $body(); } finally { $this->scope = $previous; }
            }
        }
        // sets a field but never restores it — a plain mutation, not scratch state
        final class Setter {
            private string $scope = '';
            public function switchTo(string $scope): void { $this->scope = $scope; }
            public function current(): string { return $this->scope; }
        }
        // saves a local but it's never written back to the field
        final class Reader {
            private string $scope = 'root';
            public function snapshot(): string { $copy = $this->scope; return strtoupper($copy); }
        }
        PHP;

        $this->assertSame([], (new ScratchStateRestoreDetector)->find(Codebase::fromString($code)));
    }
}
