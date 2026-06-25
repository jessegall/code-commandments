<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes;

/**
 * Base interface for all pipes.
 *
 * @template TInput
 * @template TOutput
 */
interface Pipe
{
    /**
     * Process the input and return the output.
     *
     * @param  TInput  $input
     * @return TOutput
     */
    public function handle(mixed $input): mixed;
}
