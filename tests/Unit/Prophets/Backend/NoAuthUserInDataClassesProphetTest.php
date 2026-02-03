<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoAuthUserInDataClassesProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoAuthUserInDataClassesProphetTest extends TestCase
{
    private NoAuthUserInDataClassesProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoAuthUserInDataClassesProphet();
    }

    public function test_detects_auth_helper_user_call(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use Spatie\LaravelData\Data;

class KitchenIndexPage extends Data
{
    private function resolveProducts(): Collection
    {
        $user = auth()->user();
        return collect();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/KitchenIndexPage.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
        $this->assertStringContainsString('auth()', $judgment->sins[0]->message);
        $this->assertStringContainsString('user', $judgment->sins[0]->message);
    }

    public function test_detects_auth_helper_id_call(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use Spatie\LaravelData\Data;

class DashboardPage extends Data
{
    private function getUserId(): ?int
    {
        return auth()->id();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/DashboardPage.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
        $this->assertStringContainsString('auth()', $judgment->sins[0]->message);
        $this->assertStringContainsString('id', $judgment->sins[0]->message);
    }

    public function test_detects_auth_facade_user_call(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\Data;

class ProfilePage extends Data
{
    private function getUser()
    {
        return Auth::user();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/ProfilePage.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
        $this->assertStringContainsString('Auth', $judgment->sins[0]->message);
        $this->assertStringContainsString('user', $judgment->sins[0]->message);
    }

    public function test_detects_auth_facade_id_call(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\Data;

class SettingsPage extends Data
{
    private function getCurrentUserId(): ?int
    {
        return Auth::id();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/SettingsPage.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
        $this->assertStringContainsString('Auth', $judgment->sins[0]->message);
        $this->assertStringContainsString('id', $judgment->sins[0]->message);
    }

    public function test_detects_auth_helper_with_guard(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use Spatie\LaravelData\Data;

class AdminPage extends Data
{
    private function getAdminUser()
    {
        return auth('admin')->user();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/AdminPage.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
    }

    public function test_detects_multiple_auth_calls(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\Data;

class MultiPage extends Data
{
    private function getUser()
    {
        return auth()->user();
    }

    private function getUserId(): ?int
    {
        return Auth::id();
    }

    private function isLoggedIn(): bool
    {
        return auth()->user() !== null;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/MultiPage.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(3, $judgment->sinCount());
    }

    public function test_passes_with_from_authenticated_user_attribute(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use App\Models\User;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Data;

class KitchenIndexPage extends Data
{
    public function __construct(
        #[Hidden]
        #[FromAuthenticatedUser]
        public readonly User|null $user,
    ) {}

    private function resolveProducts(): Collection
    {
        return $this->user !== null
            ? Product::query()->forUser($this->user)->get()
            : Product::query()->get();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/KitchenIndexPage.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_non_data_class(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class UserService
{
    public function getCurrentUser()
    {
        return auth()->user();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_controller_with_auth(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardController
{
    public function index()
    {
        $user = Auth::user();
        return view('dashboard', compact('user'));
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/DashboardController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_data_class_without_auth_calls(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use Spatie\LaravelData\Data;

class ProductPage extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly float $price,
    ) {}

    private function formatPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/ProductPage.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_file_without_class(): void
    {
        $content = <<<'PHP'
<?php

function getAuthUser()
{
    return auth()->user();
}
PHP;

        $judgment = $this->prophet->judge('/app/helpers.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_suggestion(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use Spatie\LaravelData\Data;

class TestPage extends Data
{
    private function getUser()
    {
        return auth()->user();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/TestPage.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('#[FromAuthenticatedUser]', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('#[Hidden]', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('$this->user', $judgment->sins[0]->suggestion);
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertStringContainsString('#[FromAuthenticatedUser]', $this->prophet->detailedDescription());
    }

    public function test_detects_auth_in_computed_property(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\View;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class UserDashboardPage extends Data
{
    #[Computed]
    public string $greeting {
        get {
            $user = auth()->user();
            return $user ? "Hello, {$user->name}" : "Hello, Guest";
        }
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/View/UserDashboardPage.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
    }
}
