<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoFacadesInServicesProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoFacadesInServicesProphetTest extends TestCase
{
    private NoFacadesInServicesProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoFacadesInServicesProphet();
    }

    private function judge(string $body): \JesseGall\CodeCommandments\Results\Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App\\Services;\n{$body}\n");
    }

    public function test_flags_an_imported_facade(): void
    {
        $j = $this->judge('use Illuminate\Support\Facades\Log; class S { public function h(): void { Log::error("x"); } }');
        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('LoggerInterface', $j->warnings[0]->message);
    }

    public function test_flags_a_fully_qualified_facade(): void
    {
        $j = $this->judge('class S { public function h(): void { \Illuminate\Support\Facades\Cache::put("k", 1); } }');
        $this->assertTrue($j->hasWarnings());
    }

    public function test_does_not_flag_support_helpers(): void
    {
        $j = $this->judge('use Illuminate\Support\Str; class S { public function h(): string { return Str::slug("a b"); } }');
        $this->assertFalse($j->hasWarnings());
    }

    public function test_skips_service_providers(): void
    {
        $j = $this->prophet->judge('/p.php', "<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nuse Illuminate\\Support\\ServiceProvider;\nclass FooServiceProvider extends ServiceProvider { public function boot(): void { Log::info('x'); } }\n");
        $this->assertFalse($j->hasWarnings());
    }

    public function test_respects_allow_list(): void
    {
        $this->prophet->configure(['allow' => ['Cache']]);
        $j = $this->judge('use Illuminate\Support\Facades\Cache; class S { public function h(): void { Cache::put("k", 1); } }');
        $this->assertFalse($j->hasWarnings());
    }
}
