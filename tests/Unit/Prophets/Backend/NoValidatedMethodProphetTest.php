<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoValidatedMethodProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoValidatedMethodProphetTest extends TestCase
{
    private NoValidatedMethodProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoValidatedMethodProphet();
    }

    public function test_detects_validated_method(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'ValidatedMethod.php');
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThanOrEqual(1, $judgment->sinCount());
    }

    public function test_passes_typed_getters(): void
    {
        $content = $this->getFixtureContent('Backend', 'Righteous', 'ProperTypedGetters.php');
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_validated_call(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreRequest;

class TestController extends Controller
{
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        return $data;
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

class DataService
{
    public function process($request)
    {
        return $request->validated();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/DataService.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_validated_in_laravel_11_controller(): void
    {
        // Laravel 11+ controllers don't extend Illuminate\Routing\Controller
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreRequest;

class UserController extends Controller
{
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        return $data;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isFallen());
    }
}
