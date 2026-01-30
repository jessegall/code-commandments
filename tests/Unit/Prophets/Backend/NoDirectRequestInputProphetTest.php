<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoDirectRequestInputProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoDirectRequestInputProphetTest extends TestCase
{
    private NoDirectRequestInputProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoDirectRequestInputProphet();
    }

    public function test_detects_direct_request_method_calls(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'DirectRequestInput.php');
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

    public function test_detects_has_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function store(Request $request)
    {
        if ($request->has('name')) {
            return 'has name';
        }
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isFallen());
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

    public function test_detects_input_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function store(Request $request)
    {
        $name = $request->input('name');
        return $name;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_this_request_input(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

class TestController extends Controller
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function index()
    {
        $window = $this->request->input('movementWindow', '30d');
        return $window;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_this_request_has(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

class TestController extends Controller
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function index()
    {
        if ($this->request->has('name')) {
            return 'has name';
        }
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_promoted_property_request(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function __construct(
        private Request $request
    ) {}

    public function index()
    {
        $name = $this->request->input('name');
        return $name;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_ignores_non_request_method_calls(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Services\UserService;

class TestController extends Controller
{
    public function __construct(
        private UserService $service
    ) {}

    public function index()
    {
        $result = $this->service->input('test');
        return $result;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isRighteous());
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

    public function test_detects_in_laravel_11_controller(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(Request $request)
    {
        if ($request->has('name')) {
            return 'has name';
        }
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
