<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: No JSON from controllers - Use Inertia responses only.
 *
 * Exception: API controllers (Http/Controllers/Api/) may return JSON.
 */
class NoJsonResponseProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Use Inertia responses instead of JSON in web controllers';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Web controllers should never return JSON responses directly.

Use Inertia responses for all frontend communication. JSON responses
break the Inertia request/response flow and bypass TypeScript type safety.

Exceptions: API controllers (Http/Controllers/Api/) and webhook controllers
may return JSON as they serve different purposes.

Bad:
    return response()->json(['data' => $data]);

Good:
    return Inertia::render('Products/Show', ProductShowPage::from());
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Only check controllers
        if (!str_contains($filePath, 'Controller')) {
            return $this->righteous();
        }

        // Allow JSON in API controllers
        if (str_contains($filePath, 'Controllers/Api/') || str_contains($filePath, 'Controllers\\Api\\')) {
            return $this->righteous();
        }

        // Allow JSON in webhook controllers
        if (str_contains($filePath, 'Webhook')) {
            return $this->righteous();
        }

        $sins = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            // Check for response()->json()
            if (preg_match('/response\(\)->json\(/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'JSON response via response()->json()',
                    trim($line),
                    'Use Inertia::render() instead'
                );
            }

            // Check for return new JsonResponse
            if (preg_match('/return\s+new\s+JsonResponse/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'JSON response via new JsonResponse()',
                    trim($line),
                    'Use Inertia::render() instead'
                );
            }

            // Check for Response::json()
            if (preg_match('/Response::json\(/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'JSON response via Response::json()',
                    trim($line),
                    'Use Inertia::render() instead'
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
