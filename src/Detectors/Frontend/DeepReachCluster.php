<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Expr\Parser;
use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * A cluster of deep data reaches that share one nested object — `order.customer.name`
 * AND `order.customer.email`, reaching `order.customer` from several places. THAT is
 * the component waiting to be born (it takes `customer` as a prop); a lone deep reach
 * is not. The sibling of {@see SwitchCaseChain}: shared by the
 * {@see DeepDataReachDetector} (which asks "are there clusters here?") and the extract
 * scribe (which lifts each one), so the two agree on what to pull out and where.
 *
 * The detection rulebook, all structural — no name lists:
 *   - **F1 cluster, not a leaf** — a shared object must be read in ≥{@see MIN_FIELDS}
 *     DISTINCT fields, else it's a single reach and ignored.
 *   - **F2 climb to the boundary** — the finding is the {@see boundary} (lowest common
 *     ancestor) of the reaching elements, not the leaf.
 *   - **F3 depth floor** — only chains reaching ≥{@see MIN_DEPTH} hops past the root
 *     count; {@see TRANSPARENT} accessors (`.value`/`.length`) don't deepen it.
 *   - **R1 reactive root reject** — a root that is `v-model`-bound anywhere is owned
 *     STATE (an Inertia `useForm`, a `ref`), not a domain object; reaching into your
 *     own state is no Demeter sin, so its chains are dropped.
 */
final class DeepReachCluster
{
    public const int MIN_DEPTH = 2; // property hops past the root: order.customer.name

    private const int MIN_FIELDS = 2; // distinct fields read off the shared object

    /** Accessors that read reactive state / a count, not a nested data shape. */
    public const array TRANSPARENT = ['value', 'length'];

    /**
     * @param  list<Element>  $reaches  the distinct elements that reach into $object
     */
    private function __construct(
        public readonly string $object,
        private readonly array $reaches,
        public readonly Sfc $sfc,
    ) {}

    /**
     * Group the deep-reaching elements the detector SELECTED (via the fluent query) into
     * clusters — one per shared nested object read in ≥{@see MIN_FIELDS} distinct fields,
     * scoped per component, reactive roots excluded (R1). The detector composes the query;
     * this is the group-by over its results, the frontend twin of the backend's shape-hash
     * grouping detectors.
     *
     * @param  list<\JesseGall\CodeCommandments\Vue\ElementMatch>  $candidates
     * @return list<self>
     */
    public static function cluster(array $candidates): array
    {
        $clusters = [];

        foreach (self::byComponent($candidates) as $component) {
            $reactive = self::reactiveRoots($component['sfc']);
            $objects = [];

            foreach ($component['elements'] as $element) {
                foreach ($element->expressions() as $expression) {
                    foreach ($expression->chains() as $chain) {
                        $chain = self::material($chain);

                        if (count($chain) <= self::MIN_DEPTH || in_array($chain[0], $reactive, true)) {
                            continue; // F3 too shallow, or R1 a reactive root
                        }

                        // Group on the REAL tree node (not the query's match copy) so the
                        // boundary's lowest-common-ancestor walk runs over the live tree.
                        $object = "{$chain[0]}.{$chain[1]}";
                        $objects[$object]['fields'][implode('.', $chain)] = true;
                        $objects[$object]['reaches'][spl_object_id($element->node)] = $element->node;
                    }
                }
            }

            foreach ($objects as $object => $group) {
                if (count($group['fields']) >= self::MIN_FIELDS) {
                    $clusters[] = new self($object, array_values($group['reaches']), $component['sfc']);
                }
            }
        }

        return $clusters;
    }

    /**
     * The candidates grouped by their component — a cluster never spans files.
     *
     * @param  list<\JesseGall\CodeCommandments\Vue\ElementMatch>  $candidates
     * @return list<array{sfc: Sfc, elements: list<\JesseGall\CodeCommandments\Vue\ElementMatch>}>
     */
    private static function byComponent(array $candidates): array
    {
        $components = [];

        foreach ($candidates as $candidate) {
            $key = spl_object_id($candidate->sfc);
            $components[$key]['sfc'] = $candidate->sfc;
            $components[$key]['elements'][] = $candidate;
        }

        return array_values($components);
    }

    /**
     * The element to extract — the lowest common ancestor of every reach (F2).
     */
    public function boundary(): Element
    {
        $common = $this->reaches[0]->ancestry();

        foreach (array_slice($this->reaches, 1) as $element) {
            $spine = $element->ancestry();
            $common = array_values(array_filter($common, static fn (Element $node): bool => in_array($node, $spine, true)));
        }

        return $common[0]; // ancestry runs self→root, so the first shared node is the deepest
    }

    /**
     * The roots that are two-way bound (`v-model="root…"`) anywhere in the component —
     * reactive state to exclude (R1).
     *
     * @return list<string>
     */
    private static function reactiveRoots(Sfc $component): array
    {
        $roots = [];

        foreach ($component->elements()->withDirectiveFamily(Directive::Model)->get() as $match) {
            foreach ($match->directiveBindings(Directive::Model) as $binding) {
                foreach (Parser::parse($binding)->roots() as $root) {
                    $roots[] = $root;
                }
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * A chain with its pass-through accessors removed, so `form.data.value` reads as
     * the two-segment `form.data`, not a depth-2 reach (F3 / {@see TRANSPARENT}).
     *
     * @param  list<string>  $chain
     * @return list<string>
     */
    private static function material(array $chain): array
    {
        return array_values(array_filter($chain, static fn (string $segment): bool => ! in_array($segment, self::TRANSPARENT, true)));
    }
}
