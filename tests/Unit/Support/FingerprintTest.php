<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\Fingerprint;
use JesseGall\CodeCommandments\Tests\TestCase;

class FingerprintTest extends TestCase
{
    public function test_same_inputs_yield_same_fingerprint(): void
    {
        $a = Fingerprint::of('App\\Prophet', 'src/Foo.php', 'bar()', 'return null;');
        $b = Fingerprint::of('App\\Prophet', 'src/Foo.php', 'bar()', 'return null;');

        $this->assertSame($a, $b);
    }

    public function test_whitespace_changes_do_not_change_fingerprint(): void
    {
        $a = Fingerprint::of('App\\Prophet', 'src/Foo.php', null, 'return   null;');
        $b = Fingerprint::of('App\\Prophet', 'src/Foo.php', null, "return\n null;");

        $this->assertSame($a, $b);
    }

    public function test_changing_the_snippet_changes_the_fingerprint(): void
    {
        $a = Fingerprint::of('App\\Prophet', 'src/Foo.php', null, 'return null;');
        $b = Fingerprint::of('App\\Prophet', 'src/Foo.php', null, 'return Option::none();');

        $this->assertNotSame($a, $b);
    }

    public function test_path_and_prophet_scope_the_identity(): void
    {
        $base = Fingerprint::of('App\\Prophet', 'src/Foo.php', null, 'return null;');

        $this->assertNotSame($base, Fingerprint::of('App\\Prophet', 'src/Bar.php', null, 'return null;'));
        $this->assertNotSame($base, Fingerprint::of('App\\Other', 'src/Foo.php', null, 'return null;'));
    }

    public function test_symbol_disambiguates_identical_snippets(): void
    {
        $a = Fingerprint::of('App\\Prophet', 'src/Foo.php', 'one()', 'return null;');
        $b = Fingerprint::of('App\\Prophet', 'src/Foo.php', 'two()', 'return null;');

        $this->assertNotSame($a, $b);
    }
}
