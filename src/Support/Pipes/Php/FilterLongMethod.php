<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Filter to only methods exceeding a maximum line count.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FilterLongMethod implements Pipe
{
    private int $maxLines = 20;

    public function withMaxLines(int $maxLines): self
    {
        $this->maxLines = $maxLines;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        $filtered = array_values(array_filter(
            $input->methods,
            function ($methodData) {
                $method = $methodData['method'];
                $lineCount = $method->getEndLine() - $method->getStartLine() + 1;

                return $lineCount > $this->maxLines;
            }
        ));

        return $input->with(methods: $filtered);
    }
}
