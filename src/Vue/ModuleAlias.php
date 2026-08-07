<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\PhpTypes\Option;

/**
 * One import-path alias from a project's bundler config — the specifier prefix (`@`, `~ui`) and
 * the directory it stands for. Resolving a specifier through it is the alias's own business, so a
 * resolver asks each alias in turn instead of taking its two fields apart.
 */
final readonly class ModuleAlias
{
    private function __construct(
        public string $prefix,
        public string $dir,
    ) {}

    public static function of(string $prefix, string $dir): self
    {
        return new self($prefix, rtrim($dir, '/'));
    }

    /**
     * The path $specifier names through this alias — none when it does not use it. `@/ui/Card.vue`
     * under `@ => /app/js` is `/app/js/ui/Card.vue`; the bare prefix is the directory itself.
     *
     * @return Option<string>
     */
    public function resolve(string $specifier): Option
    {
        if ($specifier === $this->prefix) {
            return Option::some($this->dir);
        }

        return str_starts_with($specifier, $this->prefix . '/')
            ? Option::some($this->dir . substr($specifier, strlen($this->prefix)))
            : Option::none();
    }

    /**
     * Longest prefix FIRST — `@ui` must be tried before `@`, or the shorter one swallows every
     * specifier the longer one was declared for.
     */
    public static function longestFirst(self $a, self $b): int
    {
        return strlen($b->prefix) <=> strlen($a->prefix);
    }
}
