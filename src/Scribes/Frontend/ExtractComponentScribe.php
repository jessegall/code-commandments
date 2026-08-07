<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Frontend;

use Closure;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use JesseGall\CodeCommandments\Vue\Attribute;
use JesseGall\CodeCommandments\Vue\Boundary;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ComponentLibrary;
use JesseGall\CodeCommandments\Vue\ComponentReuse;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use JesseGall\CodeCommandments\Vue\Expr\Parser;
use JesseGall\CodeCommandments\Vue\ModuleResolver;
use JesseGall\CodeCommandments\Vue\Oracle\TypeOracle;
use JesseGall\CodeCommandments\Vue\PropTypes;
use JesseGall\CodeCommandments\Vue\Sfc;
use JesseGall\CodeCommandments\Vue\Ts\Parser as TsParser;
use JesseGall\CodeCommandments\Vue\Script;

/**
 * Shared fix for duplicate-element, deep-reach, and nesting detectors: extracts
 * template to component via Boundary, rewrites call site, and imports into the file.
 */
final class ExtractComponentScribe extends RepentScribe
{
    private const string DUPLICATES = 'duplicates';

    private const string DEEP_REACH = 'deep-reach';

    private const string NESTING = 'nesting';

    private const string COMPOUND = 'compound';

    // ---- deep-reach analysis --------------------------------------------------

    /**
     * Generic container words that make a useless component name — a reach through one of these
     * (`data.value.x`) should name the component after a MEANINGFUL segment of its path, not the
     * container itself (`ValueSection`/`DataSection` say nothing about what the section shows).
     */
    private const array UNINFORMATIVE = [
        'value', 'values', 'data', 'item', 'items', 'config', 'state', 'props', 'meta',
        'info', 'detail', 'details', 'result', 'results', 'payload', 'context', 'entry',
    ];

    private ?ComponentLibrary $library = null;

    private ?PropTypes $propTypes = null;

    private ?TypeOracle $oracle = null;

    /**
     * @var array<string, array{sfc: Sfc, names: list<string>}>|null
     */
    private ?array $collector = null;

    /**
     * @var array<string, array<string, string>>
     */
    private array $resolved = [];

    private function __construct(private readonly string $strategy) {}

    /**
     * Hand the scribe the codebase's existing components, so a boundary that an existing
     * component already FITS is reused (imported + referenced) instead of creating a
     * duplicate file. Null disables the check (always create).
     */
    public function withLibrary(?ComponentLibrary $library): self
    {
        $this->library = $library;

        return $this;
    }

    /**
     * Hand the scribe the codebase's top-down prop typing, so a forwarded prop the source
     * can't type LOCALLY is traced up the render tree to its origin instead of falling back
     * to `unknown`. Null disables the cross-component trace (local resolution only).
     */
    public function withPropTypes(?PropTypes $propTypes): self
    {
        $this->propTypes = $propTypes;

        return $this;
    }

    /**
     * Hand the scribe a type oracle (real `vue-tsc`, when the project ships it), so a prop no AST
     * rung could type is resolved by a real checker instead of falling to `unknown`. Absent by
     * default — the checker is a bonus, never required.
     */
    public function withOracle(?TypeOracle $oracle): self
    {
        $this->oracle = $oracle;

        return $this;
    }

    /**
     * Reuse an existing component for this boundary if one fits — returns true when it
     * placed a `<Existing … />` reference (and imported it) instead of a new file.
     */
    private function reused(Draft $draft, Boundary $boundary): bool
    {
        $reuse = $this->library?->match($boundary);

        if ($reuse === null) {
            return false;
        }

        $this->placeReuse($draft, $boundary, $reuse);

        return true;
    }

    /**
     * Reference an existing component at a boundary's call site: a `<Existing … />` (with
     * carried directives + bindings) plus an import, unless the file already has it.
     */
    private function placeReuse(Draft $draft, Boundary $boundary, ComponentReuse $reuse): void
    {
        $draft->edit($boundary->contentSpan(), self::usage($reuse->name, $reuse->bindings, $boundary->carried()));

        if (! self::mentions($boundary->sfc->scriptContent(), $reuse->name)) {
            self::import($draft, $boundary->sfc, $reuse->path, $reuse->name);
        }
    }

    public static function forDuplicates(): self
    {
        return new self(self::DUPLICATES);
    }

    public static function forDeepReach(): self
    {
        return new self(self::DEEP_REACH);
    }

    public static function forNesting(): self
    {
        return new self(self::NESTING);
    }

    public static function forCompound(): self
    {
        return new self(self::COMPOUND);
    }

    /**
     * @param  list<ElementMatch>  $findings
     */
    public function rewrite(array $findings): array
    {
        $findings = $this->outermost($findings);

        if ($this->oracle !== null) {
            $this->prime($findings);
        }

        return $this->dispatch($findings);
    }

    /**
     * @param  list<ElementMatch>  $findings
     */
    private function dispatch(array $findings): array
    {
        return match ($this->strategy) {
            self::DUPLICATES => $this->duplicates($findings),
            self::DEEP_REACH => $this->deepReach($findings),
            self::COMPOUND => $this->compound($findings),
            default => $this->nesting($findings),
        };
    }

    /**
     * ONE checker pass for the whole run: a dry render collects every prop the AST left `unknown`
     * (grouped by component), the oracle resolves them ALL at once, and the resolved types are
     * cached for the real render that follows. A throwaway AST render is far cheaper than a checker
     * run per component — so the checker is invoked exactly once, not N times.
     *
     * @param  list<ElementMatch>  $findings
     */
    private function prime(array $findings): void
    {
        // The dry render must NOT leak into the real pass: its create()s call library->register(),
        // and the real dispatch would then "reuse" those phantom components — emitting an import +
        // call-site rewrite to a component file the real pass never creates (a dangling import that
        // guts the source and breaks the build). So it runs on a THROWAWAY scribe holding a COPY of
        // the library: everything the dry pass records or registers is discarded with it, and this
        // scribe is never in a dry state for a moment — including when the render throws.
        $dry = clone $this;
        $dry->collector = [];
        $dry->library = $this->library !== null ? clone $this->library : null;

        $dry->dispatch($findings); // resolveTypes records each component's unknowns via consultOracle

        $this->resolved = $this->oracle?->resolveAll($dry->collector ?? []) ?? [];
    }

    // ---- strategies -----------------------------------------------------------

    /**
     * @param  list<ElementMatch>  $findings
     */
    private function duplicates(array $findings): array
    {
        $draft = $this->draft([]);
        $used = [];

        foreach ($this->groups($findings) as $members) {
            $boundary = Boundary::for($members[0]);

            if (! $boundary->extractable()) {
                continue;
            }

            if (($reuse = $this->library?->match($boundary)) !== null) {
                foreach ($members as $occurrence) {
                    $this->placeReuse($draft, Boundary::for($occurrence), $reuse);
                }

                continue;
            }

            $props = self::withLoopVars($boundary, $boundary->props());
            [$props, $statics] = self::liftStatics($boundary, $props);
            $name = self::unique(dirname($members[0]->file()), $boundary->name(), $used);
            $component = $members[0]->sibling("{$name}.vue");

            if (! $this->create($draft, $boundary, $component, $this->render($boundary, $props, $boundary->markup(), [], '', $statics))) {
                continue;
            }

            foreach ($members as $occurrence) {
                $this->place($draft, Boundary::for($occurrence), $component, $name, self::selfBindings($boundary, $props));
            }
        }

        return $draft->rewrites();
    }

    /**
     * @param  list<ElementMatch>  $findings
     */
    private function nesting(array $findings): array
    {
        $draft = $this->draft([]);
        $used = [];

        foreach ($findings as $finding) {
            $boundary = Boundary::for($finding);

            if (! $boundary->extractable()) {
                continue;
            }

            if ($this->reused($draft, $boundary)) {
                continue;
            }

            $props = self::withLoopVars($boundary, $boundary->props());
            [$props, $statics] = self::liftStatics($boundary, $props);
            $name = self::unique(dirname($finding->file()), $boundary->name(), $used);
            $component = $finding->sibling("{$name}.vue");

            if ($this->create($draft, $boundary, $component, $this->render($boundary, $props, $boundary->markup(), [], '', $statics))) {
                $this->place($draft, $boundary, $component, $name, self::selfBindings($boundary, $props));
            }
        }

        return $draft->rewrites();
    }

    /**
     * Lift a compound primitive (`<Dialog>…`) into its own component, named for what it
     * does — the same mechanics as {@see nesting}, with a {@see compoundName}.
     *
     * @param  list<ElementMatch>  $findings
     */
    private function compound(array $findings): array
    {
        $draft = $this->draft([]);
        $used = [];

        foreach ($findings as $finding) {
            $boundary = Boundary::for($finding);

            if (! $boundary->extractable()) {
                continue;
            }

            if ($this->reused($draft, $boundary)) {
                continue;
            }

            $props = self::withLoopVars($boundary, $boundary->props());
            [$props, $statics] = self::liftStatics($boundary, $props);
            $name = self::unique(dirname($finding->file()), self::compoundName($boundary), $used);
            $component = $finding->sibling("{$name}.vue");

            if ($this->create($draft, $boundary, $component, $this->render($boundary, $props, $boundary->markup(), [], '', $statics))) {
                $this->place($draft, $boundary, $component, $name, self::selfBindings($boundary, $props));
            }
        }

        return $draft->rewrites();
    }

    /**
     * Name a compound for what it DOES: its title part's text plus the family tag —
     * `<DialogTitle>Pair Reader</DialogTitle>` in a `<Dialog>` → `PairReaderDialog`. Falls
     * back to the boundary's own intelligent name when there's no title.
     */
    private static function compoundName(Boundary $boundary): string
    {
        $title = self::compoundTitle($boundary->node);

        if ($title === '') {
            return $boundary->name();
        }

        $family = $boundary->node->tag;
        $base = self::pascal($title);

        return str_ends_with($base, $family) ? $base : $base . $family;
    }

    /**
     * The STATIC text of the compound's title part — a descendant component whose tag ends
     * in `Title` (`DialogTitle`, `CardTitle`). A dynamic title (`{{ … }}` interpolation) is
     * not a usable name — pascal-casing a binding expression yields a monster — so it's
     * ignored and the compound falls back to its structural name.
     */
    private static function compoundTitle(Element $node): string
    {
        foreach ($node->descendants() as $element) {
            if (! str_ends_with($element->tag, 'Title')) {
                continue;
            }

            foreach ($element->children as $child) {
                if ($child->isStaticText()) {
                    return trim($child->text);
                }
            }
        }

        return '';
    }

    /**
     * Words → PascalCase, identifier characters only (no regex — the scribe stays in the
     * no-regex layer).
     */
    private static function pascal(string $text): string
    {
        $out = '';
        $word = '';

        for ($i = 0; $i <= strlen($text); $i++) {
            $char = $text[$i] ?? '';

            if ($char !== '' && ctype_alnum($char)) {
                $word .= $char;
            } elseif ($word !== '') {
                $out .= ucfirst(strtolower($word));
                $word = '';
            }
        }

        return $out;
    }

    /**
     * @param  list<ElementMatch>  $findings
     */
    private function deepReach(array $findings): array
    {
        $draft = $this->draft([]);
        $used = [];

        foreach ($findings as $finding) {
            $boundary = Boundary::for($finding);

            if (! $boundary->extractable()) {
                continue;
            }

            if ($this->reused($draft, $boundary)) {
                continue;
            }

            [$prefix, $prop] = self::midObject($finding);
            $props = self::withLoopVars($boundary, self::reachProps($boundary, $finding, $prefix, $prop));
            [$props, $statics] = self::liftStatics($boundary, $props);
            $name = self::unique(dirname($finding->file()), self::reachName($prefix, $prop, $boundary), $used);
            $component = $finding->sibling("{$name}.vue");

            $markup = $prefix === []
                ? $boundary->markup()
                : str_replace(implode('.', $prefix), $prop, $boundary->markup());

            if ($this->create($draft, $boundary, $component, $this->render($boundary, $props, $markup, $prefix, $prop, $statics))) {
                $this->place($draft, $boundary, $component, $name, self::reachBindings($props, $prefix, $prop));
            }
        }

        return $draft->rewrites();
    }

    /**
     * Findings grouped by structure — duplicates of one block share a group.
     *
     * @param  list<ElementMatch>  $findings
     * @return list<list<ElementMatch>>
     */
    private function groups(array $findings): array
    {
        $byShape = [];

        foreach ($findings as $match) {
            $byShape[$match->structureHash()][] = $match;
        }

        return array_values($byShape);
    }

    // ---- the call site --------------------------------------------------------

    /**
     * Draft a new component file AND teach the library about it — so a later finding in this
     * same run reuses it instead of creating an identical sibling (the `Foo`/`Foo2` bug). The
     * library isn't refreshed from disk mid-run, so the scribe registers what it just made.
     *
     * Returns false WITHOUT drafting a self-referential extraction — one whose component file
     * would BE its own source (a boundary in `UserSection.vue` named `UserSection`), or whose
     * template renders a tag of its OWN name (`TypeList` containing `<TypeList …>`). Either
     * makes a file that imports/renders itself, never a clean extraction, so the whole finding
     * is skipped (the detector still flags the smell). The caller skips the call-site rewrite
     * when this is false.
     */
    private function create(Draft $draft, Boundary $boundary, string $path, string $content): bool
    {
        // Self-referential — its file IS its own source, or its element TREE renders its own
        // name (queried over the AST, never scanned out of the rendered string).
        if ($path === $boundary->sfc->path || $boundary->node->renders(basename($path, '.vue'))) {
            return false;
        }

        $draft->add($path, $content);
        $this->library?->register($path, $content);

        return true;
    }

    /**
     * Rewrite a boundary's call site to use the component, and import it into the file.
     *
     * @param  array<string, string>  $bindings
     */
    private function place(Draft $draft, Boundary $boundary, string $component, string $name, array $bindings): void
    {
        $usage = self::usage($name, $bindings, $boundary->carried(), $boundary->models(), $boundary->hasSlots(), $boundary->contentSpan()->column(), array_keys($boundary->emitEvents()));
        $draft->edit($boundary->contentSpan(), $usage);
        self::import($draft, $boundary->sfc, $component, $name);
    }

    /**
     * The `<Component v-if … :prop="binding" … />` that replaces the lifted chunk —
     * carrying the structural directive (so a conditional/list keeps working) and
     * passing each prop.
     *
     * @param  array<string, string>  $bindings  prop name => the expression to pass
     * @param  list<Attribute>  $carried  the structural directives to keep here
     * @param  list<string>  $models  props the child WRITES — bound with `v-model`, not `:`
     * @param  bool  $forwardsSlots  the chunk renders `<slot>`s — forward the host's slots
     * @param  int  $column  the call site's indentation, for the slot-forwarding block
     * @param  list<string>  $emits  events the child emits — the parent re-binds each to its own function
     */
    private static function usage(string $name, array $bindings, array $carried = [], array $models = [], bool $forwardsSlots = false, int $column = 0, array $emits = []): string
    {
        $attributes = array_map(static fn (Attribute $carry): string => $carry->render(), $carried);

        foreach ($bindings as $prop => $expression) {
            $attributes[] = in_array($prop, $models, true)
                ? 'v-model:' . self::kebab($prop) . "=\"{$expression}\""
                : ':' . self::kebab($prop) . "=\"{$expression}\"";
        }

        // A handler call that became an `$emit` in the child is re-bound to the original
        // parent function here (`@copy-json="copyJson"`), closing the loop.
        foreach ($emits as $event) {
            $attributes[] = '@' . self::kebab($event) . "=\"{$event}\"";
        }

        $open = $attributes === [] ? "<{$name}" : "<{$name} " . implode(' ', $attributes);

        if (! $forwardsSlots) {
            return "{$open} />";
        }

        // The chunk consumes slots — pass the host's slots through transparently, so a named
        // slot the host fills still reaches the extracted component's `<slot>` (issue: slots
        // were dropped when the call site was self-closing).
        $indent = str_repeat(' ', $column);

        return "{$open}>\n"
            . "{$indent}    <template v-for=\"(_, name) in \$slots\" :key=\"name\" #[name]=\"slotProps\">\n"
            . "{$indent}        <slot :name=\"name\" v-bind=\"slotProps\" />\n"
            . "{$indent}    </template>\n"
            . "{$indent}</{$name}>";
    }

    /**
     * A camelCase prop as a kebab-case template attribute (`rateLimit` → `rate-limit`).
     *
     * A hyphen is inserted ONLY at a genuine camelCase hump — a lowercase (or digit) followed by
     * an uppercase — because that is the exact boundary Vue's `camelize` reverses. An acronym or
     * all-caps run (`INLINE`, `XMLData`) has no such hump, so it is emitted VERBATIM: Vue matches a
     * prop by its exact name before camelizing, so `:INLINE` binds `INLINE`, whereas a naive
     * per-uppercase kebab (`i-n-l-i-n-e`) would camelize back to `iNLINE` and never resolve.
     */
    private static function kebab(string $name): string
    {
        $out = '';

        for ($i = 0; $i < strlen($name); $i++) {
            $char = $name[$i];
            $prev = $i > 0 ? $name[$i - 1] : '';

            if (ctype_upper($char) && ($prev !== '' && ! ctype_upper($prev) && $prev !== '-')) {
                $out .= '-' . strtolower($char);
            } else {
                $out .= $char;
            }
        }

        return $out;
    }

    /**
     * The component's props plus, when its own `v-for` is carried out to the call site,
     * the loop variables it now receives instead of binding.
     *
     * @param  list<string>  $props
     * @return list<string>
     */
    private static function withLoopVars(Boundary $boundary, array $props): array
    {
        if (! $boundary->hasCarriedLoop()) {
            return $props;
        }

        return array_values(array_unique([...$props, ...$boundary->ownLoopVars()]));
    }

    /**
     * Import the freshly-created component into the file it was lifted out of — spliced
     * at the top of `<script setup>` (or a fresh one when the file has no script block).
     */
    private static function import(Draft $draft, Sfc $sfc, string $component, string $name): void
    {
        $statement = "import {$name} from '" . self::relativeImport($sfc->path, $component) . "';\n";
        // Two different edits, not one edit with a hole in it: a file that HAS a script block gets
        // the import spliced just inside it; a file with none gets a whole `<script setup>` minted
        // at the very top.
        foreach ($sfc->scriptContentStart() as $at) {
            $draft->edit(new Span($sfc->path, $sfc->source, $at, $at), "\n{$statement}");

            return;
        }

        $draft->edit(
            new Span($sfc->path, $sfc->source, 0, 0),
            "<script setup lang=\"ts\">\n{$statement}</script>\n\n",
        );
    }

    /**
     * A relative ES import specifier from one file to another (`./Foo.vue`, `../ui/Foo.vue`).
     */
    private static function relativeImport(string $from, string $to): string
    {
        if (dirname($from) === dirname($to)) {
            return './' . basename($to);
        }

        $fromDir = array_values(array_filter(explode('/', dirname($from)), static fn (string $p): bool => $p !== '' && $p !== '.'));
        $toDir = array_values(array_filter(explode('/', dirname($to)), static fn (string $p): bool => $p !== '' && $p !== '.'));

        $shared = 0;

        while (isset($fromDir[$shared], $toDir[$shared]) && $fromDir[$shared] === $toDir[$shared]) {
            $shared++;
        }

        $up = str_repeat('../', count($fromDir) - $shared) ?: './';
        $down = implode('/', array_slice($toDir, $shared));

        return $up . ($down !== '' ? "{$down}/" : '') . basename($to);
    }

    /**
     * @param  list<string>  $props
     * @return array<string, string>
     *
     * @param  list<string>  $props
     * @return array<string, string>  prop => the parent-scope expression to bind it to
     */
    private static function selfBindings(Boundary $boundary, array $props): array
    {
        $bindings = [];

        foreach ($props as $prop) {
            $bindings[$prop] = $boundary->callSiteExpression($prop);
        }

        return $bindings;
    }

    /**
     * Deep-reach bindings: the flattened prop is passed its ORIGINAL path
     * (`:customer="order.customer"`); every other prop is passed by its own name.
     *
     * @param  list<string>  $props
     * @param  list<string>  $prefix
     * @return array<string, string>
     */
    private static function reachBindings(array $props, array $prefix, string $prop): array
    {
        $bindings = [];

        foreach ($props as $name) {
            $bindings[$name] = $name === $prop && $prefix !== [] ? implode('.', $prefix) : $name;
        }

        return $bindings;
    }

    // ---- rendering ------------------------------------------------------------

    /**
     * Split a boundary's free variables into the props it genuinely takes and the module-scope
     * STATIC constants it merely reads (`const INLINE = {…} as const`) — issue #324. A static const
     * is compile-time data, not per-render input: threading it as a prop mangles the call site (a
     * fallthrough attribute) and forces a meaningless `unknown`-typed slot. It is COPIED into the
     * child instead. Returns `[propsWithoutStatics, name => declarationSource]`.
     *
     * @param  list<string>  $props
     * @return array{0: list<string>, 1: array<string, string>}
     */
    private static function liftStatics(Boundary $boundary, array $props): array
    {
        $script = new Script($boundary->sfc->scriptContent());
        $statics = [];

        foreach ($props as $prop) {
            if (($decl = $script->staticConst($prop)) !== null) {
                $statics[$prop] = $decl;
            }
        }

        return [array_values(array_diff($props, array_keys($statics))), $statics];
    }

    /**
     * The component file: its `<script setup>` (the imports the markup/props actually
     * use, carried from the source, plus typed props) and the lifted `<template>`.
     *
     * @param  list<string>  $props
     * @param  array<string, string>  $statics  module-local static consts to copy in ({@see liftStatics})
     */
    private function render(Boundary $boundary, array $props, string $markup, array $prefix = [], string $reachProp = '', array $statics = []): string
    {
        $script = new Script($boundary->sfc->scriptContent());

        // The props-variable's members are forwarded as bare props ({@see Boundary::props}); strip the
        // `props.` prefix from the lifted markup to match, so `props.label` reads the `label` prop.
        if (($propsVariable = $script->propsVariable()) !== null) {
            $markup = str_replace("{$propsVariable}.", '', $markup);
        }

        $types = $this->resolveTypes($boundary, $props, $script, $prefix, $reachProp);

        // A prop the chunk WRITES (v-model) is two-way state — a defineModel, not a prop;
        // Vue forbids writing a plain prop, so it must be split out.
        $models = array_values(array_intersect($props, $boundary->models()));
        $readProps = array_values(array_diff($props, $models));

        $defineModels = implode('', array_map(static fn (string $m): string => "const {$m} = defineModel<{$types[$m]}>('{$m}');\n", $models));
        $defineProps = $readProps === []
            ? ''
            : 'defineProps<{ ' . implode('; ', array_map(static fn (string $p): string => "{$p}: {$types[$p]}", $readProps)) . " }>();\n";

        // Handler calls to a parent function are forwarded as emits ({@see Boundary::emits}); the
        // child declares them so `$emit` in the lifted markup is typed.
        $defineEmits = self::defineEmits($boundary->emitEvents());

        // Module-local static consts the markup reads are copied in verbatim, not taken as props.
        $carriedConsts = $statics === [] ? '' : implode("\n", $statics) . "\n";

        $scriptSetup = $carriedConsts . $defineModels . $defineProps . $defineEmits;
        $imports = trim(self::usedImports($script, $markup . "\n" . $scriptSetup) . "\n" . self::syntheticTypeImports($script, $types));
        $head = $imports === '' ? '' : "{$imports}\n\n";
        $carried = self::carriedTypes($script, $types);

        return "<script setup lang=\"ts\">\n{$head}{$carried}{$scriptSetup}</script>\n\n<template>\n{$markup}\n</template>\n";
    }

    /**
     * The parent-LOCAL type declarations the child's prop types depend on, rendered to carry along —
     * a prop typed `EditableItem[]` needs the parent's `interface EditableItem` copied in, or the
     * child won't compile (`Cannot find name 'EditableItem'`). Imported types travel via the
     * carried imports instead; only names the parent declares locally are emitted here.
     *
     * @param  array<string, string>  $types  prop name => its rendered TS type
     */
    private static function carriedTypes(Script $script, array $types): string
    {
        $referenced = [];

        foreach ($types as $type) {
            $referenced = [...$referenced, ...TsParser::type($type)->references()];
        }

        $local = $script->localTypes($referenced);

        return $local === [] ? '' : implode("\n\n", $local) . "\n\n";
    }

    /**
     * The `defineEmits<{ … }>()` declaration for the events the child forwards, each typed by
     * its arity — `['copyJson' => 1]` → `defineEmits<{ copyJson: [unknown] }>();`. The lifted
     * markup calls the built-in `$emit`, so no binding is captured; this only types the events.
     *
     * @param  array<string, int>  $events
     */
    private static function defineEmits(array $events): string
    {
        if ($events === []) {
            return '';
        }

        $signatures = [];

        foreach ($events as $name => $arity) {
            $signatures[] = "{$name}: [" . implode(', ', array_fill(0, $arity, 'unknown')) . ']';
        }

        return 'defineEmits<{ ' . implode('; ', $signatures) . " }>();\n";
    }

    /**
     * A real TS type for each prop, from the source's declared prop types: a loop
     * variable gets its iterable's ELEMENT type, the deep-reach mid-object an indexed
     * type (`Order['customer']`), a forwarded prop its own type — else `unknown`.
     *
     * @param  list<string>  $props
     * @param  list<string>  $prefix
     * @return array<string, string>
     */
    private function resolveTypes(Boundary $boundary, array $props, Script $script, array $prefix, string $reachProp): array
    {
        $source = $script->propTypes();
        $types = [];

        foreach ($props as $prop) {
            $iterable = $boundary->iterableOf($prop);

            if ($prop === $reachProp && $prefix !== []) {
                $types[$prop] = self::accessType($prefix, $source, $script);
            } elseif ($iterable !== null && ($segments = self::segments($iterable)) !== null) {
                $types[$prop] = self::elementType(self::accessType($segments, $source, $script));
            } else {
                // A template binding auto-unwraps a top-level `Ref`/`ComputedRef` to its value, so the
                // prop takes the unwrapped type — never `Ref<…>` threaded through (issue #320).
                $types[$prop] = Script::unwrapRef($source[$prop]
                    ?? $script->declaredType($prop)
                    ?? self::tracedType($boundary, $script, $prop)
                    ?? self::inertiaFormType($script, $prop) // a local `const x = useForm({…})`
                    ?? $this->propTypes?->typeOf($boundary->sfc, $prop) // trace up the render tree
                    ?? 'unknown');
            }
        }

        return $this->consultOracle($boundary->sfc, $types);
    }

    /**
     * The oracle's touch-point in the type chain. During {@see prime}'s dry render it RECORDS each
     * component's still-`unknown` props (for the single checker pass); during the real render it
     * SERVES the types that pass resolved. A no-op when no oracle is set, so behaviour is unchanged
     * unless the project ships a real checker.
     *
     * @param  array<string, string>  $types
     * @return array<string, string>
     */
    private function consultOracle(Sfc $component, array $types): array
    {
        $unknown = array_keys(array_filter($types, static fn (string $type): bool => $type === 'unknown'));

        if ($unknown === []) {
            return $types;
        }

        if ($this->collector !== null) {
            $seen = $this->collector[$component->path]['names'] ?? [];
            $this->collector[$component->path] = [
                'sfc' => $component,
                'names' => array_values(array_unique([...$seen, ...$unknown])),
            ];

            return $types;
        }

        foreach ($unknown as $name) {
            if (isset($this->resolved[$component->path][$name])) {
                // The checker reads the local's `<script>`-scope type; a template binding unwraps a
                // `Ref`/`ComputedRef` to its value, so the prop takes the unwrapped type (as every
                // AST rung already does).
                $types[$name] = Script::unwrapRef($this->resolved[$component->path][$name]);
            }
        }

        return $types;
    }

    /**
     * The type of a local bound to an Inertia `useForm({…})` — `const form = useForm({ name: '',
     * qty: 0 })` → `InertiaForm<{ name: string; qty: number }>`, the field shape inferred from the
     * seed object. This is the one composable the static passes can't trace (its type lives in
     * `node_modules`), yet an extracted `v-model="form"` typed `unknown` breaks every `form.x` access
     * — so it's synthesised from the call. Gated to `useForm` imported from `@inertiajs/*`; the
     * `InertiaForm` type import is added by {@see syntheticTypeImports}. Null for anything else, so
     * no other factory is guessed at.
     */
    private static function inertiaFormType(Script $script, string $prop): ?string
    {
        $init = $script->declaratorValue($prop);
        $source = $script->importSpecifier('useForm');

        if ($init === null || ! str_starts_with(ltrim($init), 'useForm') || $source === null || ! str_contains($source, '@inertiajs')) {
            return null;
        }

        // Prefer the author's own type argument — `useForm<ProductForm>(…)` → `InertiaForm<ProductForm>`
        // — since it's more precise than a re-inferred shape (and carried in by usedImports/carriedTypes).
        $generic = self::genericArgument(ltrim($init));

        if ($generic !== null) {
            return "InertiaForm<{$generic}>";
        }

        // No explicit type — infer the field shape from the seed object `useForm({ … })`.
        $shape = Parser::parse($init)->callee() === 'useForm' ? Parser::parse($init)->argument(0)?->objectShape() : null;

        return $shape === null ? null : "InertiaForm<{$shape}>";
    }

    /**
     * The explicit type argument of a leading `useForm<…>(…)` call, read as the balanced `<…>` right
     * after the callee (so `useForm<Foo<Bar>>(…)` yields `Foo<Bar>`) — or null when there is none. A
     * bounded delimiter scan: the template expression parser can't lex a TS generic, so this reads it
     * directly rather than guessing.
     */
    private static function genericArgument(string $init): ?string
    {
        $rest = ltrim(substr($init, strlen('useForm')));

        if (($rest[0] ?? '') !== '<') {
            return null;
        }

        $depth = 0;

        for ($i = 0, $length = strlen($rest); $i < $length; $i++) {
            $depth += match (true) {
                $rest[$i] === '<' => 1,
                $rest[$i] === '>' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return trim(substr($rest, 1, $i - 1)) ?: null;
            }
        }

        return null;
    }

    /**
     * Import statements for types this component SYNTHESISED (not carried from the source) — currently
     * Inertia's `InertiaForm`, pulled from the same module the source imported `useForm` from, so a
     * `defineModel<InertiaForm<…>>` compiles. Empty when no synthesised type needs an import.
     *
     * @param  array<string, string>  $types
     */
    private static function syntheticTypeImports(Script $script, array $types): string
    {
        foreach ($types as $type) {
            if (! str_contains($type, 'InertiaForm<')) {
                continue;
            }

            $source = $script->importSpecifier('useForm') ?? '@inertiajs/vue3';

            return "import type { InertiaForm } from '{$source}';";
        }

        return '';
    }

    /**
     * A prop traced THROUGH a composable: `const { step } = useWizardState(…)` →
     * `useWizardState`'s declared return interface → its `step` field, ref-unwrapped. The
     * binding's import is resolved to the composable's file, which is parsed and read. Null
     * when the chain breaks (not destructured, an unresolved/aliased import, an inferred
     * return) — only a real type checker could close those, and we never guess.
     */
    private static function tracedType(Boundary $boundary, Script $script, string $prop): ?string
    {
        $callee = $script->destructuredCall($prop);

        if ($callee === null) {
            return null;
        }

        $specifier = $script->importSpecifier($callee);
        $module = $specifier === null ? $script : self::loadModule($boundary->sfc->path, $specifier);

        if ($module === null) {
            return null;
        }

        $interface = $module->returnTypeName($callee);

        // Declared return → its field; inferred return → the field typed from the composable's own
        // `return { … }` (a `ref`'s value, a `function`'s signature), the way vue-tsc infers it.
        return $interface !== null
            ? $module->fieldType($interface, $prop)
            : ($module->inferredReturnFields($callee)[$prop] ?? null);
    }

    /**
     * The {@see Script} of a module specifier resolved against the importing file — relative,
     * aliased (`@app/composables/useX`), or a barrel `index.*`, via the {@see ModuleResolver}
     * (project aliases discovered from the Vite config). A `.vue` module contributes its
     * `<script>`. Null for a bare/`node_modules` specifier or a file that isn't there.
     */
    private static function loadModule(string $fromFile, string $specifier): ?Script
    {
        $path = ModuleResolver::forFile($fromFile)->resolve($fromFile, $specifier);

        if ($path === null) {
            return null;
        }

        $source = (string) file_get_contents($path);

        return new Script(str_ends_with($path, '.vue') ? Codebase::fromString($source, $path)->components()[0]->scriptContent() : $source);
    }

    /**
     * The type of a member-access path off its root: `[order, customer]` → `Order['customer']`,
     * where the root's type is a declared prop OR a traced local. `unknown` if the root
     * can't be typed.
     *
     * @param  list<string>  $segments
     * @param  array<string, string>  $source
     */
    private static function accessType(array $segments, array $source, Script $script): string
    {
        $type = $source[$segments[0]] ?? $script->declaredType($segments[0]);

        if ($type === null) {
            return 'unknown';
        }

        foreach (array_slice($segments, 1) as $segment) {
            $type = "{$type}['{$segment}']";
        }

        return $type;
    }

    /**
     * The element type of an iterable type: `Agent[]` → `Agent`; otherwise an indexed
     * access (`Group['charts']` → `Group['charts'][number]`).
     */
    private static function elementType(string $type): string
    {
        if ($type === 'unknown') {
            return 'unknown';
        }

        return str_ends_with($type, '[]') ? substr($type, 0, -2) : "{$type}[number]";
    }

    /**
     * A member-access expression as its segments, or null if it isn't a pure chain
     * (a call / index makes the element type unresolvable here).
     *
     * @return list<string>|null
     */
    private static function segments(string $expression): ?array
    {
        return Parser::parse($expression)->asChain();
    }

    /**
     * The source's import statements whose bound name the component actually uses (in
     * the markup or a prop type) — so it brings its children, helpers and types with
     * it; the consumer apps don't auto-import.
     */
    private static function usedImports(Script $script, string $used): string
    {
        $kept = [];

        foreach ($script->imports() as $import) {
            foreach ($import['names'] as $name) {
                if (! self::mentions($used, $name)) {
                    continue;
                }

                $kept[] = $import['statement'];

                break;
            }
        }

        return implode("\n", $kept);
    }

    /**
     * Does $name appear in $text as a whole word? Asked of the identifier TOKENS lexed out of
     * $text — a set membership, not a scan-for-$name (so `Order` doesn't match `OrderItem`).
     */
    private static function mentions(string $text, string $name): bool
    {
        return in_array($name, self::identifiers($text), true);
    }

    /**
     * Every identifier-like token in $text — a tiny lexer (the only char scanning allowed),
     * so "does this mention X" becomes membership instead of hunting for X.
     *
     * @return list<string>
     */
    private static function identifiers(string $text): array
    {
        $tokens = [];
        $current = '';

        for ($i = 0, $length = strlen($text); $i < $length; $i++) {
            if (self::isIdentifierChar($text[$i])) {
                $current .= $text[$i];

                continue;
            }

            if ($current !== '') {
                $tokens[] = $current;
                $current = '';
            }
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '$';
    }

    /**
     * A component name not yet used IN THAT DIRECTORY — two `MetricSection`s in
     * different folders are both fine (different files); only same-folder siblings
     * collide and get suffixed.
     *
     * @param  array<string, true>  $used
     */
    private static function unique(string $dir, string $name, array &$used): string
    {
        $candidate = $name;
        $n = 2;

        while (isset($used["{$dir}\0{$candidate}"])) {
            $candidate = $name . $n++;
        }

        $used["{$dir}\0{$candidate}"] = true;

        return $candidate;
    }

    /**
     * The component name for a deep-reach extraction — `<Segment>Section` where `Segment` is the
     * rightmost INFORMATIVE segment of the reach path (issue #315): the mid-object prop when it is
     * meaningful, else an earlier segment (a reach `spawnFailure.value.detail` names `SpawnFailureSection`,
     * never `ValueSection`), else the boundary's own structural name.
     *
     * @param  list<string>  $prefix  the reach path down to the mid-object
     */
    private static function reachName(array $prefix, string $prop, Boundary $boundary): string
    {
        if ($prop === '') {
            return $boundary->name();
        }

        foreach (array_reverse($prefix) as $segment) {
            if (! in_array(strtolower($segment), self::UNINFORMATIVE, true)) {
                return ucfirst($segment) . 'Section';
            }
        }

        return $boundary->name();
    }

    /**
     * The shared object a deep-reach cluster takes as a prop: the common prefix of its
     * deep chains, stopped one short of the leaf. `order.customer.name` +
     * `order.customer.email` → prefix `['order','customer']`, prop `customer`.
     *
     * @return array{0: list<string>, 1: string}  [prefix, prop]
     */
    private static function midObject(ElementMatch $block): array
    {
        $deep = array_filter(self::chains($block), static fn (array $chain): bool => count($chain) >= 3);

        if ($deep === []) {
            return [[], ''];
        }

        $prefix = array_shift($deep);
        $shortest = count($prefix);

        foreach ($deep as $chain) {
            $prefix = self::commonPrefix($prefix, $chain);
            $shortest = min($shortest, count($chain));
        }

        $mid = array_slice($prefix, 0, min(count($prefix), $shortest - 1));

        return [$mid, $mid[count($mid) - 1] ?? ''];
    }

    /**
     * The props a deep-reach component needs: the boundary's free variables, with the
     * flattened object's root dropped ONLY when every reach through it went via the
     * mid-object (so `$prop` fully replaces it), and `$prop` itself added.
     *
     * @param  list<string>  $prefix
     * @return list<string>
     */
    private static function reachProps(Boundary $boundary, ElementMatch $block, array $prefix, string $prop): array
    {
        $free = $boundary->props();

        if ($prefix === []) {
            return $free;
        }

        $root = $prefix[0];
        $rootReadElsewhere = false;

        foreach (self::chains($block) as $chain) {
            if (! (($chain[0] ?? null) === $root && array_slice($chain, 0, count($prefix)) !== $prefix)) {
                continue;
            }

            $rootReadElsewhere = true;
            break;
        }

        $props = $rootReadElsewhere ? $free : array_values(array_diff($free, [$root]));

        return in_array($prop, $props, true) ? $props : [...$props, $prop];
    }

    /**
     * @return list<list<string>>
     */
    private static function chains(ElementMatch $block): array
    {
        $chains = [];

        self::each($block, static function (Element $element) use (&$chains): void {
            foreach ($element->expressions() as $expression) {
                $chains = array_merge($chains, $expression->chains());
            }
        });

        return $chains;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    private static function commonPrefix(array $a, array $b): array
    {
        $prefix = [];

        foreach ($a as $i => $segment) {
            if (($b[$i] ?? null) !== $segment) {
                break;
            }

            $prefix[] = $segment;
        }

        return $prefix;
    }

    /**
     * @param  Closure(Element): void  $visit
     */
    private static function each(Element $node, Closure $visit): void
    {
        if ($node->isElement()) {
            $visit($node);
        }

        foreach ($node->children as $child) {
            self::each($child, $visit);
        }
    }
}
