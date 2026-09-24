<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\VerbMood;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The verb lexicon reads a name's first word the same in every convention — camelCase, snake_case and C#'s
 * PascalCase — and hands back the imperative in the name's own case.
 */
final class VerbMoodTest extends TestCase
{
    /**
     * @return array<string, array{string, bool, bool, string}>
     */
    public static function names(): array
    {
        return [
            'camelCase narration' => ['hidesPanel', true, false, 'hidePanel'],
            'snake_case narration' => ['hides_panel', true, false, 'hide_panel'],
            'PascalCase narration' => ['HidesPanel', true, false, 'HidePanel'],
            'PascalCase question' => ['IsHidden', false, true, 'IsHidden'],
            'PascalCase imperative' => ['Hide', false, false, 'Hide'],
        ];
    }

    #[DataProvider('names')]
    public function test_reads_a_name_in_any_convention(string $name, bool $thirdPerson, bool $question, string $imperative): void
    {
        $this->assertSame($thirdPerson, VerbMood::isThirdPerson($name));
        $this->assertSame($question, VerbMood::readsAsQuestion($name));
        $this->assertSame($imperative, VerbMood::imperative($name));
    }
}
