<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\QueryModelsThroughQueryMethodProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class QueryModelsThroughQueryMethodProphetTest extends TestCase
{
    private QueryModelsThroughQueryMethodProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new QueryModelsThroughQueryMethodProphet();
    }

    public function test_detects_where_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::where('active', true)->get();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_with_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::with('posts')->get();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_find_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function show(int $id)
    {
        return User::find($id);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_find_or_fail_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function show(int $id)
    {
        return User::findOrFail($id);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_first_where_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function show(string $email)
    {
        return User::firstWhere('email', $email);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_order_by_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::orderBy('name')->get();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_query_entry_point(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::query()->where('active', true)->get();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_all_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::all();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_count_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::count();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_create_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function store()
    {
        return User::create(['name' => 'John']);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_non_model_static_calls(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function index()
    {
        return Cache::get('users');
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_projection_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Domain\Stockpile\Projections\Stockpile;

class StockpileController extends Controller
{
    public function index()
    {
        return Stockpile::with('items')->get();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/StockpileController.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_factory_on_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::factory()->create();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_multiple_sins(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $active = User::where('active', true)->get();
        $latest = User::latest()->first();
        return compact('active', 'latest');
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $content);
        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThanOrEqual(2, $judgment->sinCount());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }

    public function test_repent_fixes_single_static_call(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::where('active', true)->get();
    }
}
PHP;

        $result = $this->prophet->repent('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('User::query()->where(', $result->newContent);
        $this->assertNotEmpty($result->penance);
    }

    public function test_repent_fixes_multiple_static_calls(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $active = User::where('active', true)->get();
        $latest = User::latest()->first();
        return compact('active', 'latest');
    }
}
PHP;

        $result = $this->prophet->repent('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('User::query()->where(', $result->newContent);
        $this->assertStringContainsString('User::query()->latest(', $result->newContent);
        $this->assertCount(2, $result->penance);
    }

    public function test_repent_leaves_allowed_calls_untouched(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::all();
    }
}
PHP;

        $result = $this->prophet->repent('/app/Http/Controllers/UserController.php', $content);

        $this->assertFalse($result->absolved);
    }

    public function test_repent_formats_multi_line_chain(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::where('active', true)
            ->orderBy('name')
            ->get();
    }
}
PHP;

        $result = $this->prophet->repent('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($result->absolved);

        $expected = <<<'PHP'
        return User::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();
PHP;

        $this->assertStringContainsString($expected, $result->newContent);
    }

    public function test_repent_keeps_single_line_chain_inline(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return User::where('active', true)->get();
    }
}
PHP;

        $result = $this->prophet->repent('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('User::query()->where(\'active\', true)->get()', $result->newContent);
    }

    public function test_repent_produces_valid_php(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $users = User::where('active', true)->get();
        $first = User::firstWhere('email', 'test@example.com');
        $count = User::count();
        return compact('users', 'first', 'count');
    }
}
PHP;

        $result = $this->prophet->repent('/app/Http/Controllers/UserController.php', $content);

        $this->assertTrue($result->absolved);

        // Verify the result is still valid PHP by re-judging
        $judgment = $this->prophet->judge('/app/Http/Controllers/UserController.php', $result->newContent);
        $this->assertTrue($judgment->isRighteous());
    }
}
