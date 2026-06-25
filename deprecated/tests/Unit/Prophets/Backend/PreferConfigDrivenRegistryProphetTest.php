<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferConfigDrivenRegistryProphet;
use JesseGall\CodeCommandments\Support\ConfigMapIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferConfigDrivenRegistryProphetTest extends TestCase
{
    private PreferConfigDrivenRegistryProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferConfigDrivenRegistryProphet;
        ConfigMapIndex::flush();
    }

    public function test_flags_a_backed_enum_whose_cases_mirror_a_config_map(): void
    {
        $root = $this->tempProject([
            'config/services.php' => "<?php\nreturn ['providers' => ['anthropic' => ['key' => 'x'], 'openai' => ['key' => 'y']]];\n",
        ]);

        $file = $root . '/src/AiProvider.php';
        $code = "<?php\nenum AiProvider: string { case Anthropic = 'anthropic'; case OpenAi = 'openai'; }";
        file_put_contents($file, $code);

        $judgment = $this->prophet->judge($file, $code);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('services.providers', $judgment->warnings[0]->message);
        $this->assertSame('config-mirrored-enum:AiProvider', $judgment->warnings[0]->symbol);
    }

    public function test_matches_case_names_when_the_enum_is_not_backed(): void
    {
        $root = $this->tempProject([
            'config/services.php' => "<?php\nreturn ['providers' => ['anthropic' => [], 'openai' => []]];\n",
        ]);

        $file = $root . '/src/Provider.php';
        // pure (non-backed) enum — tokens come from case NAMES (case-insensitive)
        $code = "<?php\nenum Provider { case Anthropic; case OpenAi; }";
        file_put_contents($file, $code);

        $this->assertCount(1, $this->prophet->judge($file, $code)->warnings);
    }

    public function test_does_not_flag_an_enum_with_no_matching_config_map(): void
    {
        $root = $this->tempProject([
            'config/services.php' => "<?php\nreturn ['timeout' => 30, 'retries' => 3];\n",
        ]);

        $file = $root . '/src/Color.php';
        $code = "<?php\nenum Color: string { case Red = 'red'; case Blue = 'blue'; }";
        file_put_contents($file, $code);

        $this->assertTrue($this->prophet->judge($file, $code)->isRighteous());
    }

    public function test_does_not_flag_when_there_is_no_config_at_all(): void
    {
        // No composer.json + config/ ancestor → no config index → never fires.
        $file = sys_get_temp_dir() . '/cc-noconf-' . uniqid() . '.php';
        $code = "<?php\nenum AiProvider: string { case Anthropic = 'anthropic'; case OpenAi = 'openai'; }";
        file_put_contents($file, $code);

        $this->assertTrue($this->prophet->judge($file, $code)->isRighteous());
        @unlink($file);
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    /**
     * @param  array<string, string>  $files
     */
    private function tempProject(array $files): string
    {
        $root = sys_get_temp_dir() . '/cc-cfgreg-' . uniqid();
        @mkdir($root . '/src', 0755, true);
        file_put_contents($root . '/composer.json', '{}');

        foreach ($files as $relative => $content) {
            $full = $root . '/' . $relative;
            @mkdir(\dirname($full), 0755, true);
            file_put_contents($full, $content);
        }

        return $root;
    }
}
