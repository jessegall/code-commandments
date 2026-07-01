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
