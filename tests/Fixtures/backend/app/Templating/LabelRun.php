<?php

namespace Shop\Templating;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringDefault;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Runs a batch of shelf labels into one string. Both blanks here MEAN blank: the buffer starts empty
 * because nothing has been written yet, and the separator defaults to none because running labels
 * together is a real choice. Neither is ever asked whether it is missing.
 */
#[Righteous(BlankStringDefault::class)]
final class LabelRun
{
    private string $buffer = '';

    public function __construct(
        private readonly string $separator = '',
    ) {}

    public function write(string $label): static
    {
        $this->buffer .= $this->separator . $label;

        return $this;
    }

    public function run(): string
    {
        return $this->buffer;
    }
}
