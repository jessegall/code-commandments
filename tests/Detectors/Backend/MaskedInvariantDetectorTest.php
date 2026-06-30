<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\MaskedInvariantDetector;
use PHPUnit\Framework\TestCase;

final class MaskedInvariantDetectorTest extends TestCase
{
    public function test_flags_a_default_over_a_transient_own_nullable(): void
    {
        $code = <<<'PHP'
        <?php
        final class Stage { public function terminal(string $id): bool { return $id === 'end'; } }
        final class Compiler {
            private ?Stage $stage = null;
            public function begin(): void { $this->stage = new Stage(); }
            public function isTerminal(string $id): bool {
                return $this->stage?->terminal($id) ?? false;
            }
        }
        PHP;

        $hits = (new MaskedInvariantDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Compiler::isTerminal'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_the_righteous_twins_alone(): void
    {
        $code = <<<'PHP'
        <?php
        final class Logger { public function info(string $m): bool { return $m !== ''; } }
        // constructor-injected, genuinely optional — absence is a Null-Object choice, not a lie
        final class Service {
            public function __construct(private readonly ?Logger $logger = null) {}
            public function run(string $m): bool { return $this->logger?->info($m) ?? false; }
        }
        // the field IS present and used without defending it — no lie, no smell
        final class Honest {
            private ?Stage $stage = null;
            public function begin(): void { $this->stage = new Stage(); }
            public function step(string $id): bool { return $this->stage->terminal($id); }
        }
        final class Stage { public function terminal(string $id): bool { return $id === 'end'; } }
        // defaulting to NULL, not a fake value — modelling absence, not masking it
        final class Finder {
            private ?Stage $stage = null;
            public function open(): void { $this->stage = new Stage(); }
            public function maybe(string $id): ?bool { return $this->stage?->terminal($id) ?? null; }
        }
        PHP;

        $this->assertSame([], (new MaskedInvariantDetector)->find(Codebase::fromString($code)));
    }
}
