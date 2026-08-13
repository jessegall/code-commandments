<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\Writer;
use PHPUnit\Framework\TestCase;

/**
 * Taking a docblock out WHOLE — the block and the line it stood on. A scribe that wanted this had to
 * hand-roll the walk to the line break over raw source (#459); the arsenal owns the offsets.
 */
final class RemoveDocblockTest extends TestCase
{
    public function test_a_block_on_its_own_lines_goes_with_its_line(): void
    {
        $source = "<?php\n\nclass Report\n{\n    /**\n     * A sentence the code already says.\n     */\n    public function render(): void {}\n}\n";

        $this->assertSame(
            "<?php\n\nclass Report\n{\n    public function render(): void {}\n}\n",
            $this->stripped($source),
        );
    }

    public function test_a_declaration_sharing_the_line_keeps_it(): void
    {
        // `/** ... */ public function render()` — eating the line would take the declaration too.
        $source = "<?php\n\nclass Report\n{\n    /** one line */ public function render(): void {}\n}\n";

        $this->assertSame(
            "<?php\n\nclass Report\n{\n     public function render(): void {}\n}\n",
            $this->stripped($source),
        );
    }

    /**
     * The source with the first method declaration's docblock removed.
     */
    private function stripped(string $source): string
    {
        $match = Codebase::fromString($source)->whereMethodDeclaration()->first();

        self::assertNotNull($match, 'the fixture must declare a method to strip');

        $draft = Draft::from([]);
        Writer::for($draft, $match)->removeDocblock($match->node);

        return $draft->rewrites()[$match->file->path];
    }
}
