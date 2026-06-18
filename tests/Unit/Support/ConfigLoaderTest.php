<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use Illuminate\Container\Container;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Tests\TestCase;

class ConfigLoaderTest extends TestCase
{
    /**
     * #43: a Laravel app's commandments.php may use app_path(); under the
     * standalone CLI no Application is bound, so app_path() fataled with
     * "Container::path() undefined". ConfigLoader::load now binds a minimal
     * Application rooted at the project so those helpers resolve.
     */
    public function test_load_resolves_app_path_when_no_application_is_bound(): void
    {
        $original = Container::getInstance();
        $base = sys_get_temp_dir() . '/cc-cfgloader-' . uniqid();
        @mkdir($base, 0755, true);
        $configPath = $base . '/commandments.php';
        file_put_contents($configPath, <<<'PHP'
        <?php
        return ['scaffold' => ['path' => app_path('Support')]];
        PHP);

        try {
            // Simulate the standalone CLI: no bound Application.
            Container::setInstance(null);
            Environment::setBasePath($base);

            $config = ConfigLoader::load($configPath);

            $this->assertSame($base . '/app/Support', $config['scaffold']['path']);
        } finally {
            Container::setInstance($original);
            @unlink($configPath);
            @rmdir($base);
        }
    }
}
