<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Testing\VueFixtureExamples;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

/**
 * The frontend engine's half of the `Fixed` contract — the same three markers the backend reads, and
 * the same preference between them.
 *
 * `@righteous` is a claim about the DETECTOR (a look-alike it must not flag, usually an exemption);
 * `@fixed` is a claim about the FIX. Publishing the first as the second taught the exemption instead
 * of the repair, which is exactly the mistake the backend markers exist to prevent.
 */
final class VueFixtureExamplesTest extends TestCase
{
    public function test_a_resolution_is_published_over_a_righteous_look_alike(): void
    {
        $example = $this->exampleFrom(<<<'VUE'
            <template>
              <!-- @sin SwitchCase -->
              <p v-if="status === 'paid'">Paid</p>
              <!-- @fixed SwitchCase -->
              <SwitchCase :value="status" />
              <!-- @righteous SwitchCase -->
              <slot v-if="status" />
            </template>
            VUE);

        $this->assertStringContainsString('<SwitchCase :value="status" />', (string) $example->good());
        $this->assertStringNotContainsString('<slot', (string) $example->good());
    }

    public function test_a_fix_spanning_blocks_publishes_every_block(): void
    {
        $example = $this->exampleFrom(<<<'VUE'
            <template>
              <!-- @sin SwitchCase -->
              <p v-if="status === 'paid'">Paid</p>
              <!-- @fixed SwitchCase -->
              <SwitchCase :value="status" />
              <!-- @fixed SwitchCase -->
              <template #paid>Paid</template>
            </template>
            VUE);

        $this->assertStringContainsString('<SwitchCase :value="status" />', (string) $example->good());
        $this->assertStringContainsString('<template #paid>Paid</template>', (string) $example->good());
    }

    public function test_a_block_of_a_group_is_named_by_its_component_not_this_machines_path(): void
    {
        $example = $this->exampleFrom(<<<'VUE'
            <template>
              <!-- @sin SwitchCase -->
              <p v-if="status === 'paid'">Paid</p>
              <!-- @fixed SwitchCase -->
              <SwitchCase :value="status" />
              <!-- @fixed SwitchCase -->
              <template #paid>Paid</template>
            </template>
            VUE);

        $this->assertStringContainsString('<!-- in OrderStatus.vue -->', (string) $example->good());
        $this->assertStringNotContainsString(dirname(__DIR__, 2), (string) $example->good());
    }

    /**
     * The example the frontend catalog's `SwitchCase` detector publishes for the given component.
     */
    private function exampleFrom(string $vue): \JesseGall\CodeCommandments\Testing\Example
    {
        $codebase = Codebase::fromString($vue, __DIR__ . '/OrderStatus.vue');
        $detector = $this->switchCaseDetector();
        $examples = VueFixtureExamples::extract($codebase, [$detector]);

        return $examples[$detector::class][0];
    }

    private function switchCaseDetector(): Detector
    {
        foreach (Catalog::frontend() as $detector) {
            if ((new \ReflectionClass($detector->sin()))->getShortName() === 'SwitchCase') {
                return $detector;
            }
        }

        $this->fail('The frontend catalog no longer holds a SwitchCase sin to test the markers with.');
    }
}
