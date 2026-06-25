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
        $filtered = collect($input->methods)
            ->filter(function ($methodData) use ($input) {
                $method = $methodData['method'];
                $lineCount = MethodLineCounter::count(
                    $input->content,
                    $method->getStartLine(),
                    $method->getEndLine()
                );

                return $lineCount > $this->maxLines;
            })
            ->values()
            ->all();

        return $input->with(methods: $filtered);
    }
}
