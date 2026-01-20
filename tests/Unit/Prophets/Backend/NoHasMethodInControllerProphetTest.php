<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoHasMethodInControllerProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoHasMethodInControllerProphetTest extends TestCase
{
    private NoHasMethodInControllerProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoHasMethodInControllerProphet();
    }

    public function test_detects_has_method(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'HasMethod.php');
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThanOrEqual(1, $judgment->sinCount());
    }

    public function test_passes_proper_request_handling(): void
    {
        $content = $this->getFixtureContent('Backend', 'Righteous', 'ProperRequestHandling.php');
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_filled_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function store(Request $request)
    {
        if ($request->filled('name')) {
            return 'has name';
        }
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_boolean_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function store(Request $request)
    {
        $active = $request->boolean('active');
        return $active;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_skips_non_controller(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class UserService
{
    public function check($request)
    {
        if ($request->has('email')) {
            return true;
        }
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }
}
