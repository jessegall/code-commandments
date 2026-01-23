<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ReadonlyDataPropertiesProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ReadonlyDataPropertiesProphetTest extends TestCase
{
    private ReadonlyDataPropertiesProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ReadonlyDataPropertiesProphet();
    }

    public function test_detects_readonly_property_with_withcast_attribute(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;

class UserData extends Data
{
    #[WithCast(SomeCast::class)]
    public readonly string $name;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('WithCast', $judgment->sins[0]->message);
    }

    public function test_detects_multiple_readonly_properties_with_injecting_attributes(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;

class UserData extends Data
{
    #[WithCast(DateCast::class)]
    public readonly string $createdAt;

    #[WithCast(IntCast::class)]
    public readonly int $age;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(2, $judgment->sinCount());
    }

    public function test_passes_readonly_property_without_injecting_attributes(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public readonly string $name;
    public readonly int $age;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_non_readonly_property_with_withcast(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;

class UserData extends Data
{
    #[WithCast(DateCast::class)]
    public string $createdAt;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_constructor_promoted_readonly_with_withcast(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;

class UserData extends Data
{
    public function __construct(
        #[WithCast(DateCast::class)]
        public readonly string $createdAt,
    ) {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_non_readonly_body_properties(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public string $name;
    public int $age;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_data_classes(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Spatie\LaravelData\Attributes\WithCast;

class User
{
    #[WithCast(SomeCast::class)]
    public readonly string $name;
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/User.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_readonly_with_different_visibility(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;

class UserData extends Data
{
    #[WithCast(SomeCast::class)]
    protected readonly int $age;

    #[WithCast(SomeCast::class)]
    private readonly string $secret;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(2, $judgment->sinCount());
    }

    public function test_mixed_properties_only_flags_violations(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;

class UserData extends Data
{
    // OK: readonly without injecting attribute
    public readonly string $name;

    // OK: non-readonly with injecting attribute
    #[WithCast(DateCast::class)]
    public string $createdAt;

    // BAD: readonly with injecting attribute
    #[WithCast(IntCast::class)]
    public readonly int $age;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
        $this->assertStringContainsString('age', $judgment->sins[0]->message);
    }

    public function test_detects_readonly_property_with_injects_property_value_attribute(): void
    {
        // Create the InjectsPropertyValue interface if it doesn't exist
        if (!interface_exists('Spatie\LaravelData\Attributes\InjectsPropertyValue')) {
            eval('
                namespace Spatie\LaravelData\Attributes;

                interface InjectsPropertyValue {}
            ');
        }

        // Create a test attribute that implements InjectsPropertyValue
        if (!class_exists('App\Data\Attributes\TestInjectingAttribute')) {
            eval('
                namespace App\Data\Attributes;

                use Attribute;
                use Spatie\LaravelData\Attributes\InjectsPropertyValue;

                #[Attribute(Attribute::TARGET_PROPERTY)]
                class TestInjectingAttribute implements InjectsPropertyValue {}
            ');
        }

        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;
use App\Data\Attributes\TestInjectingAttribute;

class UserData extends Data
{
    #[TestInjectingAttribute]
    public readonly string $name;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('TestInjectingAttribute', $judgment->sins[0]->message);
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
