<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\StringMatchMirrorsEnumProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class StringMatchMirrorsEnumProphetTest extends TestCase
{
    private const ENUM = "<?php\nnamespace App;\nenum Suit: string { case Hearts = 'hearts'; case Spades = 'spades'; }\n";

    public function test_flags_a_string_match_mirroring_an_enum(): void
    {
        $judgment = $this->judgeAgainstEnum(<<<'PHP'
        <?php
        namespace App;
        class Game {
            public function score(string $s): int {
                return match ($s) {
                    'hearts' => 1,
                    'spades' => 2,
                };
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('string-match-mirrors-enum:Suit', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('App\\Suit', $judgment->warnings[0]->message);
    }

    public function test_flags_a_switch_mirroring_an_enum(): void
    {
        $judgment = $this->judgeAgainstEnum(<<<'PHP'
        <?php
        namespace App;
        class Game {
            public function score(string $s): int {
                switch ($s) {
                    case 'hearts': return 1;
                    case 'spades': return 2;
                    default: return 0;
                }
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_a_match_on_the_enum_itself(): void
    {
        $judgment = $this->judgeAgainstEnum(<<<'PHP'
        <?php
        namespace App;
        class Game {
            public function score(string $s): int {
                return match (Suit::from($s)) {
                    Suit::Hearts => 1,
                    Suit::Spades => 2,
                };
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_partial_overlap_or_non_literal_arms(): void
    {
        $partial = $this->judgeAgainstEnum(<<<'PHP'
        <?php
        namespace App;
        class G { public function f(string $s): int { return match ($s) { 'hearts' => 1, 'clubs' => 2 }; } }
        PHP);
        $this->assertTrue($partial->isRighteous(), 'a different value-set is not the enum');

        $nonLiteral = $this->judgeAgainstEnum(<<<'PHP'
        <?php
        namespace App;
        class G { public function f(string $s): int { return match ($s) { 'hearts' => 1, SOME_CONST => 2 }; } }
        PHP);
        $this->assertTrue($nonLiteral->isRighteous(), 'non-literal arms are not a pure stringly dispatch');
    }

    public function test_does_not_flag_when_no_enum_has_that_value_set(): void
    {
        // Index has Suit (hearts/spades); this match uses a different set.
        $judgment = $this->judgeAgainstEnum(<<<'PHP'
        <?php
        namespace App;
        class G { public function f(string $s): int { return match ($s) { 'red' => 1, 'green' => 2 }; } }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $prophet = new StringMatchMirrorsEnumProphet;
        $this->assertNotEmpty($prophet->description());
        $this->assertNotEmpty($prophet->detailedDescription());
        $this->assertNotNull($prophet->advisory());
    }

    private function judgeAgainstEnum(string $code): Judgment
    {
        $dir = sys_get_temp_dir() . '/cc-smme-' . uniqid();
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/Suit.php', self::ENUM);
        file_put_contents($dir . '/Use.php', $code);

        $index = CodebaseIndex::build([$dir . '/Suit.php', $dir . '/Use.php']);
        $prophet = new StringMatchMirrorsEnumProphet;
        $prophet->setCodebaseIndex($index);

        return $prophet->judge($dir . '/Use.php', $code);
    }
}
