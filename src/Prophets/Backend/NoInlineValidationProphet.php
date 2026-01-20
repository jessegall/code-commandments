<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: No inline $request->validate() in controllers - Use FormRequest.
 */
class NoInlineValidationProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Move validation to FormRequest instead of inline $request->validate()';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never use inline validation ($request->validate()) in controllers.

Move all validation rules to a dedicated FormRequest class. This keeps
controllers clean and makes validation rules reusable and testable.

Bad:
    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string',
        ]);
    }

Good:
    // In StoreProductRequest
    public function rules(): array {
        return ['name' => ['required', 'string']];
    }

    // In Controller
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
            // Check for $request->validate()
            if (preg_match('/\$request->validate\s*\(/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'Inline validation via $request->validate()',
                    trim($line),
                    'Move validation to a dedicated FormRequest class'
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
