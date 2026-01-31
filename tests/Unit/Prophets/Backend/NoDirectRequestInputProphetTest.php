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
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    public function store(StoreUserRequest $request)
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
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    public function store(StoreUserRequest $request)
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
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    public function store(StoreUserRequest $request)
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
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    public function store(StoreUserRequest $request)
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
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    private StoreUserRequest $request;

    public function __construct(StoreUserRequest $request)
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
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    private StoreUserRequest $request;

    public function __construct(StoreUserRequest $request)
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
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    public function __construct(
        private StoreUserRequest $request
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

    public function test_ignores_untyped_parameters(): void
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

    public function test_allows_raw_request(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;

class TrackVisitor
{
    public function handle(Request $request, $next)
    {
        $ip = $request->input('ip');
        if ($request->has('token')) {
            // do something
        }
        return $next($request);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Middleware/TrackVisitor.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_in_non_controller_class(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use App\Http\Requests\StoreUserRequest;
use Spatie\LaravelData\Data;

class SomePage extends Data
{
    public function __construct(
        public readonly StoreUserRequest $request,
    ) {
        $this->init();
    }

    private function init(): void
    {
        $window = $this->request->input('movementWindow', '30d');
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/SomePage.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_in_laravel_11_controller(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
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

    public function test_allows_empty_input_call(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        $all = $request->input();
        return $all;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_allows_empty_query_call(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    public function index(StoreUserRequest $request)
    {
        $params = $request->query();
        return $params;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_query_with_args(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class TestController extends Controller
{
    public function index(StoreUserRequest $request)
    {
        $name = $request->query('name');
        return $name;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_request_helper_function(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

class TestController extends Controller
{
    public function store()
    {
        $name = request()->input('name');
        return $name;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_allows_empty_input_on_request_helper(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

class TestController extends Controller
{
    public function store()
    {
        $all = request()->input();
        return $all;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
