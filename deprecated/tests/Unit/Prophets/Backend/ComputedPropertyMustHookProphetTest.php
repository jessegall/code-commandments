<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ComputedPropertyMustHookProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ComputedPropertyMustHookProphetTest extends TestCase
{
    private ComputedPropertyMustHookProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ComputedPropertyMustHookProphet();
    }

    public function test_detects_computed_property_set_in_constructor(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class SearchData extends Data
{
    #[Computed]
    public string|null $search;

    public function __construct(
        public RequestData $request,
    ) {
        $this->search = $this->request->getSearch();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/SearchData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
        $this->assertStringContainsString('search', $judgment->sins[0]->message);
    }

    public function test_detects_multiple_computed_properties_set_in_constructor(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class OrderData extends Data
{
    #[Computed]
    public float $total;

    #[Computed]
    public string $summary;

    public function __construct(
        public array $items,
    ) {
        $this->total = array_sum(array_column($this->items, 'price'));
        $this->summary = count($this->items) . ' items';
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/OrderData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(2, $judgment->sinCount());
    }

    public function test_passes_computed_property_with_hook(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class SearchData extends Data
{
    #[Computed]
    public string|null $search {
        get => $this->request->getSearch();
    }

    public function __construct(
        public RequestData $request,
    ) {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/SearchData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_non_computed_property_set_in_constructor(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public string $name;

    public function __construct(
        public string $email,
    ) {
        $this->name = 'Default';
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_class_without_constructor(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class SimpleData extends Data
{
    #[Computed]
    public string $value;
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/SimpleData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_class_without_computed_properties(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/UserData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_empty_constructor(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class EmptyData extends Data
{
    #[Computed]
    public string $value;

    public function __construct() {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/EmptyData.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_with_fully_qualified_attribute(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Data;

class SearchData extends Data
{
    #[\Spatie\LaravelData\Attributes\Computed]
    public string|null $search;

    public function __construct(
        public RequestData $request,
    ) {
        $this->search = $this->request->getSearch();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/SearchData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
    }

    public function test_only_flags_computed_properties_not_regular_ones(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class MixedData extends Data
{
    #[Computed]
    public string $computed;

    public string $regular;

    public function __construct(
        public string $input,
    ) {
        $this->computed = strtoupper($this->input);
        $this->regular = 'some value';
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/MixedData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
        $this->assertStringContainsString('computed', $judgment->sins[0]->message);
    }

    public function test_passes_file_without_class(): void
    {
        $content = <<<'PHP'
<?php

function helper() {
    return 'value';
}
PHP;

        $judgment = $this->prophet->judge('/app/helpers.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }

    public function test_provides_suggestion_for_sin(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Data;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class SearchData extends Data
{
    #[Computed]
    public string|null $search;

    public function __construct(
        public RequestData $request,
    ) {
        $this->search = $this->request->getSearch();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Data/SearchData.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertNotNull($judgment->sins[0]->suggestion);
        $this->assertStringContainsString('property hook', $judgment->sins[0]->suggestion);
    }
}
