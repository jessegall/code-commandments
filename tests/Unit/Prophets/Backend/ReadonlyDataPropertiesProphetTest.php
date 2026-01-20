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

    public function test_rule_is_disabled(): void
    {
        // This rule is disabled - readonly is optional
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public string $name,  // Not readonly - but that's OK
    ) {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        // Rule is disabled, always passes
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_also_passes_with_readonly(): void
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
}
