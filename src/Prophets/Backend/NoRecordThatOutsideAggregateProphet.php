<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\MatchPatterns;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\PipelineBuilder;

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
        return PipelineBuilder::make(PhpContext::from($filePath, $content))
            ->returnRighteousWhen(fn (PhpContext $ctx) => $this->isDomainFile($ctx->filePath))
            ->pipe((new MatchPatterns)->add('recordThat', '/->recordThat\s*\(/'))
            ->sinsFromMatches(
                'recordThat() called outside aggregate root',
                'Create a method on the aggregate that encapsulates recordThat() internally'
            )
            ->judge();
    }

    private function isDomainFile(string $filePath): bool
    {
        $lowerPath = strtolower($filePath);

        return str_starts_with($lowerPath, 'domain/') || str_contains($lowerPath, '/domain/');
    }
}
