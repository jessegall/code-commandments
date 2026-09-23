<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\UnnamedVocabularyLiteralDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use PHPUnit\Framework\TestCase;

final class UnnamedVocabularyLiteralDetectorTest extends TestCase
{
    public function test_flags_a_raw_string_in_a_slot_the_codebase_spells_by_name(): void
    {
        $this->assertSame([19], $this->lines(<<<'PY'
            class Token:
                COLON = ":"
                BRACE_OPEN = "{"
                _PRIVATE = "#"


            class Reader:
                def expect(self, token: str) -> None:
                    pass

                def pair(self) -> None:
                    self.expect(Token.COLON)

                def other(self, label: str) -> None:
                    pass

                def block(self) -> None:
                    self.other("{")
                    self.expect("{")
            PY));
    }

    public function test_leaves_a_value_no_constant_holds_and_a_private_shorthand(): void
    {
        $this->assertSame([], $this->lines(<<<'PY'
            class Token:
                COLON = ":"
                _HASH = "#"


            def expect(token: str) -> None:
                pass


            def read() -> None:
                expect(Token.COLON)
                expect("[")
                expect("#")
            PY));
    }

    /**
     * @return list<int>
     */
    private function lines(string $source): array
    {
        return array_map(static fn (ExprMatch $match): int => $match->line(), new UnnamedVocabularyLiteralDetector()->find(Codebase::fromString($source)));
    }
}
