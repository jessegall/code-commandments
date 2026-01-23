<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Filter to only private methods.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FilterPrivateMethod implements Pipe
{
    private int $minLines = 0;

    public function withMinLines(int $minLines): self
    {
        $this->minLines = $minLines;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        $filtered = array_values(array_filter(
            $input->methods,
            function ($methodData) {
                $method = $methodData['method'];

                if (! $method->isPrivate()) {
                    return false;
                }

                if ($this->minLines > 0) {
                    $lineCount = $method->getEndLine() - $method->getStartLine() + 1;
                    if ($lineCount < $this->minLines) {
                        return false;
                    }
                }

                return true;
            }
        ));

        return $input->with(methods: $filtered);
    }
}
