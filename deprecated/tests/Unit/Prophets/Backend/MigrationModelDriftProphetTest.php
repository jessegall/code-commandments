<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\MigrationModelDriftProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\MigrationSchemaIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class MigrationModelDriftProphetTest extends TestCase
{
    private MigrationModelDriftProphet $prophet;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new MigrationModelDriftProphet;
        MigrationSchemaIndex::flush();

        $this->root = sys_get_temp_dir() . '/cc-mmd-' . uniqid();
        @mkdir($this->root . '/app/Models', 0755, true);
        @mkdir($this->root . '/database/migrations', 0755, true);
        file_put_contents($this->root . '/composer.json', '{}');
        file_put_contents($this->root . '/database/migrations/0001_create_orders_table.php', <<<'PHP'
        <?php
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;
        return new class extends \Illuminate\Database\Migrations\Migration {
            public function up(): void {
                Schema::create('orders', function (Blueprint $table) {
                    $table->id();
                    $table->string('reference');
                    $table->json('meta');
                    $table->boolean('paid');
                    $table->decimal('total', 10, 2);
                    $table->timestamp('shipped_at')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        };
        PHP);
    }

    public function test_flags_an_uncast_json_boolean_datetime_and_decimal_column(): void
    {
        $judgment = $this->judgeModel('Order', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        class Order extends Model {
            protected $fillable = ['reference', 'meta', 'paid', 'total', 'shipped_at'];
        }
        PHP);

        $cols = array_map(fn ($w) => $w->symbol, $judgment->warnings);
        sort($cols);
        $this->assertSame([
            'migration-model-drift:orders.meta',
            'migration-model-drift:orders.paid',
            'migration-model-drift:orders.shipped_at',
            'migration-model-drift:orders.total',
        ], $cols, 'fires for json/bool/datetime/decimal, but NOT id/string/timestamps/softDeletes');
    }

    public function test_does_not_flag_columns_that_are_cast(): void
    {
        $judgment = $this->judgeModel('Order', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        class Order extends Model {
            protected function casts(): array {
                return ['meta' => 'array', 'paid' => 'boolean', 'total' => 'decimal:2', 'shipped_at' => 'datetime'];
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_when_handled_by_dates_or_accessors(): void
    {
        $judgment = $this->judgeModel('Order', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        use Illuminate\Database\Eloquent\Casts\Attribute;
        class Order extends Model {
            protected $dates = ['shipped_at'];
            protected $casts = ['paid' => 'boolean'];
            public function getMetaAttribute($v) { return json_decode($v, true); }
            protected function total(): Attribute { return Attribute::make(); }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_model_whose_table_is_not_in_migrations(): void
    {
        $judgment = $this->judgeModel('Widget', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        class Widget extends Model { protected $fillable = ['meta']; }
        PHP);

        $this->assertTrue($judgment->isRighteous(), 'no widgets table declared → cannot verify');
    }

    public function test_does_not_flag_a_model_on_a_custom_base(): void
    {
        $judgment = $this->judgeModel('Order', <<<'PHP'
        <?php
        namespace App\Models;
        class Order extends BaseModel { protected $fillable = ['meta', 'paid']; }
        PHP);

        $this->assertTrue($judgment->isRighteous(), 'casts may be inherited from a custom base');
    }

    public function test_resolves_an_explicit_table_property(): void
    {
        $judgment = $this->judgeModel('OrderRow', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        class OrderRow extends Model {
            protected $table = 'orders';
            protected $fillable = ['meta'];
        }
        PHP);

        $this->assertSame('migration-model-drift:orders.meta', $judgment->warnings[0]->symbol ?? null);
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judgeModel(string $class, string $code): Judgment
    {
        $file = $this->root . '/app/Models/' . $class . '.php';
        file_put_contents($file, $code);

        return $this->prophet->judge($file, $code);
    }
}
