<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoInlineValidationProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoInlineValidationProphetTest extends TestCase
{
    private NoInlineValidationProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoInlineValidationProphet();
    }

    public function test_detects_inline_validation(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'InlineValidation.php');
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThanOrEqual(1, $judgment->sinCount());
    }

    public function test_passes_form_request(): void
    {
        $content = $this->getFixtureContent('Backend', 'Righteous', 'ProperValidation.php');
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_request_validate(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);
        return 'done';
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

class ValidationService
{
    public function validate($request)
    {
        $request->validate(['name' => 'required']);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/ValidationService.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_inline_validation_in_laravel_11_controller(): void
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
        $request->validate(['name' => 'required']);
        return 'done';
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
    }
}
