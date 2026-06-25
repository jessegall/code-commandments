<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Classifiers;

/**
 * Classifies enum types — a hint of `UnitEnum` / `BackedEnum` (or a concrete
 * enum). Enums have no empty instance, so a nullable enum is not a null-object
 * candidate.
 */
final class EnumClassifier extends TypeClassifier
{
    protected function types(): array
    {
        return [\UnitEnum::class, \BackedEnum::class];
    }
}
