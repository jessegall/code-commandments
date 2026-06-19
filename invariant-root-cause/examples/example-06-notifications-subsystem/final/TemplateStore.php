<?php

declare(strict_types=1);

namespace Notifications;

use Support\Option;

final class TemplateStore
{
    /** @var array<string, Template> */
    private array $templates = [];

    public function put(string $key, Template $template): void
    {
        $this->templates[$key] = $template;
    }

    /**
     * GENUINE absence — kept exactly as-is. A user-customised template may
     * legitimately not exist; the caller is meant to branch on the Option.
     *
     * @return Option<Template>
     */
    public function lookup(string $key): Option
    {
        return Option::fromValue($this->templates[$key] ?? null);
    }

    /**
     * INVARIANT path — a built-in template that must exist (e.g. per severity).
     * Throws rather than handing back an Option the caller would only de-null.
     */
    public function require(string $key): Template
    {
        return $this->templates[$key]
            ?? throw TemplateNotFoundException::forKey($key);
    }
}
