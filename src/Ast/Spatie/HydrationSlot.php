<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Spatie;

/**
 * The DESTINATION a value flows into when it sits in the arg0 array of a `SomeData::from([...])` call —
 * which property of which `Data` class it hydrates, that property's declared type, and (for a
 * `#[DataCollectionOf]` collection) its element type. Resolved by {@see SpatieDataNode::hydrationSlot}
 * from a value node, so every "am I re-doing what auto-hydration would do?" detector shares one walk.
 */
final readonly class HydrationSlot
{
    public function __construct(
        /**
         * The `Data` class whose `::from([...])` this value feeds.
         */
        public string $ownerFqcn,
        /**
         * The property key the value is bound to.
         */
        public string $property,
        /**
         * The property's declared type (FQCN), or null when unknown.
         */
        public ?string $declaredType,
        /**
         * Does the property carry `#[DataCollectionOf]` — a typed collection?
         */
        public bool $isCollection,
        /**
         * The type each item hydrates to — the `#[DataCollectionOf]` element, else the declared type.
         */
        public ?string $elementType,
        /**
         * Did the value sit inside a list literal (an element of a collection) rather than directly?
         */
        public bool $valueInList,
        /**
         * Does the destination property carry a `#[WithCast]`-family attribute?
         */
        public bool $destHasCast,
    ) {}
}
