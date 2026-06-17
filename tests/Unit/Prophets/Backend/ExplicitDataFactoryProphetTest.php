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

    public function test_flags_new_self_field_by_field_in_static_factory(): void
    {
        $j = $this->judge('class SongData extends \Spatie\LaravelData\Data { public static function fromSong(\App\Song $song): self { return new self(title: $song->title); } }');
        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('field-by-field', $j->warnings[0]->message);
    }

    public function test_field_by_field_new_self_finding_is_not_auto_fixable(): void
    {
        // Issue #9: `new self(field: …)` is hand hydration — repent cannot
        // rewrite it, so the finding must NOT advertise itself auto-fixable
        // (or `judge --next` / the summary send the agent to a no-op repent).
        $this->prophet->configure(['severity' => 'sin']);

        $j = $this->judge('class SongData extends \Spatie\LaravelData\Data { public static function fromSong(\App\Song $s): self { return new self(title: $s->title); } }');

        $this->assertTrue($j->isFallen());
        $this->assertFalse($j->sins[0]->autoFixable, 'field-by-field new self() is not mechanically fixable');
    }

    public function test_bare_new_self_finding_is_auto_fixable(): void
    {
        $this->prophet->configure(['severity' => 'sin']);

        $j = $this->judge('class SongData extends \Spatie\LaravelData\Data { public static function def(): self { return new self(); } }');

        $this->assertTrue($j->isFallen());
        $this->assertTrue($j->sins[0]->autoFixable, 'bare new self() rewrites to ::make()');
    }

    public function test_flags_empty_from_and_bare_new_self_toward_make(): void
    {
        $j = $this->judge('class SongData extends \Spatie\LaravelData\Data { public static function blank(): self { return self::from([]); } public static function def(): self { return new self(); } }');
        $this->assertTrue($j->hasWarnings());
        $msgs = implode("\n", array_map(fn ($w) => $w->message, $j->warnings));
        $this->assertStringContainsString('make()', $msgs);
    }

    public function test_repent_rewrites_empty_from_and_bare_new_to_make(): void
    {
        $src = "<?php\nnamespace App;\nfinal class SongData extends \\Spatie\\LaravelData\\Data {\n    public static function blank(): self { return self::from([]); }\n    public static function def(): self { return new self(); }\n}\n";
        $r = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($r->absolved);
        $this->assertStringContainsString('self::make()', $r->newContent);
        $this->assertStringNotContainsString('from([])', $r->newContent);
        $this->assertStringNotContainsString('new self()', $r->newContent);
    }

    public function test_repent_leaves_object_from_alone(): void
    {
        // Not mechanical — needs a human factory — so repent does not touch it.
        $src = "<?php\nnamespace App;\nclass C { public function go(\\App\\Song \$s): mixed { return \\App\\SongData::from(\$s); } }\n";
        $this->assertFalse($this->prophet->repent('/x.php', $src)->absolved);
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
