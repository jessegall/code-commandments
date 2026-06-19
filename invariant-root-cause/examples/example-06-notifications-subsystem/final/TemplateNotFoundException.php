<?php

declare(strict_types=1);

namespace Notifications;

/**
 * ADDED CLASS — named exception for the *required* template path. Lets
 * TemplateStore offer both a genuine optional lookup (Option) and a throwing
 * require() without conflating the two kinds of absence.
 */
final class TemplateNotFoundException extends \RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No template registered for key '{$key}'.");
    }
}
