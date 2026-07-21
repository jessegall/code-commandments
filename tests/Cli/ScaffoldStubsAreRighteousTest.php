<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use PHPUnit\Framework\TestCase;

/**
 * A scaffold is the package handing a consumer code it wrote itself — canon, by definition. Emitting a
 * stub that the package's OWN judge then flags in their project is the worst kind of finding: unactionable
 * (they didn't write it), unteachable (the rule's own author broke it), and it trains people to ignore the
 * checklist. So every stub is judged by the whole catalogue, exactly as a consumer's file would be.
 */
final class ScaffoldStubsAreRighteousTest extends TestCase
{
    private const string STUBS = __DIR__ . '/../../stubs';

    public function test_no_backend_stub_is_a_sin(): void
    {
        $violations = [];

        foreach (glob(self::STUBS . '/*.php.stub') ?: [] as $stub) {
            $name = basename($stub, '.stub');
            // Rendered exactly as `scaffold` writes it — the placeholder is a real namespace on disk.
            $codebase = Codebase::fromString(str_replace('{namespace}', 'App\\Support', (string) file_get_contents($stub)), $name);

            foreach (Catalog::backend() as $detector) {
                foreach ($detector->find($codebase) as $finding) {
                    $violations[] = "{$name}:{$finding->line()} — " . $detector->sin()->name();
                }
            }
        }

        $this->assertSame([], $violations, "A scaffold stub violates a rule this package teaches:\n" . implode("\n", $violations));
    }

    public function test_no_frontend_stub_is_a_sin(): void
    {
        $violations = [];

        foreach (glob(self::STUBS . '/vue/*.vue') ?: [] as $stub) {
            $name = basename($stub);
            $codebase = VueCodebase::fromString((string) file_get_contents($stub), $name);

            foreach (Catalog::frontend() as $detector) {
                foreach ($detector->find($codebase) as $finding) {
                    $violations[] = $finding->location() . ' — ' . $detector->sin()->name();
                }
            }
        }

        $this->assertSame([], $violations, "A scaffold stub violates a rule this package teaches:\n" . implode("\n", $violations));
    }
}
