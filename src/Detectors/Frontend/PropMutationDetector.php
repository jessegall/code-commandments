<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\PropMutation;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Expr\ExprKind;
use JesseGall\CodeCommandments\Ts\Expr\Parser;
use JesseGall\CodeCommandments\Vue\Script;

/**
 * Detects component writing its own props (v-model or assignment); only bare prop
 * writes are flagged, not shadowed locals.
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
            if ($expression->is(ExprKind::Assign) && self::isPropTarget($expression->get('target'), $props, $locals)) {
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
