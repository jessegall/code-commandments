<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoCustomFromModelProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoCustomFromModelProphetTest extends TestCase
{
    private NoCustomFromModelProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoCustomFromModelProphet();
    }

    public function test_detects_custom_from_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly string $name,
    ) {}

    public static function fromModel($user): self
    {
        return new self($user->name);
    }
}
PHP;

        // Must be in /Data/ directory
        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_without_custom_from(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly string $name,
    ) {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_data_directory(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class UserFactory
{
    public static function fromModel($model): self
    {
        return new self();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserFactory.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }
}
