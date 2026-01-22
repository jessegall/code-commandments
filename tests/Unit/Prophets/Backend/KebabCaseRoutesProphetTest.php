<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\KebabCaseRoutesProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class KebabCaseRoutesProphetTest extends TestCase
{
    private KebabCaseRoutesProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new KebabCaseRoutesProphet();
    }

    public function test_detects_camel_case_routes(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/userProfile', [UserController::class, 'profile']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('userProfile', $judgment->sins[0]->message);
    }

    public function test_detects_snake_case_routes(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/user_profile', [UserController::class, 'profile']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('user_profile', $judgment->sins[0]->message);
    }

    public function test_detects_pascal_case_routes(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/UserProfile', [UserController::class, 'profile']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_uppercase_routes(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/USERS', [UserController::class, 'index']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_kebab_case_routes(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/user-profile', [UserController::class, 'profile']);
Route::post('/order-items', [OrderController::class, 'store']);
Route::get('/api-v2/users', [ApiController::class, 'users']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_single_word_lowercase_routes(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/users', [UserController::class, 'index']);
Route::get('/orders', [OrderController::class, 'index']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_route_parameters(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/users/{userId}', [UserController::class, 'show']);
Route::get('/orders/{orderId}/items/{itemId}', [OrderController::class, 'item']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_issues_in_route_prefix(): void
    {
        $content = <<<'PHP'
<?php
Route::prefix('adminPanel')->group(function () {
    Route::get('/users', [AdminController::class, 'users']);
});
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('adminPanel', $judgment->sins[0]->message);
    }

    public function test_detects_all_http_methods(): void
    {
        $content = <<<'PHP'
<?php
Route::post('/createUser', [UserController::class, 'store']);
Route::put('/updateUser', [UserController::class, 'update']);
Route::patch('/patchUser', [UserController::class, 'patch']);
Route::delete('/deleteUser', [UserController::class, 'destroy']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(4, $judgment->sinCount());
    }

    public function test_skips_non_route_files(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/userProfile', [UserController::class, 'profile']);
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_kebab_case_suggestion(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/userProfile', [UserController::class, 'profile']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('user-profile', $judgment->sins[0]->suggestion);
    }

    public function test_handles_api_routes_file(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/getUserData', [ApiController::class, 'data']);
PHP;

        $judgment = $this->prophet->judge('/routes/api.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_routes_with_numbers(): void
    {
        $content = <<<'PHP'
<?php
Route::get('/api-v2/users', [ApiController::class, 'users']);
Route::get('/oauth2/callback', [OAuthController::class, 'callback']);
PHP;

        $judgment = $this->prophet->judge('/routes/web.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
