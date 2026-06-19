<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Prophets\Backend\DataClassFromArrayOnlyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ExplicitDataFactoryProphet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * #64/#65: the FromArrayOnly trait-add and the from([])->make() rewrite must
 * agree per class, gated on a CROSS-FILE census of object ::from() sites — the
 * call sites usually live in other files than the Data class.
 */
class DataFromCensusTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-census-' . uniqid();
        @mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function write(string $name, string $body): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $body);

        return $path;
    }

    public function test_trait_withheld_when_an_object_from_site_lives_in_another_file(): void
    {
        $userData = $this->write('UserData.php', "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nclass UserData extends Data { public function __construct(public string \$name) {} }\n");
        // The object ::from() call site is in ANOTHER file.
        $this->write('Page.php', "<?php\nnamespace App\\Http;\nuse App\\Data\\UserData;\nclass Page { public function r(\$user) { return UserData::from(\$user->append('pin')); } }\n");
        // A control class with only an array ::from() site — still safe.
        $cleanData = $this->write('CleanData.php', "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nclass CleanData extends Data { public function __construct(public string \$x) {} }\n");
        $this->write('Use.php', "<?php\nnamespace App\\Http;\nuse App\\Data\\CleanData;\nclass Use2 { public function r(array \$a) { return CleanData::from(\$a); } }\n");

        $index = CodebaseIndex::build(glob($this->dir . '/*.php') ?: []);
        $prophet = new DataClassFromArrayOnlyProphet;
        $prophet->setCodebaseIndex($index);

        $withheld = $prophet->repent($userData, file_get_contents($userData));
        $this->assertFalse(
            $withheld->absolved && str_contains((string) $withheld->newContent, 'FromArrayOnly'),
            'UserData has a cross-file object ::from() — the trait must be withheld.',
        );

        $added = $prophet->repent($cleanData, file_get_contents($cleanData));
        $this->assertTrue($added->absolved, 'CleanData has only array ::from() sites — the trait is still added.');
        $this->assertStringContainsString('FromArrayOnly', (string) $added->newContent);
    }

    public function test_make_withheld_when_class_has_an_unresolved_self_from(): void
    {
        // #70: a class whose own static factory calls self::from(<unresolved>)
        // has the trait withheld (hasUnsafeSelfFrom). The make() rewrite must
        // agree — census mode treats an unresolved self ::from() as trait-unsafe.
        $this->write('SmtpSettingsData.php', "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nclass SmtpSettingsData extends Data { public function __construct(public string \$host) {} public static function fromRaw(\$raw): self { return self::from(\$raw); } }\n");
        $this->write('CleanData.php', "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nclass CleanData extends Data { public function __construct(public string \$x) {} }\n");
        $ctrl = $this->write('Ctrl.php', "<?php\nnamespace App\\Http;\nuse App\\Data\\SmtpSettingsData;\nuse App\\Data\\CleanData;\nclass Ctrl { public function a() { return SmtpSettingsData::from([]); } public function b() { return CleanData::from([]); } }\n");

        $index = CodebaseIndex::build(glob($this->dir . '/*.php') ?: []);
        $prophet = new ExplicitDataFactoryProphet;
        $prophet->setCodebaseIndex($index);

        $out = (string) ($prophet->repent($ctrl, file_get_contents($ctrl))->newContent ?? file_get_contents($ctrl));

        $this->assertStringContainsString('SmtpSettingsData::from([])', $out, 'unresolved self::from → trait withheld → leave from([]).');
        $this->assertStringContainsString('CleanData::make()', $out, 'clean class still gets make().');
    }

    public function test_from_empty_not_rewritten_to_make_when_trait_is_withheld(): void
    {
        $this->write('SmtpSettingsData.php', "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nclass SmtpSettingsData extends Data { public function __construct(public string \$host) {} }\n");
        $this->write('CleanData.php', "<?php\nnamespace App\\Data;\nuse Spatie\\LaravelData\\Data;\nclass CleanData extends Data { public function __construct(public string \$x) {} }\n");
        // SmtpSettingsData has an object ::from() (request()); CleanData does not.
        $ctrl = $this->write('Ctrl.php', "<?php\nnamespace App\\Http;\nuse App\\Data\\SmtpSettingsData;\nuse App\\Data\\CleanData;\nclass Ctrl {\n public function withObjectFromElsewhere() { return SmtpSettingsData::from(request()); }\n public function a() { return SmtpSettingsData::from([]); }\n public function b() { return CleanData::from([]); }\n}\n");

        $index = CodebaseIndex::build(glob($this->dir . '/*.php') ?: []);
        $prophet = new ExplicitDataFactoryProphet;
        $prophet->setCodebaseIndex($index);

        $result = $prophet->repent($ctrl, file_get_contents($ctrl));
        $out = (string) ($result->newContent ?? file_get_contents($ctrl));

        $this->assertStringContainsString('SmtpSettingsData::from([])', $out, 'trait withheld → leave from([]), make() would be undefined.');
        $this->assertStringContainsString('CleanData::make()', $out, 'clean class → from([]) still becomes make().');
    }
}
