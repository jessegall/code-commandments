<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: No recordThat outside aggregates - Add aggregate methods that encapsulate recordThat internally.
 */
class NoRecordThatOutsideAggregateProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Only call recordThat() inside Aggregate classes';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
The recordThat() method should only be called inside Aggregate classes.

Never call recordThat() from outside the domain layer. Instead, create
a method on the aggregate that encapsulates the event recording internally.

Bad:
    // In a controller or service
    $aggregate->recordThat(new OrderShipped($orderId));

Good:
    // In the Aggregate class
    public function ship(): void {
        $this->recordThat(new OrderShipped($this->id));
    }

    // In controller
    $aggregate->ship();
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Skip validator/prophet files (they contain example code in heredocs)
        if (str_contains($filePath, 'Commandments/Validators/') || str_contains($filePath, 'Prophets/')) {
            return $this->righteous();
        }

        // recordThat is allowed in domain/ (aggregates)
        $lowerPath = strtolower($filePath);
        if (str_starts_with($lowerPath, 'domain/') || str_contains($lowerPath, '/domain/')) {
            return $this->righteous();
        }

        $sins = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            // Find recordThat usages
            if (preg_match('/->recordThat\s*\(/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'recordThat() called outside aggregate root',
                    trim($line),
                    'Create a method on the aggregate that encapsulates recordThat() internally'
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
