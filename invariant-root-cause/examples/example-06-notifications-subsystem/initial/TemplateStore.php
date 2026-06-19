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
     * GENUINE absence — a *customised* template may or may not exist; the caller
     * is meant to branch. Returning Option<Template> here is correct and is
     * KEPT in final/. (The misuse is in AlertDispatcher, which collapses this
     * Option to null for a template that is actually required.)
     *
     * @return Option<Template>
     */
    public function lookup(string $key): Option
    {
        return Option::fromValue($this->templates[$key] ?? null);
    }
}
