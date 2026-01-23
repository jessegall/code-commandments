<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Data classes must not have readonly properties in the class body.
 *
 * Laravel Data needs to inject properties, which doesn't work with
 * body-declared readonly properties. Use constructor promotion instead.
 */
class ReadonlyDataPropertiesProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Data classes must not declare readonly properties in the class body';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
In Laravel Data classes, readonly properties declared in the class body
prevent Laravel Data from injecting values properly.

The readonly modifier is only allowed on constructor-promoted properties.
Class body properties must not use readonly.

Bad:
    class UserData extends Data
    {
        public readonly string $name;
        public readonly int $age;
    }

Good:
    class UserData extends Data
    {
        public string $name;
        public int $age;
    }

Also good (readonly in constructor is allowed):
    class UserData extends Data
    {
        public function __construct(
            public readonly string $name,
            public readonly int $age,
        ) {}
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Only check Data classes (using AST)
        $ast = $this->parse($content);

        if (!$ast || !$this->isLaravelClass($ast, 'data')) {
            return $this->righteous();
        }

        $sins = [];
        $lines = explode("\n", $content);
        $inConstructor = false;
        $braceDepth = 0;

        foreach ($lines as $lineNum => $line) {
            // Track if we're inside a constructor
            if (preg_match('/function\s+__construct\s*\(/', $line)) {
                $inConstructor = true;
                $braceDepth = 0;
            }

            // Track brace depth to know when constructor ends
            if ($inConstructor) {
                $braceDepth += substr_count($line, '{');
                $braceDepth -= substr_count($line, '}');

                if ($braceDepth < 0 || ($braceDepth === 0 && str_contains($line, '}'))) {
                    $inConstructor = false;
                }
            }

            // Skip if inside constructor (constructor promotion is fine)
            if ($inConstructor) {
                continue;
            }

            // Look for readonly property declarations in class body
            if (preg_match('/^\s*(public|protected|private)\s+readonly\s+/', $line) ||
                preg_match('/^\s*readonly\s+(public|protected|private)\s+/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'Readonly property declared in class body',
                    trim($line),
                    'Remove the readonly modifier from class body properties'
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
