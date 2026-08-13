<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Sins;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\InjectedServiceNotHidden;
use JesseGall\CodeCommandments\Sins\Scaffold;
use PHPUnit\Framework\TestCase;

/**
 * The scaffolded transformer has to name classes the INSTALLED vendor declares (#473). The two
 * spatie/typescript-transformer majors expose different seams, so writing the wrong one is not dead
 * code — it extends a class that does not exist, and the generated file is a fatal.
 */
final class HiddenAwareTransformerScaffoldTest extends TestCase
{
    public function test_every_scaffold_it_offers_is_valid_php(): void
    {
        foreach (new InjectedServiceNotHidden()->scaffolds() as $scaffold) {
            $file = tempnam(sys_get_temp_dir(), 'cc-stub') . '.php';
            file_put_contents($file, $scaffold->render('App\\TypeScript'));

            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $status);
            unlink($file);

            $this->assertSame(0, $status, "{$scaffold->stub} is not valid PHP:\n" . implode("\n", $out));
        }
    }

    public function test_each_major_gets_the_transformer_whose_seam_it_declares(): void
    {
        // Read straight off the stubs, so the pair can never drift into naming a base class the
        // other major ships. v3 composes a class-property processor under AttributedClassTransformer;
        // v2 declares neither, and a Data class is transformed by laravel-data's own transformer.
        $this->assertStringContainsString(
            'extends AttributedClassTransformer',
            $this->stub('HiddenAwareAttributedClassTransformer.php.stub'),
        );

        $v2 = $this->stub('HiddenAwareDataTypeScriptTransformer.php.stub');

        $this->assertStringContainsString('extends DataTypeScriptTransformer', $v2);
        $this->assertStringContainsString('protected function resolveProperties', $v2, 'the seam v2 actually declares');
        $this->assertStringNotContainsString('classPropertyProcessors', $v2, 'a method no v2 class has');
    }

    private function stub(string $name): string
    {
        return new Scaffold("TypeScript/{$name}", $name)->render('App\\TypeScript');
    }
}
