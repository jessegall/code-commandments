<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\HiddenAttributeProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class HiddenAttributeProphetTest extends TestCase
{
    private HiddenAttributeProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new HiddenAttributeProphet();
    }

    public function test_detects_missing_hidden_attribute(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'MissingHiddenAttribute.php');
        // File path must be in Http/View to be checked
        $judgment = $this->prophet->judge('/app/Http/View/Products/ProductsIndexPage.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_with_proper_hidden(): void
    {
        $content = $this->getFixtureContent('Backend', 'Righteous', 'ProperHiddenAttribute.php');
        $judgment = $this->prophet->judge('/app/Http/View/Products/ProductsIndexPage.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_view_files(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

use Spatie\LaravelData\Attributes\FromContainer;

class UserService
{
    #[FromContainer(SomeClass::class)]
    public readonly SomeClass $service;
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_from_container_without_hidden(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View\Users;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UsersIndexPage extends Data
{
    public function __construct(
        #[FromContainer(\App\Repositories\UserRepository::class)]
        public readonly \App\Repositories\UserRepository $repository,
    ) {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/Users/UsersIndexPage.php', $content);
        $this->assertTrue($judgment->isFallen());
    }
}
