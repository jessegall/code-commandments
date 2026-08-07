<?php

namespace Shop\Templating;

use JesseGall\CodeCommandments\Sins\Backend\AssembledTemplate;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Writes the stub file a scaffold emits. The template is assembled from line
 * fragments, so the shape of the generated class exists nowhere in the source.
 */
final class StubWriter
{
    /**
     * The docblock delimiters are separate elements and the indentation is spaces
     * inside a quote — the output has to be assembled in the reader's head.
     */
    #[Sinful(AssembledTemplate::class)]
    public function stub(string $class): string
    {
        $lines = [
            '/**',
            " * A {$class}, generated. Do not edit.",
            ' */',
            "final class {$class}",
            '{',
            '    public function handle(): void',
            '    {',
            '    }',
            '}',
        ];

        return implode("\n", $lines);
    }

    /**
     * The FIX: one heredoc at the shape it emits. The docblock reads as a docblock,
     * the indentation is real whitespace, and the class name sits where it lands.
     */
    #[Fixed(AssembledTemplate::class)]
    #[Righteous(AssembledTemplate::class)]
    public function stated(string $class): string
    {
        return <<<PHP
            /**
             * A {$class}, generated. Do not edit.
             */
            final class {$class}
            {
                public function handle(): void
                {
                }
            }
            PHP;
    }

    /**
     * NOT the sin: the parts are values the program computed, so there is no fixed
     * shape a heredoc could state.
     */
    #[Righteous(AssembledTemplate::class)]
    public function listing(array $names): string
    {
        return implode("\n", array_map(static fn (string $name): string => "- {$name}", $names));
    }

    /**
     * NOT the sin either: joining into ONE line builds a value, not a layout.
     */
    #[Righteous(AssembledTemplate::class)]
    public function header(): string
    {
        return implode(', ', ['id', 'name', 'total']);
    }
}
