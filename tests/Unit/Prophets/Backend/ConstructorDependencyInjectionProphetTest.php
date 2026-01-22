<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ConstructorDependencyInjectionProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ConstructorDependencyInjectionProphetTest extends TestCase
{
    private ConstructorDependencyInjectionProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ConstructorDependencyInjectionProphet();
    }

    public function test_detects_service_in_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(UserService $service)
    {
        return $service->create();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('UserService', $judgment->sins[0]->message);
        $this->assertStringContainsString('constructor', $judgment->sins[0]->suggestion);
    }

    public function test_detects_repository_in_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function index(UserRepository $repository)
    {
        return $repository->all();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('UserRepository', $judgment->sins[0]->message);
    }

    public function test_detects_multiple_dependencies(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(UserService $service, NotificationHandler $handler)
    {
        $user = $service->create();
        $handler->notify($user);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(2, $judgment->sinCount());
    }

    public function test_passes_constructor_injection(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function __construct(
        private UserService $service,
    ) {}

    public function store()
    {
        return $this->service->create();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_allows_request_in_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(Request $request)
    {
        return $request->all();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_allows_form_request_in_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        return $request->validated();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_allows_model_binding_in_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function show(User $user)
    {
        return $user;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_allows_multiple_model_bindings(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Post;
use Illuminate\Routing\Controller;

class PostController extends Controller
{
    public function show(User $user, Post $post)
    {
        return $post;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/PostController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_various_dependency_types(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class TestController extends Controller
{
    public function a(UserFactory $factory) {}
    public function b(OrderBuilder $builder) {}
    public function c(PaymentHandler $handler) {}
    public function d(CacheManager $manager) {}
    public function e(EventDispatcher $dispatcher) {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TestController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(5, $judgment->sinCount());
    }

    public function test_ignores_private_methods(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store()
    {
        return $this->helper(new UserService());
    }

    private function helper(UserService $service)
    {
        return $service->create();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_protected_methods(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    protected function helper(UserService $service)
    {
        return $service->create();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_controller_classes(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class UserService
{
    public function create(UserRepository $repository)
    {
        return $repository->create();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_handles_nullable_types(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(?UserService $service)
    {
        return $service?->create();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('UserService', $judgment->sins[0]->message);
    }

    public function test_handles_fully_qualified_class_names(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(\App\Services\UserService $service)
    {
        return $service->create();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_combined_request_and_service(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(StoreUserRequest $request, UserService $service)
    {
        return $service->create($request->validated());
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
        $this->assertStringContainsString('UserService', $judgment->sins[0]->message);
    }

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertStringContainsString('constructor', strtolower($this->prophet->description()));
    }
}
