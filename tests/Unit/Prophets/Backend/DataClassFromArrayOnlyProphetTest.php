<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\DataClassFromArrayOnlyProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class DataClassFromArrayOnlyProphetTest extends TestCase
{
    private DataClassFromArrayOnlyProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = (new DataClassFromArrayOnlyProphet())
            ->configure(['trait_class' => 'App\\Support\\FromArrayOnly']);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App\\Data;\n{$body}\n");
    }

    public function test_flags_data_class_without_trait(): void
    {
        $j = $this->judge('use Spatie\\LaravelData\\Data; final class SongData extends Data { public string $title; }');
        $this->assertTrue($j->isFallen());
        $this->assertStringContainsString('FromArrayOnly', $j->sins[0]->message);
    }

    public function test_clean_when_trait_present(): void
    {
        $j = $this->judge('use Spatie\\LaravelData\\Data; use App\\Support\\FromArrayOnly; final class SongData extends Data { use FromArrayOnly; }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_clean_for_non_data_class(): void
    {
        $j = $this->judge('final class Song { public string $title; }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_clean_for_class_extending_a_project_base(): void
    {
        // Extends a project base (not Spatie Data directly) — the requirement
        // bubbles up to that base, so subclasses are not flagged.
        $j = $this->judge('final class SongData extends BaseData { public string $title; }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_resolves_aliased_data_import(): void
    {
        $j = $this->judge('use Spatie\\LaravelData\\Data as SpatieData; final class SongData extends SpatieData {}');
        $this->assertTrue($j->isFallen());
    }

    public function test_repent_adds_trait_and_import(): void
    {
        $src = "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nfinal class SongData extends Data\n{\n    public string \$title;\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('use App\\Support\\FromArrayOnly;', $result->newContent);
        $this->assertStringContainsString('use FromArrayOnly;', $result->newContent);
        // And the fix is complete — re-judging is clean.
        $this->assertTrue($this->prophet->judge('/x.php', $result->newContent)->isRighteous());
    }

    public function test_repent_is_unchanged_when_already_compliant(): void
    {
        $src = "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nuse App\\Support\\FromArrayOnly;\nfinal class SongData extends Data\n{\n    use FromArrayOnly;\n}\n";

        $this->assertFalse($this->prophet->repent('/x.php', $src)->absolved);
    }

    public function test_can_repent_php_only(): void
    {
        $this->assertTrue($this->prophet->canRepent('/x.php'));
        $this->assertFalse($this->prophet->canRepent('/x.vue'));
    }
}
