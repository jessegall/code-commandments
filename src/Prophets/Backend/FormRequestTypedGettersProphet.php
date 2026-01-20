<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: FormRequest getters must have explicit return types.
 */
class FormRequestTypedGettersProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Add explicit return types to FormRequest getter methods';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
All FormRequest getter methods must have explicit return types.

This ensures type safety when accessing request data in controllers.
Getter methods should follow the pattern: getPropertyName(): Type

Bad:
    public function getName() {
        return $this->string('name');
    }

Good:
    public function getName(): string {
        return $this->string('name')->toString();
    }

    public function getWeight(): ?int {
        return $this->integer('weight');
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Only check FormRequest classes
        if (!str_contains($filePath, 'Requests') && !str_contains($filePath, 'Request.php')) {
            return $this->righteous();
        }

        $warnings = [];

        // Find public get* methods without return types
        // Pattern: public function getSomething() { (missing : Type before {)
        if (preg_match_all('/public\s+function\s+(get\w+)\s*\([^)]*\)\s*\{/m', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $methodName = $match[1][0];
                $offset = $match[0][1];

                // Check if there's a return type (should have : before {)
                $fullPattern = '/public\s+function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*:\s*[\w\\\\|?]+\s*\{/';
                if (!preg_match($fullPattern, $content)) {
                    $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                    $warnings[] = $this->warningAt(
                        $line,
                        "Method {$methodName}() missing return type",
                        "Add explicit return type: public function {$methodName}(): Type"
                    );
                }
            }
        }

        if (empty($warnings)) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }
}
