<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Judge;

use JesseGall\CodeCommandments\Ast\Codebase as PhpCodebase;
use JesseGall\CodeCommandments\Cli\Judge\DetectorRunner;
use JesseGall\CodeCommandments\Cli\Judge\Views;
use JesseGall\CodeCommandments\Cli\ProgressBar;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\CSharp\Detector as CSharpDetector;
use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Cs\Codebase as CSharpCodebase;
use JesseGall\CodeCommandments\Cs\NodeMatch as CSharpNodeMatch;
use JesseGall\CodeCommandments\Detectors\Backend\DuplicateFunctionDetector;
use JesseGall\CodeCommandments\Detectors\CrossFileSet;
use JesseGall\CodeCommandments\Finding;
use JesseGall\CodeCommandments\Py\Codebase as PythonCodebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use PHPUnit\Framework\TestCase;

/**
 * One run judges every engine the same way: each engine's rules over its own codebase, through the one
 * runner — so a Python rule gets the parallel workers a PHP one does, and the answer does not depend
 * on how many workers there are.
 */
final class DetectorRunnerEnginesTest extends TestCase
{
    public function test_every_engine_is_judged_in_one_run_whatever_the_parallelism(): void
    {
        $sequential = $this->locations(1);

        $this->assertContains('orders.py:1', $sequential);
        $this->assertContains('Orders.php:5', $sequential);
        $this->assertSame($sequential, $this->locations(4));
    }

    public function test_a_csharp_rule_is_judged_beside_the_others(): void
    {
        if (Bridge::located()->isNone()) {
            $this->markTestSkipped('the .NET SDK is not installed, so there is no bridge to read C# with');
        }

        $csharp = CSharpCodebase::fromString("public class Orders\n{\n    public int Total() => 1;\n}\n", 'Orders.cs');
        $group = [[new EveryCSharpFunction()], Views::of($csharp, Scope::everything(), CrossFileSet::unread())];

        $sequential = $this->locations(1, [$group]);

        $this->assertContains('Orders.cs:3', $sequential);
        $this->assertContains('orders.py:1', $sequential);
        $this->assertSame($sequential, $this->locations(4, [$group]));
    }

    /**
     * @param  list<array{0: list<\JesseGall\CodeCommandments\Detector>, 1: Views}>  $more  more engines' rules and views
     * @return list<string>
     */
    private function locations(int $parallel, array $more = []): array
    {
        $php = PhpCodebase::fromString(<<<'PHP'
            <?php
            final class Orders
            {
                public function total(array $lines): int { $sum = 0; foreach ($lines as $line) { $sum += $line['price'] * $line['quantity']; } return $sum; }
                public function sum(array $lines): int { $sum = 0; foreach ($lines as $line) { $sum += $line['price'] * $line['quantity']; } return $sum; }
            }
            PHP, 'Orders.php');
        $python = new PythonCodebase(['orders.py' => "def total():\n    return 1\n"]);
        $scope = Scope::everything();
        $beyond = CrossFileSet::unread();

        $judgement = new DetectorRunner($parallel)->run([
            [[new DuplicateFunctionDetector()], Views::of($php, $scope, $beyond)],
            [[new EveryFunction()], Views::of($python, $scope, $beyond)],
            ...$more,
        ], new ProgressBar());

        $locations = array_map(static fn (Finding $finding): string => basename($finding->location), $judgement->findings);
        sort($locations);

        return $locations;
    }
}

/**
 * A Python rule flagging every function.
 */
final class EveryFunction implements Detector
{
    public function sin(): Sin
    {
        return new EveryFunctionSin();
    }

    /**
     * @return list<NodeMatch>
     */
    public function find(PythonCodebase $codebase): array
    {
        return $codebase->whereFunction()->get();
    }
}

final class EveryFunctionSin extends Sin
{
    public function __construct()
    {
        parent::__construct(name: 'every-function', skill: FixAtTheSource::class, description: 'probe', rule: 'probe');
    }
}

/**
 * A C# rule flagging every function.
 */
final class EveryCSharpFunction implements CSharpDetector
{
    public function sin(): Sin
    {
        return new EveryFunctionSin();
    }

    /**
     * @return list<CSharpNodeMatch>
     */
    public function find(CSharpCodebase $codebase): array
    {
        return $codebase->whereFunction()->get();
    }
}
