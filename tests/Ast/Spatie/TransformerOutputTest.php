<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\TransformerOutput;
use PHPUnit\Framework\TestCase;

final class TransformerOutputTest extends TestCase
{
    public function test_it_reads_the_fluent_output_directory_resolved_from_dir(): void
    {
        // The v3 provider form: `->outputDirectory($root . '/…')` with `$root = dirname(__DIR__, n)`.
        $src = <<<'PHP'
        <?php
        class TypeScriptTransformerServiceProvider
        {
            protected function configure($config): void
            {
                $root = dirname(__DIR__, 2);
                $config->outputDirectory($root . '/resources/js/types')->writer(new FlatModuleWriter('generated.ts'));
            }
        }
        PHP;

        $codebase = Codebase::fromString($src, '/proj/app/Providers/TypeScriptTransformerServiceProvider.php');

        // __DIR__ = /proj/app/Providers → dirname(, 2) = /proj → + /resources/js/types.
        $this->assertSame('/proj/resources/js/types', TransformerOutput::locationIn($codebase));
    }

    public function test_it_reads_the_config_output_file_as_a_file(): void
    {
        // The classic config-array form resolves to the single FILE, not its directory.
        $src = <<<'PHP'
        <?php
        return [
            'output_file' => __DIR__ . '/../resources/js/generated.d.ts',
            'writer' => TypeDefinitionWriter::class,
        ];
        PHP;

        $codebase = Codebase::fromString($src, '/proj/config/typescript-transformer.php');

        // __DIR__ = /proj/config → + /../resources/js/generated.d.ts → /proj/resources/js/generated.d.ts.
        $this->assertSame('/proj/resources/js/generated.d.ts', TransformerOutput::locationIn($codebase));
    }

    public function test_an_unresolvable_expression_yields_null(): void
    {
        $src = <<<'PHP'
        <?php
        return ['output_file' => someRuntimeHelper()];
        PHP;

        $codebase = Codebase::fromString($src, '/proj/config/typescript-transformer.php');

        $this->assertNull(TransformerOutput::locationIn($codebase));
    }

    public function test_a_project_without_any_transformer_config_yields_null(): void
    {
        $codebase = Codebase::fromString('<?php class Plain { public int $id; }', '/proj/app/Plain.php');

        $this->assertNull(TransformerOutput::locationIn($codebase));
    }
}
