<?php

namespace Shop\Shelving;

use JesseGall\CodeCommandments\Sins\Backend\ConvertedArgument;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Lays out the floor bay by bay, slugging every name on the way in.
 */
final class ShelfPlanner
{
    public function __construct(private readonly ShelfIndex $index) {}

    /**
     * @param  list<string>  $aisles
     */
    #[Sinful(ConvertedArgument::class)]
    public function plan(array $aisles): void
    {
        foreach ($aisles as $bay => $name) {
            $this->index->reserve(SlugText::of($name), $bay);
        }
    }
}
