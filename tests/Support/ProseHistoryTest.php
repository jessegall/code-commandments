<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\Prose;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Prose that tells the code's past is history; the same words inside a condition describe what happens at
 * runtime.
 */
final class ProseHistoryTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function texts(): array
    {
        return [
            'a rename' => ['Renamed from DealerProfileInformationUpdated.', true],
            'an extraction' => ['This was extracted into its own class.', true],
            'history after a condition closed' => ['Returns early if empty. It used to be a dictionary.', true],
            'a runtime outcome in a condition' => ['true if a brand was extracted; otherwise false.', false],
            'a runtime place after where' => ['Gets where this candidate now lives in the register.', false],
            'a purpose, clause-initial' => ['Matches drafts. Used to fire the fallback rule so dealers see an estimate.', false],
            'history mid-clause' => ['Mirrors the switch the registration used to hold.', true],
            'a class that moved' => ['This helper now lives in the Pricing module.', true],
            'a runtime outcome after whether' => ['Reports whether the job was extracted into the queue.', false],
        ];
    }

    #[DataProvider('texts')]
    public function test_tells_history_from_a_runtime_condition(string $text, bool $history): void
    {
        $this->assertSame($history, Prose::narratesHistory($text));
    }
}
