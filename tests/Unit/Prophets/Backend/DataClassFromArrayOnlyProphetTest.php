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

    public function test_class_with_self_from_object_is_flagged_not_auto_fixable(): void
    {
        // Issue #14: a Data class whose own factory passes a non-array to
        // ::from() must NOT be advertised as auto-fixable — adding the trait
        // would make its runtime array-assert throw.
        $j = $this->judge('use Spatie\\LaravelData\\Data; final class HttpNodeConfig extends Data { public static function fromNode($node): self { return self::from($node->staticInputs); } }');

        $this->assertTrue($j->isFallen());
        $this->assertFalse($j->sins[0]->autoFixable);
        $this->assertStringContainsString('non-array', $j->sins[0]->message);
    }

    public function test_repent_skips_a_class_with_self_from_object(): void
    {
        // Issue #14: repent must not add the trait here — doing so broke 84
        // tests with 'from() expects an array'. Leave it for the agent to
        // migrate the ::from() call sites first.
        $src = "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nfinal class HttpNodeConfig extends Data\n{\n    public static function fromNode(\$node): self\n    {\n        return self::from(\$node->staticInputs);\n    }\n}\n";

        $this->assertFalse($this->prophet->repent('/x.php', $src)->absolved);
    }

    public function test_repent_still_fixes_a_class_whose_from_takes_an_array_param(): void
    {
        // from($arrayParam) is array-safe — the trait can be added.
        $src = "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nfinal class BarData extends Data\n{\n    public static function fromArrayData(array \$data): self\n    {\n        return self::from(\$data);\n    }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('use FromArrayOnly;', $result->newContent);
    }

    public function test_repent_still_fixes_a_class_whose_from_uses_to_array(): void
    {
        // from($x->toArray()) is array-safe too.
        $src = "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nfinal class FooData extends Data\n{\n    public static function fromNode(\$node): self\n    {\n        return self::from(\$node->staticInputs->toArray());\n    }\n}\n";

        $this->assertTrue($this->prophet->repent('/x.php', $src)->absolved);
    }

    public function test_can_repent_php_only(): void
    {
        $this->assertTrue($this->prophet->canRepent('/x.php'));
        $this->assertFalse($this->prophet->canRepent('/x.vue'));
    }

    // ── inheritance awareness via the codebase index ──────────────────

    public function test_subclass_is_clean_when_base_has_the_trait(): void
    {
        $dir = sys_get_temp_dir() . '/cc-dcfao-' . uniqid();
        mkdir($dir);
        // Base extends Spatie Data and USES the trait.
        file_put_contents($dir . '/BaseData.php', "<?php\nnamespace App;\nuse Spatie\\LaravelData\\Data;\nuse App\\Support\\FromArrayOnly;\nabstract class BaseData extends Data { use FromArrayOnly; }\n");
        // Subclass extends the base — inherits the trait, so it has access.
        $subPath = $dir . '/SongData.php';
        $subSrc = "<?php\nnamespace App;\nfinal class SongData extends BaseData { public string \$title; }\n";
        file_put_contents($subPath, $subSrc);

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build([$dir . '/BaseData.php', $subPath]);
        $this->prophet->setCodebaseIndex($index);

        $this->assertTrue($this->prophet->judge($subPath, $subSrc)->isRighteous());

        shell_exec('rm -rf ' . escapeshellarg($dir));
    }

    public function test_subclass_is_flagged_when_no_ancestor_has_the_trait(): void
    {
        $dir = sys_get_temp_dir() . '/cc-dcfao-' . uniqid();
        mkdir($dir);
        // Base extends Spatie Data but does NOT use the trait.
        file_put_contents($dir . '/BaseData.php', "<?php\nnamespace App;\nuse Spatie\\LaravelData\\Data;\nabstract class BaseData extends Data {}\n");
        $subPath = $dir . '/SongData.php';
        $subSrc = "<?php\nnamespace App;\nfinal class SongData extends BaseData { public string \$title; }\n";
        file_put_contents($subPath, $subSrc);
        // #80: positive proof — a provable-array ::from() site somewhere.
        file_put_contents($dir . '/Caller.php', "<?php\nnamespace App;\nclass Caller { public function m(array \$a) { return SongData::from(\$a); } }\n");

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build([$dir . '/BaseData.php', $subPath, $dir . '/Caller.php']);
        $this->prophet->setCodebaseIndex($index);

        // No ancestor provides the trait → the subclass lacks access → flagged.
        $this->assertTrue($this->prophet->judge($subPath, $subSrc)->isFallen());

        shell_exec('rm -rf ' . escapeshellarg($dir));
    }

    public function test_base_is_exempt_when_a_subclass_depends_on_magic(): void
    {
        // #49: the array-only trait is inherited downward — a #[LoadRelation]
        // subclass needs the magic from(Model), so adding the trait to the base
        // would fatal at runtime. The base must be exempt, not flagged.
        $dir = sys_get_temp_dir() . '/cc-dcfao-magic-' . uniqid();
        mkdir($dir);

        $basePath = $dir . '/ShopDetailsData.php';
        $baseSrc = "<?php\nnamespace App;\nuse Spatie\\LaravelData\\Data;\nclass ShopDetailsData extends Data { public int \$id; }\n";
        file_put_contents($basePath, $baseSrc);
        file_put_contents($dir . '/ShopData.php', "<?php\nnamespace App;\nuse Spatie\\LaravelData\\Attributes\\LoadRelation;\nclass ShopData extends ShopDetailsData {\n    #[LoadRelation]\n    public array \$channels = [];\n}\n");

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build([$basePath, $dir . '/ShopData.php']);
        $this->prophet->setCodebaseIndex($index);

        $this->assertTrue($this->prophet->judge($basePath, $baseSrc)->isRighteous(), 'A base whose subclass depends on magic from(Model) must be exempt.');

        shell_exec('rm -rf ' . escapeshellarg($dir));
    }
}
