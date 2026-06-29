<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class SwitchCaseDetectorTest extends TestCase
{
    public function test_flags_a_string_dispatch_chain(): void
    {
        $found = $this->find(<<<'VUE'
            <template>
              <div v-if="status === 'active'">A</div>
              <div v-else-if="status === 'pending'">B</div>
              <div v-else>C</div>
            </template>
            VUE);

        $this->assertCount(1, $found);
        $this->assertSame(2, $found[0]->line, 'the chain head (the v-if) is flagged');
    }

    public function test_flags_a_numeric_dispatch_chain_without_a_trailing_else(): void
    {
        $found = $this->find(<<<'VUE'
            <template>
              <Step v-if="step === 1" />
              <Step v-else-if="step === 2" />
              <Step v-else-if="step === 3" />
            </template>
            VUE);

        $this->assertCount(1, $found);
    }

    public function test_ignores_a_chain_over_different_variables(): void
    {
        $found = $this->find(<<<'VUE'
            <template>
              <div v-if="a === 1">A</div>
              <div v-else-if="b === 2">B</div>
            </template>
            VUE);

        $this->assertCount(0, $found);
    }

    public function test_ignores_non_equality_conditions(): void
    {
        $found = $this->find(<<<'VUE'
            <template>
              <div v-if="count > 5">many</div>
              <div v-else-if="count < 2">few</div>
            </template>
            VUE);

        $this->assertCount(0, $found);
    }

    public function test_ignores_a_lone_v_if(): void
    {
        $found = $this->find('<template><div v-if="open">x</div></template>');

        $this->assertCount(0, $found);
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Vue\ElementMatch>
     */
    private function find(string $vue): array
    {
        return new SwitchCaseDetector()->find(Codebase::fromString($vue));
    }
}
