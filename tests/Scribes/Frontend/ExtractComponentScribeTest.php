<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\DeepDataReachDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\DuplicateElementDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

/**
 * The extract-component scribe, driven by the detector that points at it — the same
 * findings → builder path the runner uses. Each generated component must be a valid,
 * self-contained SFC: a real root (never a bare slot/`v-else` `<template>`), and props
 * for every variable its markup still reads.
 */
final class ExtractComponentScribeTest extends TestCase
{
    public function test_deep_reach_flattens_the_shared_object_to_a_prop(): void
    {
        $src = $this->onlyDeepReach(
            '<section><p>{{ order.customer.fullName }}</p><p>{{ order.customer.email }}</p></section>',
        );

        // The mid-object becomes the prop; the chain is rewritten relative to it.
        $this->assertStringContainsString('customer: unknown', $src);
        $this->assertStringContainsString('{{ customer.fullName }}', $src);
        $this->assertStringContainsString('{{ customer.email }}', $src);
        $this->assertStringNotContainsString('order.customer', $src);
    }

    public function test_deep_reach_keeps_every_other_free_variable_as_a_prop(): void
    {
        // `projection` is also read shallowly (projection.name) → it stays a prop
        // alongside the flattened `rankingConfig`, so the draft compiles.
        $src = $this->onlyDeepReach(
            '<fieldset><h3>{{ projection.name }}</h3>'
            . '<input :value="projection.config.decimals" /><input :value="projection.config.prefix" /></fieldset>',
        );

        $this->assertStringContainsString('projection: unknown', $src);
        $this->assertStringContainsString('config: unknown', $src);
    }

    public function test_does_not_infer_called_functions_or_globals_as_props(): void
    {
        $src = $this->onlyDeepReach(
            '<div><input :value="row.cfg.a" @input="emit(\'x\', Number($event))" />'
            . '<input :value="row.cfg.b" @input="clearOverride()" /></div>',
        );

        // Only `cfg` is a prop; the called names and `$event` stay in the markup but
        // are never inferred as props.
        $props = substr($src, (int) strpos($src, 'defineProps'), (int) strpos($src, '}>') - (int) strpos($src, 'defineProps'));
        $this->assertStringContainsString('cfg: unknown', $props);
        $this->assertStringNotContainsString('emit', $props);
        $this->assertStringNotContainsString('Number', $props);
        $this->assertStringNotContainsString('$event', $props);
        $this->assertStringNotContainsString('clearOverride', $props);
    }

    public function test_unwraps_a_context_bound_template_to_its_content(): void
    {
        // The cluster boundary is a slot `<template #panel>` — the component root must
        // be its CONTENT, never the slot wrapper.
        $src = $this->onlyDeepReach(
            '<template #panel><div class="inner"><p>{{ stat.detail.a }}</p><p>{{ stat.detail.b }}</p></div></template>',
        );

        $this->assertStringContainsString('<template>', $src);
        $this->assertStringNotContainsString('#panel', $src);
        $this->assertStringContainsString('<div class="inner">', $src);
    }

    public function test_duplicate_blocks_collapse_to_one_component_with_their_free_vars(): void
    {
        $block = '<DialogClose class="close"><X class="icon" /><span>{{ label }}</span></DialogClose>';
        $files = $this->extract(new DuplicateElementDetector, "<template><div>{$block}</div><aside>{$block}</aside></template>");

        $this->assertCount(1, $files);
        $this->assertStringContainsString('label: unknown', reset($files));
    }

    private function onlyDeepReach(string $body): string
    {
        $filler = str_repeat("  <p>row</p>\n", 55);
        $files = $this->extract(new DeepDataReachDetector, "<template>\n  <div>\n{$filler}  {$body}\n  </div>\n</template>");

        $this->assertCount(1, $files, 'expected exactly one extracted component');

        return reset($files);
    }

    /**
     * @return array<string, string>
     */
    private function extract(DeepDataReachDetector|DuplicateElementDetector $detector, string $sfc): array
    {
        $codebase = Codebase::fromString($sfc);

        return $detector->scribe()->rewrite($detector->find($codebase));
    }
}
