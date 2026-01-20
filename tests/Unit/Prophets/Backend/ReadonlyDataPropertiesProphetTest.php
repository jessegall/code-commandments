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

    public function test_detects_readonly_property_in_class_body(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public readonly string $name;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_readonly_with_different_visibility(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    protected readonly int $age;
    private readonly string $secret;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(2, $judgment->sinCount());
    }

    public function test_detects_readonly_before_visibility(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    readonly public string $name;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_constructor_promoted_readonly(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
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

class User
{
    public readonly string $name;
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/User.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
