<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\DataClumpToValueObjectProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\ArgumentGroupCensus;
use JesseGall\CodeCommandments\Tests\TestCase;

class DataClumpToValueObjectProphetTest extends TestCase
{
    private DataClumpToValueObjectProphet $prophet;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new DataClumpToValueObjectProphet;
        ArgumentGroupCensus::flush();

        $this->root = sys_get_temp_dir() . '/cc-clump-' . uniqid();
        @mkdir($this->root . '/src', 0755, true);
        file_put_contents($this->root . '/composer.json', '{}');
    }

    public function test_flags_a_trio_that_travels_across_sites_and_files(): void
    {
        // ($start, $end, $tz) passed at 3 sites across 2 files.
        $this->write('A.php', '<?php namespace App; class A { function a($start, $end, $tz, $svc) { $svc->report($start, $end, $tz); $svc->export($start, $end, $tz); } }');
        $this->write('B.php', '<?php namespace App; class B { function b($start, $end, $tz, $svc) { $svc->chart($start, $end, $tz); } }');

        $judgment = $this->judge('A.php');

        $this->assertTrue($judgment->hasWarnings());
        $clump = $judgment->warnings[0];
        $this->assertSame('data-clump:$end,$start,$tz', $clump->symbol);
        $this->assertStringContainsString('travel together', $clump->message);
    }

    public function test_does_not_flag_a_tuple_in_only_one_file(): void
    {
        $this->write('A.php', '<?php namespace App; class A { function a($x, $y, $z, $svc) { $svc->p($x, $y, $z); $svc->q($x, $y, $z); $svc->r($x, $y, $z); } }');

        $this->assertTrue($this->judge('A.php')->isRighteous(), 'co-travel within one file is incidental');
    }

    public function test_does_not_flag_fewer_than_three_values(): void
    {
        $this->write('A.php', '<?php namespace App; class A { function a($x, $y, $svc) { $svc->p($x, $y); } }');
        $this->write('B.php', '<?php namespace App; class B { function b($x, $y, $svc) { $svc->q($x, $y); $svc->r($x, $y); } }');

        $this->assertTrue($this->judge('A.php')->isRighteous());
    }

    public function test_does_not_flag_a_framework_pipeline_signature(): void
    {
        $this->write('A.php', '<?php namespace App; class A { function a($context, $next, $extra, $svc) { $svc->p($context, $next, $extra); } }');
        $this->write('B.php', '<?php namespace App; class B { function b($context, $next, $extra, $svc) { $svc->q($context, $next, $extra); $svc->r($context, $next, $extra); } }');

        $this->assertTrue($this->judge('A.php')->isRighteous(), '$next marks a pipeline signature, not a data clump');
    }

    public function test_does_not_flag_non_simple_arguments(): void
    {
        $this->write('A.php', '<?php namespace App; class A { function a($svc) { $svc->p(foo(), bar(), baz()); } }');
        $this->write('B.php', '<?php namespace App; class B { function b($svc) { $svc->q(foo(), bar(), baz()); $svc->r(foo(), bar(), baz()); } }');

        $this->assertTrue($this->judge('A.php')->isRighteous(), 'computed args are not a simple value tuple');
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function write(string $name, string $code): void
    {
        file_put_contents($this->root . '/src/' . $name, $code);
    }

    private function judge(string $name): Judgment
    {
        $file = $this->root . '/src/' . $name;

        return $this->prophet->judge($file, (string) file_get_contents($file));
    }
}
