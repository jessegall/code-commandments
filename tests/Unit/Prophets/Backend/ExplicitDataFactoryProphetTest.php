<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ExplicitDataFactoryProphet;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class ExplicitDataFactoryProphetTest extends TestCase
{
    private ExplicitDataFactoryProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ExplicitDataFactoryProphet();
    }

    private function judge(string $content): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\n{$content}\n");
    }

    // ── from(<object>) → flag ─────────────────────────────────────────

    public function test_flags_from_typed_object_param(): void
    {
        $j = $this->judge('class C { public function go(\App\Song $song): mixed { return \App\SongData::from($song); } }');
        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('non-array (object)', $j->warnings[0]->message);
    }

    public function test_flags_from_this(): void
    {
        $j = $this->judge('class C { public function go(): mixed { return \App\SongData::from($this); } }');
        $this->assertTrue($j->hasWarnings());
    }

    public function test_flags_from_new(): void
    {
        $j = $this->judge('class C { public function go(): mixed { return \App\SongData::from(new \App\Song()); } }');
        $this->assertTrue($j->hasWarnings());
    }

    public function test_flags_from_object_property(): void
    {
        $j = $this->judge('class C { public function __construct(private \App\Song $song) {} public function go(): mixed { return \App\SongData::from($this->song); } }');
        $this->assertTrue($j->hasWarnings());
    }

    // ── array args → clean ────────────────────────────────────────────

    public function test_array_literal_is_clean(): void
    {
        $j = $this->judge('class C { public function go(): mixed { return \App\SongData::from(["a" => 1]); } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_array_typed_param_is_clean(): void
    {
        $j = $this->judge('class C { public function go(array $row): mixed { return \App\SongData::from($row); } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_unknown_type_is_left_alone(): void
    {
        $j = $this->judge('class C { public function go($x): mixed { return \App\SongData::from($x); } }');
        $this->assertTrue($j->isRighteous());
    }

    // ── enums are safe (scalar arg) ───────────────────────────────────

    public function test_enum_from_scalar_is_clean(): void
    {
        $j = $this->judge('class C { public function go(string $code): mixed { return \App\Status::from($code); } }');
        $this->assertTrue($j->isRighteous());
    }

    // ── toArray bypass: outside flags, inside is fine ─────────────────

    public function test_flags_toarray_bypass_outside(): void
    {
        $j = $this->judge('class C { public function go(\App\Song $song): mixed { return \App\SongData::from($song->toArray()); } }');
        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('toArray', $j->warnings[0]->message);
    }

    public function test_toarray_inside_the_class_is_clean(): void
    {
        $j = $this->judge('class SongData extends \Spatie\LaravelData\Data { public static function fromSong(\App\Song $song): self { return static::from($song->toArray()); } }');
        $this->assertTrue($j->isRighteous());
    }

    // ── new self() in a static factory of a Data class ────────────────

    public function test_flags_new_self_in_static_factory(): void
    {
        $j = $this->judge('class SongData extends \Spatie\LaravelData\Data { public static function fromSong(\App\Song $song): self { return new self(title: $song->title); } }');
        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('by hand', $j->warnings[0]->message);
    }

    public function test_copy_wither_spread_is_exempt(): void
    {
        $j = $this->judge('class SongData extends \Spatie\LaravelData\Data { public static function with(array $changes): self { return new self(...$changes); } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_new_self_in_non_data_class_is_clean(): void
    {
        $j = $this->judge('class Song { public static function make(): self { return new self(); } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_new_self_in_instance_method_is_clean(): void
    {
        // Instance-method new self() is a wither, not a factory — not flagged.
        $j = $this->judge('class SongData extends \Spatie\LaravelData\Data { public function copy(): self { return new self(); } }');
        $this->assertTrue($j->isRighteous());
    }

    // ── config ────────────────────────────────────────────────────────

    public function test_severity_sin_blocks(): void
    {
        $this->prophet->configure(['severity' => 'sin']);
        $j = $this->judge('class C { public function go(\App\Song $song): mixed { return \App\SongData::from($song); } }');
        $this->assertTrue($j->isFallen());
        $this->assertFalse($j->hasWarnings());
    }

    public function test_advisory_is_complete(): void
    {
        $this->assertInstanceOf(Advisory::class, $this->prophet->advisory());
        $this->assertTrue($this->prophet->advisory()->isComplete());
    }
}
