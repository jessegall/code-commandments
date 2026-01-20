<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: No $request->validated() in controllers - Use typed getters.
 */
class NoValidatedMethodProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Use typed getters instead of $request->validated()';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never use $request->validated() in controllers.

The validated() method returns an untyped array, losing type safety.
Instead, use explicit typed getter methods on the FormRequest.

Bad:
    $data = $request->validated();
    $product->update($data);

Good:
    $product->name = $request->getName();
    $product->price = $request->getPrice();
    $product->save();
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
            // Check for $request->validated() without key argument
            if (preg_match('/\$request->validated\(\)/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'Using $request->validated() returns untyped array',
                    trim($line),
                    'Use typed getter methods on FormRequest instead'
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
