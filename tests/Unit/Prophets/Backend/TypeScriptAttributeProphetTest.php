<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\TypeScriptAttributeProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class TypeScriptAttributeProphetTest extends TestCase
{
    private TypeScriptAttributeProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new TypeScriptAttributeProphet();
    }

    public function test_detects_missing_typescript_attribute(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class ProductData extends Data
{
    public function __construct(
        public readonly string $name,
    ) {}
}
PHP;

        // Must be in /Data/ directory
        $judgment = $this->prophet->judge('/app/Data/ProductData.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_with_typescript_attribute(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProductData extends Data
{
    public function __construct(
        public readonly string $name,
    ) {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/ProductData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_data_files(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/User.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_utility_classes(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data\Casts;

class MyCast
{
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/Casts/MyCast.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }
}
