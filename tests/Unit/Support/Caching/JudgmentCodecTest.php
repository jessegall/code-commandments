<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Caching;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Caching\JudgmentCodec;
use JesseGall\CodeCommandments\Tests\TestCase;

class JudgmentCodecTest extends TestCase
{
    public function test_round_trips_sins_and_warnings_field_for_field(): void
    {
        $results = new Collection([
            'App\\Prophets\\Foo' => new Judgment(
                sins: [new Sin('a sin', 12, 4, 'snip', 'fix it', 'foo:sym', true)],
                warnings: [new Warning('a warning', 7, 'wsnip', 'bar:sym', false)],
            ),
            'App\\Prophets\\Bar' => new Judgment(skipped: true, skipReason: 'not applicable'),
        ]);

        $decoded = JudgmentCodec::decode(json_decode(json_encode(JudgmentCodec::encode($results)), true));

        $foo = $decoded->get('App\\Prophets\\Foo');
        $this->assertCount(1, $foo->sins);
        $this->assertCount(1, $foo->warnings);

        $sin = $foo->sins[0];
        $this->assertSame('a sin', $sin->message);
        $this->assertSame(12, $sin->line);
        $this->assertSame(4, $sin->column);
        $this->assertSame('snip', $sin->snippet);
        $this->assertSame('fix it', $sin->suggestion);
        $this->assertSame('foo:sym', $sin->symbol);
        $this->assertTrue($sin->autoFixable);

        $warning = $foo->warnings[0];
        $this->assertSame('a warning', $warning->message);
        $this->assertSame(7, $warning->line);
        $this->assertSame('wsnip', $warning->snippet);
        $this->assertSame('bar:sym', $warning->symbol);
        $this->assertFalse($warning->autoFixable);

        $bar = $decoded->get('App\\Prophets\\Bar');
        $this->assertTrue($bar->skipped);
        $this->assertSame('not applicable', $bar->skipReason);
    }
}
