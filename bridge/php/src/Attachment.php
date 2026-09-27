<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

/**
 * Where a comment belongs: the node it is attached to, when one owns it, and whether it trails code on its line.
 */
final readonly class Attachment
{
    public function __construct(public ?int $owner, public bool $trailing) {}
}
