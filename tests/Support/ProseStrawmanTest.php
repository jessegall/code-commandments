<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\Prose;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A comment defends the code against a strawman when it says what the code is not — not random, not a typo,
 * deliberately not sorted. A clause saying what happens at runtime, or what the code makes sure of, is not
 * one, and neither is "mistake" used as a verb.
 */
final class ProseStrawmanTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function sentences(): array
    {
        return [
            'not random' => ['The stream id is deterministic, not random.', true],
            'not an oversight' => ['There is no green anywhere in this palette, and that is not an oversight.', true],
            'not a mistake' => ['This is deliberate, not a mistake.', true],
            'deliberately not' => ['Visibility is deliberately not re-checked.', true],
            'absent from this listing' => ['The website is not here either: it is on the record.', true],
            'not declared here' => ['Operation capabilities are NOT declared here.', true],
            'mistake as a verb' => ['Published beside every place so a reader can never mistake a town for a doorstep.', false],
            'mistake after does not' => ['The context records it so a reader does not mistake it for a verified principal.', false],
            'mistake after a modal' => ['The instant stays null, so nothing later can mistake our clock for the provider\'s.', false],
            'what a test makes sure of' => ['Far from the other, so a test that read the wrong one could not pass by coincidence.', false],
            'a runtime condition' => ['Omits it from the list when the requested channel is not in this set.', false],
            'a relative clause' => ['An entry whose town is not here is not drawn at all.', false],
            'a negation across a dash' => ['Names that do not resolve — a typo in a secret must not take the process down.', false],
            'a reason' => ['Because Scrutor is not present in this solution, each rule is registered by hand.', false],
            'another thing\'s contents' => ['The raw condition string is not in this source\'s condition map.', false],
        ];
    }

    #[DataProvider('sentences')]
    public function test_reads_a_defence_against_a_strawman(string $sentence, bool $defends): void
    {
        $this->assertSame($defends, Prose::defendsAgainstStrawman($sentence), $sentence);
    }
}
