<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoRequestDataPassthroughProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoRequestDataPassthroughProphetTest extends TestCase
{
    private NoRequestDataPassthroughProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoRequestDataPassthroughProphet;
    }

    public function test_detects_request_values_passed_to_data_from(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'RequestDataPassthrough.php');
        $judgment = $this->prophet->judge('/app/Http/Controllers/ExportTemplateController.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThanOrEqual(2, $judgment->sinCount());
    }

    public function test_passes_when_no_request_values_in_data_from(): void
    {
        $content = $this->getFixtureContent('Backend', 'Righteous', 'ProperRequestDataInjection.php');
        $judgment = $this->prophet->judge('/app/Http/Controllers/ExportTemplateController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_single_request_value_in_from(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    public function create(StoreUserRequest $request)
    {
        return UserPage::from([
            'search' => $request->getSearch(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
    }

    public function test_ignores_non_request_values_in_from(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;

class UserController extends Controller
{
    public function edit(StoreUserRequest $request, User $user)
    {
        return UserPage::from([
            'userId' => $user->id,
            'name' => $user->name,
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_mixed_request_and_non_request_values(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\EditorRequest;
use App\Models\Template;

class TemplateController extends Controller
{
    public function edit(EditorRequest $request, Template $template)
    {
        return EditorPage::from([
            'templateId' => $template->id,
            'previewColumns' => $request->getPreviewColumns(),
            'previewLimit' => $request->getPreviewLimit(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TemplateController.php', $content);
        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
    }

    public function test_ignores_from_without_array_argument(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    public function create(StoreUserRequest $request)
    {
        return UserPage::from($request);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_non_controller_files(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;
use App\Http\Requests\StoreUserRequest;

class UserService
{
    public function create(StoreUserRequest $request)
    {
        return UserPage::from([
            'search' => $request->getSearch(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_request_helper_function(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

class UserController extends Controller
{
    public function create()
    {
        return UserPage::from([
            'search' => request()->getSearch(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_this_request_property(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    private StoreUserRequest $request;

    public function __construct(StoreUserRequest $request)
    {
        $this->request = $request;
    }

    public function create()
    {
        return UserPage::from([
            'search' => $this->request->getSearch(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_promoted_property_request(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    public function __construct(
        private StoreUserRequest $request,
    ) {}

    public function create()
    {
        return UserPage::from([
            'search' => $this->request->getSearch(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_ignores_model_create_with_request_values(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        User::query()->create([
            'name' => $request->getName(),
            'email' => $request->getEmail(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_sin_message_contains_class_name_and_keys(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\EditorRequest;

class TemplateController extends Controller
{
    public function create(EditorRequest $request)
    {
        return EditorPage::from([
            'previewColumns' => $request->getPreviewColumns(),
            'previewLimit' => $request->getPreviewLimit(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TemplateController.php', $content);
        $this->assertTrue($judgment->isFallen());

        $sin = $judgment->sins[0];
        $this->assertStringContainsString('EditorPage', $sin->message);
        $this->assertStringContainsString('previewColumns', $sin->message);
        $this->assertStringContainsString('previewLimit', $sin->message);
        $this->assertStringContainsString('#[FromContainer]', $sin->suggestion);
        $this->assertStringContainsString('#[Computed]', $sin->suggestion);
    }

    public function test_detects_in_laravel_11_controller(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\EditorRequest;

class TemplateController extends Controller
{
    public function create(EditorRequest $request)
    {
        return EditorPage::from([
            'search' => $request->getSearch(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/TemplateController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_ignores_from_on_non_page_class(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    public function create(StoreUserRequest $request)
    {
        return UserData::from([
            'search' => $request->getSearch(),
        ]);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_from_on_page_class(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    public function create(StoreUserRequest $request)
    {
        return UserSettingsPage::from([
            'search' => $request->getSearch(),
        ]);
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
