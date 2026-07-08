<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\CompoundInlineComponentDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\DeepDataReachDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\DeepNestedDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\DuplicateElementDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ComponentLibrary;
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

    public function test_an_all_caps_prop_is_bound_verbatim_not_mangled(): void
    {
        // Issue #324: a prop whose name is an acronym / all-caps (`ROWS`) must bind verbatim
        // (`:ROWS="ROWS"`) — a naive per-uppercase kebab produced `:r-o-w-s`, which Vue's
        // `camelize` reads back as `rOWS`, never resolving the declared prop.
        $files = $this->extract(
            new DeepDataReachDetector,
            "<template>\n  <div>\n" . str_repeat("  <p>row</p>\n", 55)
            . "  <fieldset><h3>{{ ROWS.name }}</h3>"
            . "<input :value=\"ROWS.config.decimals\" /><input :value=\"ROWS.config.prefix\" /></fieldset>\n  </div>\n</template>",
        );

        $callSite = $files['component.vue'];

        $this->assertStringContainsString(':ROWS="ROWS"', $callSite, 'the all-caps prop binds verbatim');
        $this->assertStringNotContainsString(':r-o-w-s', $callSite, 'never per-uppercase kebab an acronym');
    }

    public function test_a_deep_reach_through_a_generic_container_is_named_after_a_meaningful_segment(): void
    {
        // Issue #315: a reach whose mid-object is a generic container word (`detail`) must NOT name
        // the component `DetailSection` — walk back to the informative segment (`spawnFailure`) so
        // the extracted file says what it shows.
        $files = $this->extract(
            new DeepDataReachDetector,
            "<template>\n  <div>\n" . str_repeat("  <p>row</p>\n", 55)
            . "  <section><p>{{ spawnFailure.detail.message }}</p><p>{{ spawnFailure.detail.code }}</p></section>\n  </div>\n</template>",
        );

        $names = array_map(static fn (string $p): string => basename($p, '.vue'), array_keys($this->components($files)));

        $this->assertContains('SpawnFailureSection', $names);
        $this->assertNotContains('DetailSection', $names);
    }

    public function test_a_module_local_static_const_is_copied_in_not_threaded_as_a_prop(): void
    {
        // Issue #324: a static `const INLINE = {…} as const` the markup reads is compile-time data,
        // not per-render input — the extraction must COPY the declaration into the child, never make
        // it a prop (`INLINE: unknown`) bound as a mangled fallthrough attribute at the call site.
        $files = $this->extract(
            new DeepDataReachDetector,
            "<script setup lang=\"ts\">\nconst INLINE = { BOOL: 'bool', INT: 'int' } as const;\nconst order = useOrder();\n</script>\n<template>\n  <div>\n"
            . str_repeat("    <p>row</p>\n", 55)
            . "    <section><p>{{ order.customer.fullName }}</p><p>{{ order.customer.kind === INLINE.BOOL }}</p></section>\n  </div>\n</template>\n",
        );

        $components = $this->components($files);
        $component = reset($components);

        $this->assertStringContainsString("const INLINE = { BOOL: 'bool', INT: 'int' } as const;", $component, 'the static const is copied in');
        $this->assertStringNotContainsString('INLINE: unknown', $component, 'it is never a prop');
        $this->assertStringNotContainsString(':INLINE="INLINE"', $files['component.vue'], 'and never bound at the call site');
    }

    public function test_a_ref_typed_binding_becomes_an_unwrapped_prop_not_a_raw_ref(): void
    {
        // Issue #320: a template binding auto-unwraps a top-level Ref, so a prop typed after one must
        // take the VALUE type — `Ref<InspectorTexts | null> | null` becomes `InspectorTexts | null`,
        // never a raw `Ref<…>` threaded through (which reads undefined in the child).
        $files = $this->extract(
            new DeepDataReachDetector,
            "<script setup lang=\"ts\">\nimport type { Ref } from 'vue';\nconst texts: Ref<InspectorTexts | null> | null = getTexts();\nconst order = useOrder();\n</script>\n<template>\n  <div>\n"
            . str_repeat("    <p>row</p>\n", 55)
            . "    <section><p>{{ order.customer.fullName }}: {{ texts?.hint }}</p><p>{{ order.customer.email }}</p></section>\n  </div>\n</template>\n",
        );

        $components = $this->components($files);
        $component = reset($components);

        $this->assertStringContainsString('texts: InspectorTexts | null', $component, 'the ref is unwrapped to its value type');
        $this->assertStringNotContainsString('Ref<', $component, 'no raw Ref threaded as a prop');
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

    public function test_unwraps_a_structural_directive_template_so_the_root_is_a_real_element(): void
    {
        // A `<template v-if>` boundary must NOT become a component rooted in a bare
        // `<template>` (invalid SFC) — lift its content; the v-if rides to the call site.
        $src = $this->onlyDeepReach(
            '<template v-if="ready"><div class="inner"><p>{{ stat.detail.a }}</p><p>{{ stat.detail.b }}</p></div></template>',
        );

        // The component root is the real element, never a bare/structural template…
        $this->assertStringContainsString('<div class="inner">', $src);
        $this->assertStringNotContainsString('v-if', $src);
        // …so the SFC <template> is not immediately followed by another <template>.
        $afterOpen = ltrim(substr($src, (int) strpos($src, '<template>') + strlen('<template>')));
        $this->assertStringStartsWith('<div', $afterOpen);
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

    public function test_identical_extractions_in_one_directory_reuse_one_component(): void
    {
        // Two sibling files with the SAME extractable block. The first creates
        // CustomerSection; the second must REUSE it (the library learns about a component
        // the moment it's drafted, mid-run) — not create a CustomerSection2 duplicate.
        $dir = $this->tempDir();
        $filler = str_repeat("  <p>row</p>\n", 55);
        $file = "<template>\n  <div>\n{$filler}  <section><p>{{ order.customer.fullName }}</p><p>{{ order.customer.email }}</p></section>\n  </div>\n</template>\n";
        file_put_contents("{$dir}/PanelA.vue", $file);
        file_put_contents("{$dir}/PanelB.vue", $file);

        $detector = new DeepDataReachDetector();
        $codebase = Codebase::scan($dir);
        $scribe = $detector->scribe()->withLibrary(ComponentLibrary::from($codebase));
        $paths = array_keys($scribe->rewrite($detector->find($codebase)));

        $this->assertContains("{$dir}/CustomerSection.vue", $paths);
        $this->assertEmpty(preg_grep('/CustomerSection2\.vue$/', $paths), 'the identical block must reuse, not duplicate into CustomerSection2');
    }

    public function test_the_oracle_dry_render_does_not_leak_phantom_reuses(): void
    {
        // Regression (issue #297): with an oracle set, the throwaway prime() dry-render used to
        // register its would-be components into the SHARED library, so the real pass "reused" those
        // phantoms — emitting `<CustomerSection … />` + `import` but NEVER creating the file (a
        // dangling import that guts the source). Every component an import references MUST be created.
        $dir = $this->tempDir();
        $filler = str_repeat("  <p>row</p>\n", 55);
        $file = "<template>\n  <div>\n{$filler}  <section><p>{{ order.customer.fullName }}</p><p>{{ order.customer.email }}</p></section>\n  </div>\n</template>\n";
        file_put_contents("{$dir}/PanelA.vue", $file);

        $oracle = new class implements \JesseGall\CodeCommandments\Vue\Oracle\TypeOracle {
            public function resolveAll(array $queries): array
            {
                return []; // resolves nothing — but the dry pass it drives must still not pollute
            }
        };

        $detector = new DeepDataReachDetector();
        $codebase = Codebase::scan($dir);
        $scribe = $detector->scribe()->withLibrary(ComponentLibrary::from($codebase))->withOracle($oracle);
        $files = $scribe->rewrite($detector->find($codebase));

        $this->assertArrayHasKey("{$dir}/CustomerSection.vue", $files, 'the extracted component is actually created, not a phantom reuse');
        $this->assertStringContainsString('<CustomerSection', $files["{$dir}/PanelA.vue"] ?? '', 'the call site references it');
    }

    public function test_a_dynamic_compound_title_does_not_become_the_component_name(): void
    {
        // The DialogTitle is a binding expression, not static text. It must NOT be pascal-
        // cased into a monster name — the compound falls back to its structural name.
        $dialog = '<Dialog><DialogContent><DialogHeader>'
            . "<DialogTitle>{{ selected ? selected.name : (scoped ? 'Add account' : 'Add credential') }}</DialogTitle>"
            . '<DialogDescription>{{ blurb }}</DialogDescription></DialogHeader>'
            . '<form><Label>A</Label><Input :model-value="a" /><Label>B</Label><Input :model-value="b" />'
            . '<Label>C</Label><Input :model-value="c" /><Button>Save</Button></form></DialogContent></Dialog>';
        $sfc = "<script setup lang=\"ts\">\nimport { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/ui/dialog';\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$dialog}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $codebase = Codebase::fromString($sfc);
        $files = $detector->scribe()->rewrite($detector->find($codebase));
        $created = array_keys($this->components($files));

        $this->assertNotEmpty($created, 'the dialog should still be extracted');
        foreach ($created as $path) {
            $name = basename($path, '.vue');
            // A sane structural name, NOT the pascal-cased ternary expression.
            $this->assertLessThan(28, strlen($name), "monster name from a dynamic title: {$name}");
            $this->assertStringNotContainsString('AddAccount', $name);
            $this->assertStringNotContainsString('AddCredential', $name);
            $this->assertStringNotContainsString('Scoped', $name);
        }
    }

    public function test_a_written_value_becomes_a_model_not_a_prop(): void
    {
        // The bug: a value the chunk WRITES — `v-model:open="confirmOpen"` plus
        // `@click="confirmOpen = false"` — was forwarded as a plain prop, and Vue rejects a
        // v-model on a prop (build error). It must be a defineModel, bound with v-model.
        $popover = '<Popover v-model:open="confirmOpen"><PopoverTrigger as-child>'
            . '<Button variant="destructive"><Trash2 class="size-3" />Delete node</Button></PopoverTrigger>'
            . '<PopoverContent class="w-64"><header><h4>Confirm</h4></header>'
            . '<p class="title">Delete {{ label }}?</p><p class="note">This cannot be undone.</p>'
            . '<ul><li>One</li><li>Two</li></ul>'
            . '<div class="actions"><Button @click="confirmOpen = false">Cancel</Button>'
            . '<Button @click="remove">Delete</Button></div></PopoverContent></Popover>';
        $sfc = "<script setup lang=\"ts\">\nimport { Popover, PopoverTrigger, PopoverContent } from '@/ui/popover';\nconst confirmOpen = ref(false);\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$popover}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $codebase = Codebase::fromString($sfc);
        $files = $detector->scribe()->rewrite($detector->find($codebase));
        $created = $this->components($files);

        $this->assertNotEmpty($created, 'the popover should be extracted');
        $component = reset($created);

        // confirmOpen is two-way state — a model, never a prop (Vue forbids writing a prop).
        $this->assertStringContainsString("defineModel<boolean>('confirmOpen')", $component);
        $this->assertStringNotContainsString('confirmOpen: boolean', $component, 'must not be in defineProps');
        $this->assertStringContainsString('v-model:open="confirmOpen"', $component, 'the inner v-model still works on the model');

        // The call site binds the model with v-model, the read-only `label`/`remove` with `:`.
        $callSite = $files['component.vue'];
        $this->assertStringContainsString('v-model:confirm-open="confirmOpen"', $callSite);
    }

    public function test_an_inertia_useform_model_is_typed_from_its_seed_not_unknown(): void
    {
        // Issue #304(2): an extracted `v-model` bound to an Inertia `useForm({…})` was typed
        // `defineModel<unknown>`, breaking every `form.x` access. It must be synthesised as
        // `InertiaForm<{ shape }>` from the seed object, with the `InertiaForm` type imported.
        $dialog = '<Dialog><DialogContent><DialogHeader><DialogTitle>Edit</DialogTitle></DialogHeader>'
            . '<form><Label>Name</Label><Input v-model="form.name" />'
            . '<Label>Qty</Label><Input v-model="form.quantity" />'
            . '<Label>Active</Label><Input v-model="form.active" />'
            . '<Button @click="form.reset()">Reset</Button></form></DialogContent></Dialog>';
        $sfc = "<script setup lang=\"ts\">\nimport { useForm } from '@inertiajs/vue3';\n"
            . "import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/ui/dialog';\n"
            . "const form = useForm({ name: '', quantity: 0, active: true });\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$dialog}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $files = $detector->scribe()->rewrite($detector->find(Codebase::fromString($sfc)));
        $created = $this->components($files);
        $component = $created === [] ? '' : (string) reset($created);

        $this->assertStringContainsString('InertiaForm<{ name: string; quantity: number; active: boolean }>', $component);
        $this->assertStringContainsString("import type { InertiaForm } from '@inertiajs/vue3';", $component);
        $this->assertStringNotContainsString('unknown', $component, 'the form no longer falls to unknown');
    }

    public function test_a_props_variable_access_forwards_the_member_not_the_props_object(): void
    {
        // A chunk that reads `props.label` must forward `label` (typed from Props), not a bogus
        // `props: unknown`; the child markup uses bare `label`, and the call site binds props.label.
        $popover = '<Popover v-model:open="confirmOpen"><PopoverTrigger as-child>'
            . '<Button variant="destructive"><Trash2 class="size-3" />Delete node</Button></PopoverTrigger>'
            . '<PopoverContent class="w-64"><header><h4>Confirm</h4></header>'
            . '<p class="title">Delete {{ props.label }}?</p><p class="note">This cannot be undone.</p>'
            . '<ul><li>One</li><li>Two</li></ul>'
            . '<div class="actions"><Button @click="confirmOpen = false">Cancel</Button>'
            . '<Button @click="remove">Delete</Button></div></PopoverContent></Popover>';
        $sfc = "<script setup lang=\"ts\">\nimport { Popover, PopoverTrigger, PopoverContent } from '@/ui/popover';\ninterface Props { label: string }\nconst props = defineProps<Props>();\nconst confirmOpen = ref(false);\nfunction remove() {}\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$popover}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $files = $detector->scribe()->rewrite($detector->find(Codebase::fromString($sfc)));
        $created = $this->components($files);
        $component = reset($created);

        $this->assertStringContainsString('label: string', $component, 'the member is a typed prop');
        $this->assertStringNotContainsString('props: unknown', $component, 'the props object is NOT a prop');
        $this->assertStringNotContainsString('props.label', $component, 'the markup uses the bare prop');
        $this->assertStringContainsString(':label="props.label"', $files['component.vue'], 'the call site binds the parent expression');
    }

    public function test_the_oracle_types_a_prop_no_ast_rung_could(): void
    {
        // A local `computed` with a member body is the classic vue-tsc-only unknown. When a checker
        // is present it resolves it; the generated prop takes that type instead of `unknown`.
        $popover = '<Popover><PopoverTrigger as-child><Button>Open</Button></PopoverTrigger>'
            . '<PopoverContent class="w-64"><header><h4>Summary</h4></header>'
            . '<p class="title">{{ blurb }}</p><p class="note">Detail</p>'
            . '<ul><li>One</li><li>Two</li></ul>'
            . '<div class="actions"><Button>Close</Button></div></PopoverContent></Popover>';
        $sfc = "<script setup lang=\"ts\">\nimport { Popover, PopoverTrigger, PopoverContent } from '@/ui/popover';\nconst blurb = computed(() => schema.value.label);\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$popover}\n  </div>\n</template>\n";

        $oracle = new class implements \JesseGall\CodeCommandments\Vue\Oracle\TypeOracle {
            public function resolveAll(array $queries): array
            {
                $resolved = [];

                foreach ($queries as $path => $query) {
                    if (in_array('blurb', $query['names'], true)) {
                        $resolved[$path] = ['blurb' => 'string | null'];
                    }
                }

                return $resolved;
            }
        };

        $detector = new CompoundInlineComponentDetector();
        $files = $detector->scribe()->withOracle($oracle)->rewrite($detector->find(Codebase::fromString($sfc)));
        $created = $this->components($files);
        $component = reset($created);

        $this->assertStringContainsString('blurb: string | null', $component, 'the oracle resolved it');
        $this->assertStringNotContainsString('blurb: unknown', $component);
    }

    public function test_an_assigned_value_becomes_a_model_not_a_readonly_prop(): void
    {
        // Issue #256: a value the chunk only ASSIGNS (no v-model) — `@click="dismissed = true"`
        // — was lifted as a plain prop, making the assignment a silent no-op (readonly prop).
        // It must become a defineModel so the write emits update: and reaches the parent.
        $dialog = '<Dialog><DialogContent><DialogHeader>'
            . '<DialogTitle>Confirm</DialogTitle><DialogDescription>{{ blurb }}</DialogDescription></DialogHeader>'
            . '<div class="body"><p>One</p><p>Two</p><ul><li>a</li><li>b</li></ul></div>'
            . '<DialogFooter><Button @click="dismissed = true">Dismiss</Button>'
            . '<Button @click="confirm">OK</Button></DialogFooter></DialogContent></Dialog>';
        $sfc = "<script setup lang=\"ts\">\nimport { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/ui/dialog';\nconst dismissed = ref(false);\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$dialog}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $codebase = Codebase::fromString($sfc);
        $files = $detector->scribe()->rewrite($detector->find($codebase));
        $created = $this->components($files);

        $this->assertNotEmpty($created, 'the dialog should be extracted');
        $component = reset($created);

        // `dismissed` is assigned in the chunk → a model, so the write actually propagates.
        $this->assertStringContainsString("defineModel<boolean>('dismissed')", $component);
        $this->assertStringNotContainsString('dismissed: boolean', $component, 'must not be a readonly prop');
        $this->assertStringContainsString('v-model:dismissed="dismissed"', $files['component.vue']);
    }

    public function test_a_chunk_that_renders_slots_forwards_the_host_slots(): void
    {
        // The bug: extracting a chunk that renders `<slot>` left the call site self-closing,
        // so the host's named slots never reached the new component and bodies went empty.
        $dialog = '<Dialog><DialogContent><DialogHeader>'
            . '<DialogTitle>Title</DialogTitle><DialogDescription>{{ blurb }}</DialogDescription></DialogHeader>'
            . '<div class="body"><slot name="lead" /><p>One</p><p>Two</p><ul><li>a</li><li>b</li></ul></div>'
            . '<DialogFooter><Button>OK</Button></DialogFooter></DialogContent></Dialog>';
        $sfc = "<script setup lang=\"ts\">\nimport { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/ui/dialog';\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$dialog}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $files = $detector->scribe()->rewrite($detector->find(Codebase::fromString($sfc)));
        $created = $this->components($files);

        $this->assertNotEmpty($created, 'the dialog should be extracted');
        $this->assertStringContainsString('<slot name="lead"', reset($created), 'the extracted component still renders the slot');

        // The call site is NOT self-closing — it forwards the host's slots transparently.
        $callSite = $files['component.vue'];
        $this->assertStringContainsString('v-for="(_, name) in $slots"', $callSite);
        $this->assertStringContainsString('<slot :name="name" v-bind="slotProps" />', $callSite);
    }

    public function test_a_handler_call_to_a_parent_function_is_forwarded_as_an_emit(): void
    {
        // Issue #257: a handler that CALLS a parent-local function — `@click="copyJson('nodes')"`
        // — was lifted verbatim, but `copyJson` is undefined in the child, so the button became a
        // silent no-op. It must become `$emit('copyJson', 'nodes')` in the child, a `defineEmits`
        // declaration there, and `@copy-json="copyJson"` at the call site.
        $dialog = '<Dialog><DialogContent><DialogHeader>'
            . '<DialogTitle>Export</DialogTitle><DialogDescription>{{ blurb }}</DialogDescription></DialogHeader>'
            . '<div class="body"><p>One</p><p>Two</p><ul><li>a</li><li>b</li></ul></div>'
            . '<DialogFooter><Button @click="copyJson(\'nodes\')">Copy</Button></DialogFooter></DialogContent></Dialog>';
        $sfc = "<script setup lang=\"ts\">\nimport { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/ui/dialog';\nfunction copyJson(scope: string) { navigator.clipboard.writeText(scope); }\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$dialog}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $files = $detector->scribe()->rewrite($detector->find(Codebase::fromString($sfc)));
        $created = $this->components($files);

        $this->assertNotEmpty($created, 'the dialog should be extracted');
        $component = reset($created);

        $this->assertStringContainsString("@click=\"\$emit('copyJson', 'nodes')\"", $component, 'the handler call becomes an emit');
        $this->assertStringContainsString('defineEmits<{ copyJson: [unknown] }>();', $component, 'the event is declared');

        $this->assertStringContainsString('@copy-json="copyJson"', $files['component.vue'], 'the call site re-binds the event to the parent function');
    }

    public function test_a_handler_calling_the_components_own_emit_refuses_extraction(): void
    {
        // A handler that calls the component's OWN `defineEmits` binding — `@click="emit('close')"`
        // — is already emitting an event, not calling a forwardable parent function. It must NOT
        // be rewritten to `$emit('emit', 'close')` (an event literally named `emit`); the
        // extraction refuses instead of minting garbage.
        $dialog = '<Dialog><DialogContent><DialogHeader>'
            . '<DialogTitle>Confirm</DialogTitle><DialogDescription>{{ blurb }}</DialogDescription></DialogHeader>'
            . '<div class="body"><p>One</p><p>Two</p><ul><li>a</li><li>b</li></ul></div>'
            . '<DialogFooter><Button @click="emit(\'close\')">Cancel</Button></DialogFooter></DialogContent></Dialog>';
        $sfc = "<script setup lang=\"ts\">\nimport { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/ui/dialog';\nconst emit = defineEmits<{ close: [] }>();\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$dialog}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $files = $detector->scribe()->rewrite($detector->find(Codebase::fromString($sfc)));

        $this->assertEmpty($this->components($files), "calling the component's own emit must refuse the extraction");
        foreach ($files as $content) {
            $this->assertStringNotContainsString("\$emit('emit'", $content, 'never mint an event named `emit`');
        }
    }

    public function test_a_parent_function_reached_outside_a_clean_handler_refuses_extraction(): void
    {
        // A parent function used in an interpolation (not a forwardable handler) can't be emitted
        // up — `{{ formatBlurb() }}` would dangle as undefined in the child. The extraction must
        // refuse rather than produce a broken component.
        $dialog = '<Dialog><DialogContent><DialogHeader>'
            . '<DialogTitle>Export</DialogTitle><DialogDescription>{{ formatBlurb() }}</DialogDescription></DialogHeader>'
            . '<div class="body"><p>One</p><p>Two</p><ul><li>a</li><li>b</li></ul></div>'
            . '<DialogFooter><Button>OK</Button></DialogFooter></DialogContent></Dialog>';
        $sfc = "<script setup lang=\"ts\">\nimport { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/ui/dialog';\nfunction formatBlurb() { return 'x'; }\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$dialog}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $files = $detector->scribe()->rewrite($detector->find(Codebase::fromString($sfc)));

        $this->assertEmpty($this->components($files), 'an un-forwardable parent reach must refuse the extraction');
    }

    public function test_a_multi_loop_container_is_not_named_after_one_of_its_loops(): void
    {
        // A dialog containing TWO v-fors is a section/dialog, not "a list" — naming it
        // {firstLoopVar}List is wrong (and risks colliding with a child it renders). It
        // must fall through to a structural name.
        $dialog = '<Dialog><DialogContent><DialogHeader>'
            . '<DialogTitle>{{ heading }}</DialogTitle>'
            . '<DialogDescription>{{ blurb }}</DialogDescription></DialogHeader>'
            . '<ul><li v-for="type in selected.types" :key="type.id">{{ type.name }}</li></ul>'
            . '<ul><li v-for="field in selected.fields" :key="field.id">{{ field.label }}</li></ul>'
            . '<footer><Button>Save</Button><Button>Cancel</Button></footer></DialogContent></Dialog>';
        $sfc = "<script setup lang=\"ts\">\nimport { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/ui/dialog';\n</script>\n"
            . "<template>\n  <div>\n    <button>Open</button>\n    {$dialog}\n  </div>\n</template>\n";

        $detector = new CompoundInlineComponentDetector();
        $files = $detector->scribe()->rewrite($detector->find(Codebase::fromString($sfc)));
        $created = array_keys($this->components($files));

        $this->assertNotEmpty($created);
        foreach ($created as $path) {
            $name = basename($path, '.vue');
            $this->assertStringNotContainsString('TypeList', $name);
            $this->assertStringNotContainsString('FieldList', $name);
        }
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
