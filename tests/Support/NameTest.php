<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\Name;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A commandment's class name and its `--sin=` id are two spellings of ONE word, so the conversion
 * has to round-trip: whatever a human types on the command line must give the same pair.
 */
final class NameTest extends TestCase
{
    #[DataProvider('studlyCases')]
    public function test_studly(string $input, string $expected): void
    {
        $this->assertSame($expected, Name::studly($input));
    }

    public static function studlyCases(): iterable
    {
        yield 'already studly' => ['NullableElementReturn', 'NullableElementReturn'];
        yield 'kebab' => ['nullable-element-return', 'NullableElementReturn'];
        yield 'snake' => ['nullable_element_return', 'NullableElementReturn'];
        yield 'spaced' => ['nullable element return', 'NullableElementReturn'];
        yield 'camel' => ['nullableElementReturn', 'NullableElementReturn'];
        yield 'nothing to name' => ['---', ''];
    }

    #[DataProvider('kebabCases')]
    public function test_kebab(string $input, string $expected): void
    {
        $this->assertSame($expected, Name::kebab($input));
    }

    public static function kebabCases(): iterable
    {
        yield 'studly' => ['NullableElementReturn', 'nullable-element-return'];
        yield 'already kebab' => ['nullable-element-return', 'nullable-element-return'];
        yield 'an acronym run splits before its last capital' => ['ParseHTMLDocument', 'parse-html-document'];
        yield 'a trailing acronym stays whole' => ['RawSQL', 'raw-sql'];
        yield 'digits ride along' => ['Psr4Root', 'psr4-root'];
    }

    public function test_the_two_spellings_round_trip(): void
    {
        foreach (['NullableElementReturn', 'NoRawSql', 'DeepTemplate'] as $studly) {
            $this->assertSame($studly, Name::studly(Name::kebab($studly)));
        }
    }
}
