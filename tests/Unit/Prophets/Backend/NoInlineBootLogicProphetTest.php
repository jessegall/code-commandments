<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoInlineBootLogicProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoInlineBootLogicProphetTest extends TestCase
{
    private NoInlineBootLogicProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoInlineBootLogicProphet();
    }

    public function test_detects_inline_logic_in_creating_hook(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Shop $shop) {
            if (! $shop->config) {
                $shop->config = ShopConfigData::from(['type' => $shop->type]);
            }
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/Shop.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('creating', $judgment->sins[0]->message);
    }

    public function test_detects_inline_logic_in_deleting_hook(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::deleting(function (Shop $shop) {
            $shop->orders()->delete();
            app(ShopResourceRepository::class)->deleteByShopIdAndType($shop->id, ResourceType::ORDER);
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/Shop.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('deleting', $judgment->sins[0]->message);
    }

    public function test_detects_multiple_hooks_with_inline_logic(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Shop $shop) {
            $shop->config = ShopConfigData::from(['type' => $shop->type]);
        });

        static::deleting(function (Shop $shop) {
            $shop->orders()->delete();
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/Shop.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(2, $judgment->sinCount());
    }

    public function test_passes_event_only_hooks(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected static function booted(): void
    {
        static::created(function (Device $device) {
            event(new DeviceRegistered($device));
        });

        static::deleted(function (Device $device) {
            event(new DeviceUnregistered($device));
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/Device.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_empty_hooks(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected static function booted(): void
    {
        static::created(function (User $user) {
            // TODO: implement
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/User.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_model_without_boot_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email'];
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/User.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_model_files(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class ShopService
{
    protected static function boot()
    {
        static::creating(function ($item) {
            $item->doSomething();
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/ShopService.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_in_booted_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            $order->total = $order->calculateTotal();
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/Order.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('saving', $judgment->sins[0]->message);
    }

    public function test_detects_updated_hook_with_logic(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::updated(function (Product $product) {
            Cache::forget("product.{$product->id}");
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/Product.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('updated', $judgment->sins[0]->message);
    }

    public function test_passes_multiple_event_calls(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected static function booted(): void
    {
        static::created(function (Shop $shop) {
            event(new ShopCreated($shop));
            event(new NotifyAdmins($shop));
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/Shop.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_model_by_extends_keyword(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Domain\Shop;

use Illuminate\Database\Eloquent\Model;

class ShopEntity extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($shop) {
            $shop->slug = Str::slug($shop->name);
        });
    }
}
PHP;

        // Not in Models directory but extends Model
        $judgment = $this->prophet->judge('/app/Domain/Shop/ShopEntity.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_authenticatable_model(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function (User $user) {
            $user->api_token = Str::random(60);
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/User.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_event_with_new_keyword(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected static function booted(): void
    {
        static::created(function (Shop $shop) {
            event(new \App\Events\ShopCreated($shop));
        });
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Models/Shop.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }
}
