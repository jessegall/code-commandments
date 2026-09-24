<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\Detectors\CSharp\NamespaceCycleDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\TypeScript\DuplicateFunctionDetector;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Testing\Comparison;
use JesseGall\CodeCommandments\Testing\Example;
use JesseGall\CodeCommandments\Testing\ExampleFiles;
use PHPUnit\Framework\TestCase;

/**
 * A fixture file marked `@example Name bad|good` is published whole as that half of the rule's example,
 * in place of what the declaration markers carve out — the way to show a sin that lives outside one
 * declaration, like the import that crosses a layer.
 */
final class ExampleFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/example-files-' . uniqid();
        mkdir("{$this->root}/Rewards", recursive: true);
        mkdir("{$this->root}/Loyalty", recursive: true);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), [...glob("{$this->root}/*/*"), ...glob("{$this->root}/*.*")]);
        array_map(rmdir(...), glob("{$this->root}/*"));
        rmdir($this->root);
    }

    public function test_marked_files_replace_the_carved_example_whole(): void
    {
        file_put_contents("{$this->root}/Rewards/Vouchers.cs", <<<'CS'
            // @example NamespaceCycle bad
            namespace Shop.Rewards;

            public sealed class VoucherCatalog
            {
                // @sin NamespaceCycle
                public string IssueTo(Shop.Loyalty.Member member) => member.Id;
            }
            CS);
        file_put_contents("{$this->root}/Loyalty/Members.cs", <<<'CS'
            // @example NamespaceCycle bad
            namespace Shop.Loyalty;

            using Shop.Rewards;
            CS);
        file_put_contents("{$this->root}/Rewards/Issuing.cs", <<<'CS'
            // @example NamespaceCycle good
            namespace Shop.Rewards;
            CS);

        $detector = new NamespaceCycleDetector();
        $carved = [$detector::class => [new Example(new Comparison('carved bad', 'carved good'), Language::CSharp)]];

        [$example] = ExampleFiles::in($this->root)->over($carved, [$detector])[$detector::class];

        $this->assertSame(<<<'CS'
            // in Loyalty/Members.cs
            namespace Shop.Loyalty;

            using Shop.Rewards;

            // in Rewards/Vouchers.cs
            namespace Shop.Rewards;

            public sealed class VoucherCatalog
            {
                public string IssueTo(Shop.Loyalty.Member member) => member.Id;
            }
            CS, $example->bad());
        $this->assertSame("// in Rewards/Issuing.cs\nnamespace Shop.Rewards;", $example->good());
    }

    public function test_a_component_loses_the_markers_in_its_script_too(): void
    {
        file_put_contents("{$this->root}/Signup.vue", <<<'VUE'
            <!-- @example DuplicateFunction bad -->
            <script setup lang="ts">
            // @sin DuplicateFunction
            async function subscribe(): Promise<void> {}
            </script>
            VUE);

        $detector = new DuplicateFunctionDetector();

        [$example] = ExampleFiles::in($this->root)->over([], [$detector])[$detector::class];

        $this->assertSame("<!-- in Signup.vue -->\n<script setup lang=\"ts\">\nasync function subscribe(): Promise<void> {}\n</script>", $example->bad());
    }

    public function test_a_rule_with_no_example_file_keeps_its_carved_example(): void
    {
        $detector = new NamespaceCycleDetector();
        $carved = [$detector::class => [new Example(new Comparison('carved bad', 'carved good'), Language::CSharp)]];

        $this->assertSame($carved, ExampleFiles::in($this->root)->over($carved, [$detector]));
    }
}
