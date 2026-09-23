<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\Option;

/**
 * One {@see LocatedTool}, sought on first use and kept — so whoever holds it (the journal's hook service, a
 * judge run) finds it once and reuses the running tool warm.
 *
 * @template T of LocatedTool
 */
final class HeldTool
{
    /**
     * @var Option<T>
     */
    private Option $tool;

    private bool $sought = false;

    /**
     * @param  class-string<T>  $class
     */
    public function __construct(private readonly string $class)
    {
        $this->tool = Option::none();
    }

    /**
     * @return Option<T>
     */
    public function tool(): Option
    {
        if (! $this->sought) {
            $this->tool = ($this->class)::located();
            $this->sought = true;
        }

        return $this->tool;
    }
}
