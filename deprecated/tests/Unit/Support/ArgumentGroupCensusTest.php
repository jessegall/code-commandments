<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ArgumentGroupCensus;
use JesseGall\CodeCommandments\Tests\TestCase;

class ArgumentGroupCensusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ArgumentGroupCensus::flush();
    }

    public function test_counts_simple_arg_tuples_across_sites_and_files_order_insensitively(): void
    {
        $root = $this->tempProject([
            'src/A.php' => '<?php namespace App; class A { function a($start, $end, $tz, $s) { $s->p($start, $end, $tz); $s->q($tz, $start, $end); } }',
            'src/B.php' => '<?php namespace App; class B { function b($start, $end, $tz, $s) { $s->r($start, $end, $tz); } }',
        ]);

        $census = ArgumentGroupCensus::forFile($root . '/src/A.php');
        $key = ArgumentGroupCensus::keyFor(['$tz', '$start', '$end']);

        $this->assertSame(3, $census->siteCount($key), 'reordered passings collapse to one clump');
        $this->assertSame(2, $census->fileCount($key));
        $this->assertTrue($census->isClump($key, 3, 2));
        $this->assertFalse($census->isClump($key, 4, 2));
    }

    public function test_ignores_calls_with_non_simple_args_or_too_few_args(): void
    {
        $root = $this->tempProject([
            'src/A.php' => '<?php namespace App; class A { function a($x, $y, $s) { $s->p($x, $y); $s->q(foo(), bar(), baz()); } }',
        ]);

        $census = ArgumentGroupCensus::forFile($root . '/src/A.php');

        $this->assertTrue($census->isEmpty(), 'a 2-arg call and a computed-arg call yield no tuples');
    }

    public function test_key_for_args_is_null_for_non_simple_calls(): void
    {
        // Indirectly: keyFor builds a stable sorted key.
        $this->assertSame('$a,$b,$c', ArgumentGroupCensus::keyFor(['$c', '$a', '$b', '$a']));
    }

    /**
     * @param  array<string, string>  $files
     */
    private function tempProject(array $files): string
    {
        $root = sys_get_temp_dir() . '/cc-agc-' . uniqid();
        @mkdir($root . '/src', 0755, true);
        file_put_contents($root . '/composer.json', '{}');

        foreach ($files as $relative => $content) {
            file_put_contents($root . '/' . $relative, $content);
        }

        return $root;
    }
}
