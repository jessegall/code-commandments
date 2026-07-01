<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Scribes\ScribeChain;
use JesseGall\CodeCommandments\Scribes\ScribeStep;
use JesseGall\CodeCommandments\WorkingCopy;
use PHPUnit\Framework\TestCase;

final class ScribeChainTest extends TestCase
{
    public function test_default_runs_in_place_fixers_before_extractors(): void
    {
        $names = $this->names(ScribeChain::default());

        // SwitchCase (an in-place fixer) must come before the extractors.
        $this->assertLessThan(
            array_search('DeepNestedDetector', $names, true),
            array_search('SwitchCaseDetector', $names, true),
            'in-place fixers run before component extractors',
        );
        $this->assertLessThan(
            array_search('DeepDataReachDetector', $names, true),
            array_search('ControlFlowOnElementDetector', $names, true),
        );
    }

    public function test_prepend_append_before_after_replace_remove(): void
    {
        $chain = (new ScribeChain())
            ->append($this->step('a'))
            ->append($this->step('b'))
            ->append($this->step('c'));

        $chain->prepend($this->step('first'))
            ->append($this->step('last'))
            ->before('b', $this->step('pre-b'))
            ->after('b', $this->step('post-b'))
            ->replace('c', $this->step('c2'))
            ->remove('a');

        $this->assertSame(['first', 'pre-b', 'b', 'post-b', 'c2', 'last'], $this->names($chain));
    }

    public function test_before_after_append_when_the_name_is_missing(): void
    {
        $chain = (new ScribeChain())->append($this->step('a'));
        $chain->before('nope', $this->step('x'))->after('nope', $this->step('y'));

        $this->assertSame(['a', 'x', 'y'], $this->names($chain));
    }

    public function test_matching_narrows_to_a_scope(): void
    {
        $chain = (new ScribeChain())
            ->append($this->step('AlphaDetector'))
            ->append($this->step('BetaDetector'))
            ->matching('Beta');

        $this->assertSame(['BetaDetector'], $this->names($chain));
    }

    /**
     * @return list<string>
     */
    private function names(ScribeChain $chain): array
    {
        return array_map(static fn (ScribeStep $step): string => $step->name(), $chain->steps());
    }

    private function step(string $name): ScribeStep
    {
        return new class($name) implements ScribeStep {
            public function __construct(private readonly string $name) {}

            public function name(): string
            {
                return $this->name;
            }

            public function run(string|array $path, Scope $scope, WorkingCopy $overlay = new WorkingCopy()): array
            {
                return [];
            }
        };
    }
}
