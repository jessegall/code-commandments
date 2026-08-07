<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * A `.commandments/config.php` that cannot be honoured — a `configure()` closure naming a detector
 * this project does not run, or one that never says which detector it means. Named rather than
 * generic, and carrying its own wording, so a caller can catch this and only this, and the sentence
 * the user reads is written once beside the condition that produces it.
 */
final class InvalidConfiguration extends \InvalidArgumentException
{
    public static function unknownDetector(string $class): self
    {
        return new self("configure({$class}): that detector is not registered, or was disabled.");
    }

    public static function untypedConfigurator(): self
    {
        return new self('A configure() closure must type-hint the detector to configure, e.g. fn (MyDetector $d) => …');
    }
}
