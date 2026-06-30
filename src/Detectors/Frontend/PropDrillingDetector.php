<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\PropDrilling;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use JesseGall\CodeCommandments\Vue\ModuleResolver;
use JesseGall\CodeCommandments\Vue\Script;
use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * Prop DRILLING — a prop threaded through a component that doesn't use it, on its way to a
 * child that doesn't either. The middle component is a dead pipe: `defineProps<{ user }>()`
 * whose `user` only appears as `<Child :user="user" />`, AND that `Child` ITSELF just forwards
 * it onward. The value passes through ≥2 conduits with no one along the way reading it — the
 * classic case for provide/inject or handing the leaf its data directly. Points at
 * vue-components.
 *
 * A single forward is NOT drilling — `<Button :disabled="disabled" />` is a component composing
 * its own UI, and `<Dialog :open="open" @update:open="…" />` is a controlled v-model proxy.
 * The CHAIN is what separates the sin: this flags a forward only when the child component
 * resolves (through the {@see ModuleResolver}) to one that is ALSO a conduit for the same prop.
 * A leaf / library child can't be confirmed a pipe, so composition is left alone — calibration
 * showed the single-component "forwarded + unused" signal can't tell drilling from composition,
 * so the graph hop is what makes it sound.
 */
final class PropDrillingDetector implements Detector
{
    /** @var array<string, array<string, list<array{element: ElementMatch, childProp: string}>>> */
    private array $conduitCache = [];

    /** @var array<string, Sfc> */
    private array $byPath = [];

    public function sin(): Sin
    {
        return new PropDrilling();
    }

    public function find(Codebase $components): array
    {
        $this->byPath = [];

        foreach ($components->components() as $component) {
            $this->byPath[self::canonical($component->path)] = $component;
        }

        $findings = [];

        foreach ($components->components() as $component) {
            foreach ($this->conduits($component) as $forwards) {
                foreach ($forwards as $forward) {
                    if ($this->forwardsToAnotherConduit($forward, $component)) {
                        $findings[spl_object_id($forward['element']->node)] = $forward['element'];
                    }
                }
            }
        }

        return array_values($findings);
    }

    /**
     * Whether the child a forward targets resolves to a component that is ALSO a conduit for
     * the same (child-side) prop — i.e. the prop keeps being piped, not consumed.
     *
     * @param  array{element: ElementMatch, childProp: string}  $forward
     */
    private function forwardsToAnotherConduit(array $forward, Sfc $parent): bool
    {
        $child = $this->resolveChild($forward['element']->tag, $parent);

        return $child !== null && isset($this->conduits($child)[$forward['childProp']]);
    }

    /**
     * The CONDUIT props of a component — each prop forwarded BARE to a child component and read
     * nowhere else (not in another binding/interpolation/directive, not as `props.x` in the
     * script). Keyed by prop, each carrying the forward site(s) and the child-side prop name.
     *
     * @return array<string, list<array{element: ElementMatch, childProp: string}>>
     */
    private function conduits(Sfc $component): array
    {
        $key = self::canonical($component->path);

        if (isset($this->conduitCache[$key])) {
            return $this->conduitCache[$key];
        }

        $script = new Script($component->scriptContent());
        $props = $script->propTypes();

        if ($props === []) {
            return $this->conduitCache[$key] = [];
        }

        $reads = [];     // prop => count of expressions that read it
        $forwards = [];  // prop => list<array{element, childProp}>

        foreach ($component->elements()->get() as $element) {
            foreach ($element->expressions() as $expression) {
                foreach ($expression->roots() as $root) {
                    if (isset($props[$root])) {
                        $reads[$root] = ($reads[$root] ?? 0) + 1;
                    }
                }
            }

            if (! $element->isComponent()) {
                continue;
            }

            foreach ($element->propBindings() as $childProp => $expression) {
                $chain = $expression->asChain();

                if ($chain !== null && count($chain) === 1 && isset($props[$chain[0]])) {
                    $forwards[$chain[0]][] = ['element' => $element, 'childProp' => $childProp];
                }
            }
        }

        $locals = $script->localNames();
        $propsVar = $script->propsVariable();
        $conduits = [];

        foreach ($forwards as $prop => $sites) {
            if (($reads[$prop] ?? 0) !== count($sites)) {
                continue; // read somewhere other than the forwards — genuinely used
            }

            if (in_array($prop, $locals, true)) {
                continue; // a destructured prop, read by its bare name in script — untrackable
            }

            if ($propsVar !== null && $script->accessesMember($propsVar, $prop)) {
                continue; // read as `props.<prop>` in the script
            }

            $conduits[$prop] = $sites;
        }

        return $this->conduitCache[$key] = $conduits;
    }

    /**
     * The parsed component a tag resolves to — its import, through the {@see ModuleResolver},
     * matched against the scanned codebase. Null for a library/global tag (no import, or a file
     * outside the codebase), so a leaf child can never be mistaken for a conduit.
     */
    private function resolveChild(string $tag, Sfc $parent): ?Sfc
    {
        $specifier = (new Script($parent->scriptContent()))->importSpecifier($tag);

        if ($specifier === null) {
            return null;
        }

        $path = ModuleResolver::forFile($parent->path)->resolve($parent->path, $specifier);

        return $path === null ? null : ($this->byPath[self::canonical($path)] ?? null);
    }

    private static function canonical(string $path): string
    {
        return realpath($path) ?: $path;
    }
}
