<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\DeepDataReachDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\DeepNestedDetector;
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
        $files = $this->extract(new DuplicateElementDetector, "<script setup>\n</script>\n<template><div>{$block}</div><aside>{$block}</aside></template>");

        $components = $this->components($files);

        $this->assertCount(1, $components, 'one component for the two duplicates');
        $this->assertStringContainsString('label: unknown', reset($components));
    }

    public function test_rewrites_the_call_site_and_imports_the_component(): void
    {
        $files = $this->extract(
            new DeepDataReachDetector,
            "<script setup lang=\"ts\">\nconst order = useOrder();\n</script>\n<template>\n  <div>\n"
            . str_repeat("    <p>row</p>\n", 55)
            . "    <section><p>{{ order.customer.fullName }}</p><p>{{ order.customer.email }}</p></section>\n  </div>\n</template>\n",
        );

        $source = $files['component.vue'];

        // the source imports the component and replaces the lifted markup with its usage
        $this->assertStringContainsString("import CustomerSection from './CustomerSection.vue';", $source);
        $this->assertStringContainsString('<CustomerSection :customer="order.customer" />', $source);
        $this->assertStringNotContainsString('order.customer.fullName', $source);
    }

    public function test_carries_the_imports_the_extracted_markup_uses(): void
    {
        // The source imports Badge (used) and Unused (not). Only Badge travels, so the
        // extracted component compiles where the app does not auto-import.
        $src = "<script setup lang=\"ts\">\nimport { Badge } from '@/ui/badge';\nimport { Unused } from '@/x';\n</script>\n"
            . "<template>\n  <div>\n" . str_repeat("    <p>row</p>\n", 55)
            . "    <section><Badge>{{ order.customer.fullName }}</Badge><Badge>{{ order.customer.email }}</Badge></section>\n  </div>\n</template>\n";

        $components = $this->components($this->extract(new DeepDataReachDetector, $src));
        $component = reset($components);

        $this->assertStringContainsString("import { Badge } from '@/ui/badge';", $component);
        $this->assertStringNotContainsString('Unused', $component);
    }

    public function test_forwards_a_source_prop_type_instead_of_unknown(): void
    {
        // `order` is declared `order: Order` in the source → a deep nest that keeps
        // `order` as a prop carries that type rather than `unknown`.
        $components = $this->components($this->extract(new DeepNestedDetector, $this->deepComponentReading('order')));
        $component = reset($components);

        $this->assertStringContainsString('order: Order', $component);
        $this->assertStringContainsString("import type { Order } from '@/types';", $component);
    }

    public function test_traces_a_local_computed_to_type_a_loop_variable(): void
    {
        // `groups` isn't a prop — it's `const groups = computed<Group[]>(…)`. Tracing
        // that declaration types the `group` loop variable as `Group`.
        $src = "<script setup lang=\"ts\">\nimport type { Group } from '@/types';\nconst groups = computed<Group[]>(() => []);\n</script>\n"
            . "<template>\n  <ul>\n"
            . '    <li v-for="group in groups" :key="group.id">' . str_repeat('<div>', 11) . '{{ group.name }}' . str_repeat('</div>', 11) . "</li>\n"
            . "    <li class=\"footer\">end</li>\n"
            . "  </ul>\n</template>\n";

        $components = $this->components($this->extract(new DeepNestedDetector, $src));

        $this->assertStringContainsString('group: Group', reset($components));
    }

    public function test_types_a_loop_variable_as_the_iterable_element_type(): void
    {
        // `interface Props { agents: Agent[] }` → a v-for="agent in agents" list item
        // gets `agent: Agent`, not unknown (the type read off a NAMED interface).
        $src = "<script setup lang=\"ts\">\nimport type { Agent } from '@/types';\ninterface Props { agents: Agent[] }\ndefineProps<Props>();\n</script>\n"
            . "<template>\n  <ul>\n"
            . '    <li v-for="agent in agents" :key="agent.id">' . str_repeat('<div>', 11) . '{{ agent.name }}' . str_repeat('</div>', 11) . "</li>\n"
            . "    <li class=\"footer\">end</li>\n"
            . "  </ul>\n</template>\n";

        $components = $this->components($this->extract(new DeepNestedDetector, $src));
        $component = reset($components);

        $this->assertStringContainsString('agent: Agent', $component);
        $this->assertStringContainsString("import type { Agent } from '@/types';", $component);
    }

    private function deepComponentReading(string $root): string
    {
        $leaf = "{$root}.field.value";

        return "<script setup lang=\"ts\">\nimport type { Order } from '@/types';\ndefineProps<{ {$root}: Order }>();\n</script>\n"
            . "<template>\n  " . $this->deepNest($leaf) . "\n  <footer>end</footer>\n</template>\n";
    }

    public function test_same_name_in_different_directories_is_not_suffixed(): void
    {
        // Two unrelated deep components in two folders both extract a `DataSection`.
        // Different folders, different files — neither may be renamed `DataSection2`.
        $dir = $this->tempDir();
        mkdir("{$dir}/a");
        mkdir("{$dir}/b");
        file_put_contents("{$dir}/a/PanelA.vue", $this->deepComponent('data.value'));
        file_put_contents("{$dir}/b/CardB.vue", $this->deepComponent('data.value'));

        $detector = new DeepNestedDetector();
        $paths = array_keys($detector->scribe()->rewrite($detector->find(Codebase::scan($dir))));

        $this->assertContains("{$dir}/a/DataSection.vue", $paths);
        $this->assertContains("{$dir}/b/DataSection.vue", $paths);
        $this->assertEmpty(preg_grep('/DataSection2\.vue$/', $paths), 'no cross-directory suffix');
    }

    public function test_two_collisions_in_one_directory_are_suffixed(): void
    {
        // Two deep sections in ONE file both name `DataSection` — a genuine same-folder
        // clash, so the second IS disambiguated.
        $dir = $this->tempDir();
        $body = $this->deepNest('data.value') . "\n  " . $this->deepNest('data.label');
        file_put_contents("{$dir}/Twin.vue", "<template>\n  {$body}\n</template>\n");

        $detector = new DeepNestedDetector();
        $paths = array_keys($detector->scribe()->rewrite($detector->find(Codebase::scan($dir))));

        $this->assertContains("{$dir}/DataSection.vue", $paths);
        $this->assertContains("{$dir}/DataSection2.vue", $paths);
    }

    private function deepComponent(string $leaf): string
    {
        return "<template>\n  {$this->deepNest($leaf)}\n  <footer>end</footer>\n</template>\n";
    }

    private function deepNest(string $leaf): string
    {
        return '<section>' . str_repeat('<div>', 13) . "<p>{{ {$leaf} }}</p>" . str_repeat('</div>', 13) . '</section>';
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/cc_extract_' . uniqid();
        mkdir($dir, 0777, true);
        $this->cleanup[] = $dir;

        return $dir;
    }

    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    private function onlyDeepReach(string $body): string
    {
        $filler = str_repeat("  <p>row</p>\n", 55);
        $files = $this->extract(new DeepDataReachDetector, "<template>\n  <div>\n{$filler}  {$body}\n  </div>\n</template>");
        $components = $this->components($files);

        $this->assertCount(1, $components, 'expected exactly one extracted component');

        return reset($components);
    }

    /**
     * The newly-created component files (everything but the refactored source).
     *
     * @param  array<string, string>  $files
     * @return array<string, string>
     */
    private function components(array $files): array
    {
        return array_filter($files, static fn (string $path): bool => $path !== 'component.vue', ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private function extract(DeepDataReachDetector|DuplicateElementDetector|DeepNestedDetector $detector, string $sfc): array
    {
        $codebase = Codebase::fromString($sfc);

        return $detector->scribe()->rewrite($detector->find($codebase));
    }
}
