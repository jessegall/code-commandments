<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Python;

use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use PHPUnit\Framework\TestCase;

/**
 * A Python rule is a {@see Detector} over a Python codebase, and the catalogs hold the Python engine
 * beside the other two — every detector, sin and skill listing includes what ships under `Python/`.
 */
final class DetectorContractTest extends TestCase
{
    public function test_a_python_detector_composes_the_python_query(): void
    {
        $detector = new class implements Detector {
            public function sin(): Sin
            {
                return new class extends Sin {
                    public function __construct()
                    {
                        parent::__construct(name: 'probe', skill: FixAtTheSource::class, description: 'probe', rule: 'probe');
                    }
                };
            }

            public function find(Codebase $codebase): array
            {
                return $codebase->whereFunction()->where(static fn (NodeMatch $match): bool => $match->isMethod())->get();
            }
        };

        $found = $detector->find(Codebase::fromString("def a():\n    pass\n\nclass B:\n    def c(self):\n        pass\n", 'shop.py'));

        $this->assertSame(['shop.py:5'], array_map(static fn (NodeMatch $match): string => $match->location(), $found));
    }

    public function test_every_catalog_includes_the_python_engine(): void
    {
        $this->assertEquals([...Detectors::backend(), ...Detectors::frontend(), ...Detectors::python()], Detectors::all());
        $this->assertEquals([...Sins::all(), ...Sins::frontend(), ...Sins::python()], Sins::every());
        $this->assertContainsOnlyInstancesOf(\JesseGall\CodeCommandments\Skills\Skill::class, Skills::python());
    }
}
