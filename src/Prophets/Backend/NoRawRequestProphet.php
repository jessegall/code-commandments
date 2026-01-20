<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: No raw Illuminate\Http\Request - Use dedicated FormRequest classes with typed getters.
 */
class NoRawRequestProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Use FormRequest classes instead of raw Request in controllers';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never use raw Illuminate\Http\Request in controller methods.

Instead, create a dedicated FormRequest class with typed getter methods.
This ensures validation is handled before the controller, and provides
type-safe access to request data.

Bad:
    public function store(Request $request) {
        $name = $request->input('name');
    }

Good:
    public function store(StoreProductRequest $request) {
        $name = $request->getName();
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
            // Look for method signatures with raw Request type hint
            // Pattern: (Request $request) or (Illuminate\Http\Request $request)
            if (preg_match('/\((?:Illuminate\\\\Http\\\\)?Request\s+\$request/', $line)) {
                // Make sure it's not a FormRequest (those end with Request but are specific)
                // Raw Request is just "Request" not "SomethingRequest"
                if (!preg_match('/\([A-Z][a-zA-Z]+Request\s+\$/', $line)) {
                    $sins[] = $this->sinAt(
                        $lineNum + 1,
                        'Raw Illuminate\Http\Request in controller method',
                        trim($line),
                        'Use a dedicated FormRequest class with typed getter methods'
                    );
                }
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
