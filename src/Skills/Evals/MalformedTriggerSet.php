<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Evals;

use RuntimeException;

/**
 * A `triggers.json` that is not the object the eval harness reads. Named and thrown at the file,
 * because a silently-ignored eval set is a description that reports itself measured when it is not.
 */
final class MalformedTriggerSet extends RuntimeException
{
    public static function at(string $path): self
    {
        return new self("{$path} is not a trigger set — expected an object with `triggers` and optional `not` arrays.");
    }
}
