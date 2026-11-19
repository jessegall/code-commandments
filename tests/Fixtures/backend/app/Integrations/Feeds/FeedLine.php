<?php

namespace Shop\Integrations\Feeds;

use JesseGall\CodeCommandments\Sins\Backend\FalseForNothing;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reads a supplier's price feed one line at a time.
 */
final class FeedLine
{
    /**
     * @param  resource  $handle
     */
    public function __construct(private readonly mixed $handle) {}

    #[Sinful(FalseForNothing::class)]
    public function next(): string|false
    {
        $line = fgets($this->handle);

        return $line === false ? false : rtrim($line, "\n");
    }

    #[Righteous(FalseForNothing::class)]
    public function isFinished(): bool
    {
        return feof($this->handle);
    }
}
