<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * A flag the help screen documents must be one the parser accepts. Both read the SAME declaration, which
 * is what made the drift invisible: `--branch[=BASE]` was printed by `--help` and refused on sight,
 * because the name was read only as far as the `=` and came out as `branch[` — a name no invocation can
 * match. Three flags shipped documented and unusable.
 */
final class EveryDocumentedOptionIsAcceptedTest extends TestCase
{
    /**
     * Every option every command declares, as the name a user would type.
     *
     * @return list<array{string, string}>
     */
    public static function options(): array
    {
        $cases = [];

        foreach (new Kernel()->commands() as $command) {
            foreach ($command->help()->optionNames() as $name) {
                $cases[] = [$command->names()[0], $name];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider options
     */
    public function test_a_documented_option_is_a_name_a_user_can_type(string $command, string $name): void
    {
        $this->assertNotSame('', $name, "{$command} declares an option with no name");

        $this->assertDoesNotMatchRegularExpression(
            '/[\[\]=<>\s]/',
            $name,
            "`{$command}` documents an option whose parsed name is `{$name}` — that is the spec's "
                . 'punctuation, not a flag anybody can type, so the parser will refuse the very flag the '
                . 'help screen advertises',
        );
    }
}
