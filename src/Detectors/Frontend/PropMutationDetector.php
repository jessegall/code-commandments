<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\PropMutation;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Expr\Parser;
use JesseGall\CodeCommandments\Vue\Script;

/**
 * A component WRITES one of its own props — `v-model="open"` bound to a prop, or an event
 * handler assigning to it (`@click="confirmingClose = true"`). Props are read-only input from
 * the parent: a `v-model` on a prop fails the Vue compiler, and an assignment is a silent
 * no-op (the click "works" but nothing changes). Two-way state belongs in a `defineModel`, or
 * the parent owns it and the child emits an `update:` event. Points at vue-components.
 *
 * Only a BARE prop write is flagged — `v-model="open"` / `prop = …`, where `asChain()` is the
 * single prop name. A name SHADOWED by a local (`const open = useVModel(props, 'open')`, a
 * `defineModel`, a `computed`) is the writable local, not the prop, so it is excluded — the
 * exact false positive the consumers' `useVModel` inputs would otherwise raise.
 */
final class PropMutationDetector implements Detector
{
    public function sin(): Sin
    {
        return new PropMutation();
    }

    public function find(Codebase $components): array
    {
        $findings = [];

        foreach ($components->components() as $component) {
            $script = new Script($component->scriptContent());
            $props = $script->propTypes();

            if ($props === []) {
                continue;
            }

            $locals = $script->localNames();

            foreach ($component->elements()->get() as $element) {
                if (self::writesAProp($element, $props, $locals)) {
                    $findings[] = $element;
                }
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, string>  $props
     * @param  list<string>  $locals
     */
    private static function writesAProp(Element $element, array $props, array $locals): bool
    {
        // A `v-model` (any arg/modifier) makes its target two-way — a write.
        foreach ($element->directiveBindings(Directive::Model) as $binding) {
            if (self::isPropTarget(Parser::parse($binding), $props, $locals)) {
                return true;
            }
        }

        // An assignment in a handler (`@click="prop = …"`) writes the prop directly.
        foreach ($element->expressions() as $expression) {
            if ($expression->is(Expr::ASSIGN) && self::isPropTarget($expression->get('target'), $props, $locals)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $props
     * @param  list<string>  $locals
     */
    private static function isPropTarget(Expr $target, array $props, array $locals): bool
    {
        $chain = $target->asChain();

        return $chain !== null
            && count($chain) === 1
            && isset($props[$chain[0]])
            && ! in_array($chain[0], $locals, true);
    }
}
