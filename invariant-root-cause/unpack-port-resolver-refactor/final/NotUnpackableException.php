<?php

namespace JesseGall\Workflows\Workflow\Nodes;

/**
 * ADDED CLASS — the loud failure for the invariant unpack paths
 * ({@see UnpackPortResolver::requirePortsFor()} / requireExtract()).
 *
 * Lives beside InvalidUnpackerException in the same namespace; a named exception
 * (static factory, no message strings at the throw site) so a non-unpackable
 * value surfaces as a clear, catchable error instead of an empty port set or a
 * silently field-less node.
 */
final class NotUnpackableException extends \RuntimeException
{
    /**
     * @param  class-string  $class
     */
    public static function forClass(string $class): self
    {
        return new self(
            "Class {$class} exposes no unpack ports: no unpacker is registered "
            . 'and it has no reflectable public fields.'
        );
    }
}
