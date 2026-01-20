<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: No has()/hasFile()/filled()/boolean() in controllers - Use typed FormRequest getters.
 */
class NoHasMethodInControllerProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Use typed FormRequest getters instead of has()/filled()/boolean()';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never use has(), hasFile(), filled(), or boolean() methods in controllers.

These methods should be encapsulated in FormRequest typed getters.
Use the pattern: if ($value = $request->getValue()) { ... }

Bad:
    if ($request->has('name')) {
        $name = $request->input('name');
    }

Good:
    // In FormRequest:
    public function getName(): ?string {
        return $this->input('name');
    }

    // In Controller:
    if ($name = $request->getName()) {
        // use $name
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Only check controllers
        if (!str_contains($filePath, 'Controller')) {
            return $this->righteous();
        }

        $sins = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            // Check for $request->has()
            if (preg_match('/\$request->has\s*\(/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'Using $request->has() in controller',
                    trim($line),
                    'Use typed FormRequest getters with nullable returns'
                );
            }

            // Check for $request->hasFile()
            if (preg_match('/\$request->hasFile\s*\(/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'Using $request->hasFile() in controller',
                    trim($line),
                    'Use typed FormRequest getter that returns ?UploadedFile'
                );
            }

            // Check for $request->filled()
            if (preg_match('/\$request->filled\s*\(/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'Using $request->filled() in controller',
                    trim($line),
                    'Use typed FormRequest getters with nullable returns'
                );
            }

            // Check for $request->boolean()
            if (preg_match('/\$request->boolean\s*\(/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'Using $request->boolean() in controller',
                    trim($line),
                    'Use typed FormRequest getter that returns bool'
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
