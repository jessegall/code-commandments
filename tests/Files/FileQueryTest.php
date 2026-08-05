<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Files;

use JesseGall\CodeCommandments\Ast\Codebase as BackendCodebase;
use JesseGall\CodeCommandments\Files\FileMatch;
use JesseGall\CodeCommandments\Vue\Codebase as FrontendCodebase;
use PHPUnit\Framework\TestCase;

/**
 * A file NAME is judgeable, on both engines, through the one shared query (#445) — a poetic module
 * name is the same sin as a poetic identifier, and a rule could previously only see the declarations
 * inside a file.
 */
final class FileQueryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-files-' . uniqid('', true);
        mkdir($this->root . '/resources/js/scene', 0777, true);
        mkdir($this->root . '/src/Orders', 0777, true);

        file_put_contents($this->root . '/resources/js/scene/standing.ts', 'export type Node = { id: string }');
        file_put_contents($this->root . '/resources/js/scene/OrderCard.vue', '<template><div /></template>');
        file_put_contents($this->root . '/src/Orders/PaymentData.php', '<?php namespace App; class PaymentData {}');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_the_backend_judges_the_names_of_its_files(): void
    {
        $files = BackendCodebase::scan($this->root . '/src')->whereFile()->get();

        $this->assertCount(1, $files);
        $this->assertSame('PaymentData.php', $files[0]->name());
        $this->assertSame('PaymentData', $files[0]->stem());
        $this->assertSame('php', $files[0]->extension());
        $this->assertStringEndsWith('/src/Orders/PaymentData.php:1', $files[0]->location());
    }

    public function test_the_frontend_judges_components_and_the_modules_beside_them(): void
    {
        $stems = array_map(
            static fn (FileMatch $file): string => $file->stem(),
            FrontendCodebase::scan($this->root . '/resources/js')->whereFile()->get(),
        );

        sort($stems);

        $this->assertSame(['OrderCard', 'standing'], $stems);
    }

    public function test_a_rule_narrows_by_extension_and_by_name(): void
    {
        // The reported case: hold module names to the same standard as class names.
        $poetic = FrontendCodebase::scan($this->root . '/resources/js')
            ->whereFile()
            ->withExtension('ts')
            ->where(static fn (FileMatch $file): bool => $file->stem() === strtolower($file->stem()))
            ->get();

        $this->assertCount(1, $poetic);
        $this->assertSame('standing.ts', $poetic[0]->name());
        $this->assertSame('standing.ts', $poetic[0]->scope(), 'the report names the file');
    }

    public function test_a_file_knows_where_it_lives(): void
    {
        $file = new FileMatch('/app/resources/js/scene/client/node.ts');

        $this->assertSame('/app/resources/js/scene/client', $file->directory());
        $this->assertSame(['app', 'resources', 'js', 'scene', 'client', 'node.ts'], $file->segments());
    }
}
