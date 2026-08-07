<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Cli\Judge\DetectorRunner;
use JesseGall\CodeCommandments\Cli\ProgressBar;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Sin;
use PHPUnit\Framework\TestCase;

/**
 * A detector's result is reduced to a {@see \JesseGall\CodeCommandments\Finding} through the
 * {@see Located} CONTRACT and nothing else. The runner used to read a `file` PROPERTY instead, so a
 * result that satisfied the published interface crashed on a null property (#446) — a rule the package
 * documents must be implementable exactly as documented.
 */
final class LocatedFindingTest extends TestCase
{
    public function test_a_result_that_only_implements_the_interface_becomes_a_finding(): void
    {
        $detector = new class implements Detector
        {
            public function sin(): Sin
            {
                return new ArrayBag();
            }

            public function find(Codebase $codebase): array
            {
                return [new class implements Located
                {
                    public function file(): string
                    {
                        return '/app/Screen.php';
                    }

                    public function location(): string
                    {
                        return '/app/Screen.php:12';
                    }

                    public function scope(): string
                    {
                        return 'Screen::rows';
                    }
                }];
            }
        };

        $findings = new DetectorRunner(1)->run([$detector], Codebase::fromString('<?php class A {}'), new ProgressBar);

        $this->assertCount(1, $findings);
        $this->assertSame('/app/Screen.php', $findings[0]->file);
        $this->assertSame('/app/Screen.php:12', $findings[0]->location);
        $this->assertSame('Screen::rows', $findings[0]->scope);
    }
}
