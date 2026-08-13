<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DeNulledFinderDetector;
use PHPUnit\Framework\TestCase;

final class DeNulledFinderDetectorTest extends TestCase
{
    public function test_flags_a_nullable_finder_every_caller_de_nulls(): void
    {
        $code = <<<'PHP'
        <?php
        class Workflow { public function record(): void {} }
        class Job {
            public function workflowFor(int $id): ?Workflow { return null; }
            public function localOnly(int $id): ?Workflow { return null; }
            public function risky(int $id): ?Workflow { return null; }
            public function total(int $id): Workflow { return new Workflow; }

            public function handle(int $id): void {
                $w = $this->workflowFor($id);
                if ($w === null) { return; }
                $w->record();
            }

            public function settle(int $id): void {
                $this->workflowFor($id)?->record();
            }

            public function once(int $id): void {
                $this->localOnly($id)?->record();
            }

            public function rawTwice(int $id): void {
                $this->risky($id)->record();
                $this->risky($id)?->record();
            }
        }
        PHP;

        $hits = (new DeNulledFinderDetector)->find(Codebase::fromString($code));

        // workflowFor: de-nulled at 2 sites (travels) -> flagged.
        // localOnly: a single local caller checks it -> honest null, not flagged.
        // risky: 2 callers but one uses it raw -> not "every caller", not flagged.
        // total: not nullable.
        $this->assertSame(['Job::workflowFor'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_a_framework_contract_dictates_its_own_nullable_return(): void
    {
        // #463/#465/#466: `Guard::user()` is declared `Authenticatable|null` BY the framework, which
        // calls it precisely to ask whether anyone is authenticated. Narrowing it would stop the
        // class fulfilling the contract it exists for — so the fix the rule asks for cannot be made.
        // The typed accessor beside it is the class's OWN choice and is judged as ever.
        $code = <<<'PHP'
        <?php
        namespace App {
            use Illuminate\Contracts\Auth\Authenticatable;
            use Illuminate\Contracts\Auth\Guard;

            class PackerGuard implements Guard {
                public function user(): Authenticatable|null { return null; }
                public function packer(): User|null { return null; }

                public function id(): string|null { return $this->user()?->getAuthIdentifier(); }
                public function label(): string { return $this->user()?->getAuthIdentifier() ?? ''; }

                public function named(): string { return $this->packer()?->getAuthIdentifier() ?? ''; }
                public function slug(): string { return $this->packer()?->getAuthIdentifier() ?? '-'; }
            }

            class User { public function getAuthIdentifier(): string { return ''; } }
        }
        PHP;

        $scopes = array_map(
            static fn ($m): string => $m->scope(),
            (new DeNulledFinderDetector)->find(Codebase::fromString($code)),
        );

        $this->assertNotContains('App\PackerGuard::user', $scopes, 'the framework declared that return, not the class');
        $this->assertContains('App\PackerGuard::packer', $scopes, 'the accessor the class chose is judged as ever');
    }
}
