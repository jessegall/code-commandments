<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoRawRequestProphetTest extends TestCase
{
    private NoRawRequestProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoRawRequestProphet();
    }

    public function test_detects_raw_request_in_controller(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'RawRequestUsage.php');
        $judgment = $this->prophet->judge('/test/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_passes_with_form_request(): void
    {
        $content = $this->getFixtureContent('Backend', 'Righteous', 'ProperRequest.php');
        $judgment = $this->prophet->judge('/test/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_raw_request_type_hint(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function store(Request $request)
    {
        return 'stored';
    }
}
PHP;

        $judgment = $this->prophet->judge('/test/TestController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
    }

    public function test_allows_form_request(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        return $request->getName();
    }
}
PHP;

        $judgment = $this->prophet->judge('/test/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_controller_files(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class UserService
{
    public function process(Request $request)
    {
        return $request;
    }
}
PHP;

        $judgment = $this->prophet->judge('/test/UserService.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }

    public function test_detects_raw_request_in_laravel_11_controller(): void
    {
        // Laravel 11+ controllers don't extend Illuminate\Routing\Controller
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(Request $request)
    {
        return $request->all();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
    }
}
