<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ControllerPrivateMethodsProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ControllerPrivateMethodsProphetTest extends TestCase
{
    private ControllerPrivateMethodsProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ControllerPrivateMethodsProphet();
    }

    public function test_detects_too_many_private_methods(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store()
    {
        $this->methodOne();
        $this->methodTwo();
        $this->methodThree();
        $this->methodFour();
    }

    private function methodOne()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodTwo()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodThree()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodFour()
    {
        // Line 1
        // Line 2
        // Line 3
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
        $this->assertStringContainsString('4 private methods', $judgment->sins[0]->message);
        $this->assertStringContainsString('methodOne', $judgment->sins[0]->message);
        $this->assertStringContainsString('methodFour', $judgment->sins[0]->message);
    }

    public function test_passes_with_acceptable_number_of_private_methods(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store()
    {
        $this->methodOne();
        $this->methodTwo();
    }

    private function methodOne()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodTwo()
    {
        // Line 1
        // Line 2
        // Line 3
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_short_private_methods(): void
    {
        // Default min_method_lines is 3, so 2-line methods should be ignored
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store()
    {
        $this->a();
        $this->b();
        $this->c();
        $this->d();
        $this->e();
    }

    private function a() { return 1; }
    private function b() { return 2; }
    private function c() { return 3; }
    private function d() { return 4; }
    private function e() { return 5; }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_public_and_protected_methods(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store() {}
    public function show() {}
    public function update() {}
    public function destroy() {}

    protected function helperOne()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    protected function helperTwo()
    {
        // Line 1
        // Line 2
        // Line 3
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
    public function create()
    {
        $this->methodOne();
        $this->methodTwo();
        $this->methodThree();
        $this->methodFour();
    }

    private function methodOne()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodTwo()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodThree()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodFour()
    {
        // Line 1
        // Line 2
        // Line 3
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_configurable_max_private_methods_threshold(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    private function methodOne()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodTwo()
    {
        // Line 1
        // Line 2
        // Line 3
    }
}
PHP;

        // With default threshold (3), this should pass
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());

        // With stricter threshold (1), this should fail
        $this->prophet->configure(['max_private_methods' => 1]);
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_configurable_min_method_lines_threshold(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    private function methodOne()
    {
        return 1;
    }

    private function methodTwo()
    {
        return 2;
    }

    private function methodThree()
    {
        return 3;
    }

    private function methodFour()
    {
        return 4;
    }
}
PHP;

        // With default min_method_lines (3), these 3-line methods count
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());

        // With higher min_method_lines (5), these methods should be ignored
        $this->prophet = new ControllerPrivateMethodsProphet();
        $this->prophet->configure(['min_method_lines' => 5]);
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_suggestion(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    private function a()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function b()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function c()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function d()
    {
        // Line 1
        // Line 2
        // Line 3
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertNotNull($judgment->sins[0]->suggestion);
        $this->assertStringContainsString('service', strtolower($judgment->sins[0]->suggestion));
    }

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertStringContainsString('private', strtolower($this->prophet->description()));
    }

    public function test_handles_controller_extending_base_controller(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

class UserController extends Controller
{
    private function methodOne()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodTwo()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodThree()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodFour()
    {
        // Line 1
        // Line 2
        // Line 3
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_exactly_at_threshold_passes(): void
    {
        // Default threshold is 3, so exactly 3 methods should pass
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class UserController extends Controller
{
    private function methodOne()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodTwo()
    {
        // Line 1
        // Line 2
        // Line 3
    }

    private function methodThree()
    {
        // Line 1
        // Line 2
        // Line 3
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }
}
