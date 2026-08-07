<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Doc;

use JesseGall\CodeCommandments\Cli\Doc\GeneratedBlock;
use JesseGall\CodeCommandments\Cli\Doc\MalformedBlock;
use PHPUnit\Framework\TestCase;

/**
 * The one splice behind every generated block. Its job is to replace what stands BETWEEN two
 * markers, and it now lives in files a human writes — a project's own instructions file — so the
 * question is no longer only "where is the block?" but "is this a block at all?".
 *
 * Two shapes made the old substring search dangerous there. A marker MENTIONED in prose (our own
 * documentation shows these markers, so a user pasting them into their notes is ordinary) was
 * matched as if it were the real one, and the splice then deleted everything from the mention to
 * the real END. And a document carrying two BEGINs — a merge, a copy-paste — spliced between the
 * first BEGIN and the first END, swallowing whatever a user had written between the pairs.
 *
 * So a marker counts only when it is ALONE on its line, and anything ambiguous is refused loudly
 * rather than guessed at.
 */
final class GeneratedBlockTest extends TestCase
{
    private const string NAME = 'code-commandments skills';

    public function test_it_replaces_what_stands_between_the_markers(): void
    {
        $document = $this->document('old body');

        $this->assertSame($this->document('new body'), GeneratedBlock::replace($document, self::NAME, "\nnew body\n"));
    }

    public function test_a_document_with_no_block_is_reported_as_having_none(): void
    {
        $this->assertNull(GeneratedBlock::replace("# Mine\n\nprose\n", self::NAME, 'x'));
    }

    public function test_a_marker_quoted_in_prose_is_not_a_block(): void
    {
        $document = "# Mine\n\nThe tool injects a `" . GeneratedBlock::begin(self::NAME, 'composer sync') . "` marker.\n\nmy own words\n";

        // Matched as the real thing, the splice ran from this mention to wherever an END turned up
        // — deleting the user's prose in between.
        $this->assertNull(GeneratedBlock::replace($document, self::NAME, 'x'));
    }

    public function test_two_blocks_are_refused_rather_than_guessed_between(): void
    {
        $document = $this->document('first') . "\nprose the user wrote\n" . $this->document('second');

        $this->expectException(MalformedBlock::class);

        GeneratedBlock::replace($document, self::NAME, 'x');
    }

    public function test_a_begin_with_no_end_is_refused(): void
    {
        $document = "# Mine\n" . GeneratedBlock::begin(self::NAME, 'composer sync') . "\nbody the user now owns\n";

        // Left to fall through as "no block", the caller appends a SECOND block — and the next run
        // then splices between this orphan BEGIN and the new END, eating everything between.
        $this->expectException(MalformedBlock::class);

        GeneratedBlock::replace($document, self::NAME, 'x');
    }

    public function test_an_end_before_its_begin_is_refused(): void
    {
        $document = GeneratedBlock::end(self::NAME) . "\nprose\n" . GeneratedBlock::begin(self::NAME, 'composer sync') . "\n";

        $this->expectException(MalformedBlock::class);

        GeneratedBlock::replace($document, self::NAME, 'x');
    }

    public function test_an_indented_marker_still_counts(): void
    {
        $document = '  ' . GeneratedBlock::begin(self::NAME, 'composer sync') . "\nbody\n  " . GeneratedBlock::end(self::NAME) . "\n";

        $this->assertStringContainsString('fresh', (string) GeneratedBlock::replace($document, self::NAME, "\nfresh\n"));
    }

    public function test_another_blocks_markers_are_left_alone(): void
    {
        $document = $this->document('ours') . GeneratedBlock::begin('other-table', 'x') . "\ntheirs\n" . GeneratedBlock::end('other-table') . "\n";

        $spliced = (string) GeneratedBlock::replace($document, self::NAME, "\nfresh\n");

        $this->assertStringContainsString("\ntheirs\n", $spliced);
        $this->assertStringContainsString("\nfresh\n", $spliced);
    }

    private function document(string $body): string
    {
        return GeneratedBlock::begin(self::NAME, 'composer sync') . "\n{$body}\n" . GeneratedBlock::end(self::NAME) . "\n";
    }
}
