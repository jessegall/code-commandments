<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\MigrationSchemaIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class MigrationSchemaIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MigrationSchemaIndex::flush();
    }

    public function test_maps_blueprint_columns_to_type_categories_and_merges_alters(): void
    {
        $root = $this->tempProject([
            'database/migrations/0001_create_orders_table.php' => <<<'PHP'
            <?php
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            return new class extends \Illuminate\Database\Migrations\Migration {
                public function up(): void {
                    Schema::create('orders', function (Blueprint $table) {
                        $table->id();
                        $table->string('reference')->unique();
                        $table->json('meta');
                        $table->boolean('paid');
                        $table->decimal('total', 10, 2);
                        $table->timestamps();
                        $table->softDeletes();
                    });
                }
            };
            PHP,
            'database/migrations/0002_add_notes_to_orders.php' => <<<'PHP'
            <?php
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            return new class extends \Illuminate\Database\Migrations\Migration {
                public function up(): void {
                    Schema::table('orders', function (Blueprint $table) {
                        $table->jsonb('audit');
                    });
                }
            };
            PHP,
        ]);

        $index = MigrationSchemaIndex::forFile($root . '/app/Models/Order.php');
        $cols = $index->columnsOf('orders');

        $this->assertTrue($index->hasTable('orders'));
        $this->assertSame('int', $cols['id']);
        $this->assertSame('string', $cols['reference']);
        $this->assertSame('json', $cols['meta']);
        $this->assertSame('bool', $cols['paid']);
        $this->assertSame('decimal', $cols['total']);
        $this->assertSame('datetime', $cols['created_at']);
        $this->assertSame('datetime', $cols['updated_at']);
        $this->assertSame('datetime', $cols['deleted_at']);
        $this->assertSame('json', $cols['audit'], 'an alter migration merges onto the table');
    }

    public function test_is_empty_without_a_migrations_dir(): void
    {
        $this->assertTrue(MigrationSchemaIndex::forFile(sys_get_temp_dir() . '/none-' . uniqid() . '/x.php')->isEmpty());
    }

    /**
     * @param  array<string, string>  $files
     */
    private function tempProject(array $files): string
    {
        $root = sys_get_temp_dir() . '/cc-msi-' . uniqid();
        @mkdir($root . '/app/Models', 0755, true);
        file_put_contents($root . '/composer.json', '{}');

        foreach ($files as $relative => $content) {
            $full = $root . '/' . $relative;
            @mkdir(\dirname($full), 0755, true);
            file_put_contents($full, $content);
        }

        return $root;
    }
}
