<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Frontend;

use JesseGall\CodeCommandments\Vue\Attribute;
use JesseGall\CodeCommandments\Vue\Boundary;

/**
 * How a component's call-site tag is SHAPED — the structural directives it keeps, the props the child
 * writes back, whether it forwards slots, the indentation its slot block sits at, and the events it
 * re-binds. Five values that always travel together, so they travel as one, and each way of arriving at
 * them is a named constructor rather than a different argument list at a different call site.
 */
final readonly class TagShape
{
    /**
     * @param  list<Attribute>  $carried  the structural directives to keep at the call site
     * @param  list<string>  $models  props the child WRITES — bound with `v-model`, not `:`
     * @param  bool  $forwardsSlots  the chunk renders `<slot>`s — forward the host's slots
     * @param  int  $column  the call site's indentation, for the slot-forwarding block
     * @param  list<string>  $emits  events the child emits — the parent re-binds each to its own function
     */
    private function __construct(
        public array $carried = [],
        public array $models = [],
        public bool $forwardsSlots = false,
        public int $column = 0,
        public array $emits = [],
    ) {}

    /**
     * The shape of a boundary being EXTRACTED. Everything here is knowledge the boundary already has,
     * so it is read in one place, from the boundary itself.
     */
    public static function extracting(Boundary $boundary): self
    {
        return new self(
            $boundary->carried(),
            $boundary->models(),
            $boundary->hasSlots(),
            $boundary->contentSpan()->column(),
            array_keys($boundary->emitEvents()),
        );
    }

    /**
     * The shape for REUSING a component that already exists — the structural directives and nothing
     * else, because the bindings come from the matched component rather than from this boundary.
     *
     * @param  list<Attribute>  $carried
     */
    public static function carrying(array $carried): self
    {
        return new self($carried);
    }
}
