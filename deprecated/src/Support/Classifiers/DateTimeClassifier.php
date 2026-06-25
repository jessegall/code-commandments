<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Classifiers;

/**
 * Classifies date/time types — anything in the DateTime family. A nullable
 * date/time is a value-or-absent that is fine to leave null-defaulted, so it is
 * NOT a null-object candidate.
 */
final class DateTimeClassifier extends TypeClassifier
{
    protected function types(): array
    {
        return [\DateTimeInterface::class, \DateTime::class, \DateTimeImmutable::class];
    }
}
