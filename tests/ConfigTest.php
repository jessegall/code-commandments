<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use InvalidArgumentException;
use JesseGall\CodeCommandments\Ast\Codebase as AstCodebase;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Backend\DataClumpDetector;
use JesseGall\CodeCommandments\Detectors\Detector as BackendDetector;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Frontend\PropDrilling;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use JesseGall\CodeCommandments\Vue\Detector as FrontendDetector;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    /** @var list<string> */
    private array $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            @unlink($dir . '/.commandments/config.php');
            @rmdir($dir . '/.commandments');
            @rmdir($dir);
        }

        $this->dirs = [];
    }

    public function test_disable_by_detector_class_drops_it(): void
    {
        $config = new Config()->disable(ConfigTunableDetector::class);

        $result = $config->apply([new ConfigTunableDetector], []);

        $this->assertSame([], $result['backend']);
    }

    public function test_disable_by_sin_class_drops_every_detector_for_that_sin(): void
    {
        // ConfigTunableDetector points at the ArrayBag sin — disabling the SIN drops the detector.
        $config = new Config()->disable(ArrayBag::class);

        $result = $config->apply([new ConfigTunableDetector], []);

        $this->assertSame([], $result['backend']);
    }

    public function test_register_adds_a_custom_detector_routed_to_its_engine(): void
    {
        $config = new Config()->register(ConfigTunableDetector::class, ConfigFrontendDetector::class);

        $result = $config->apply([], []);

        $this->assertCount(1, $result['backend']);
        $this->assertInstanceOf(ConfigTunableDetector::class, $result['backend'][0]);
        $this->assertCount(1, $result['frontend']);
        $this->assertInstanceOf(ConfigFrontendDetector::class, $result['frontend'][0]);
    }

    public function test_configure_injects_the_detector_by_its_type_hint(): void
    {
        $config = new Config()->configure(fn (ConfigTunableDetector $d) => $d->limit(42));

        $result = $config->apply([new ConfigTunableDetector], []);

        $this->assertSame(42, $result['backend'][0]->limit);
    }

    public function test_configure_a_registered_detector(): void
    {
        $config = new Config()
            ->register(ConfigTunableDetector::class)
            ->configure(fn (ConfigTunableDetector $d) => $d->limit(7));

        $result = $config->apply([], []);

        $this->assertSame(7, $result['backend'][0]->limit);
    }

    public function test_configure_an_unknown_or_disabled_detector_throws(): void
    {
        $config = new Config()->configure(fn (ConfigTunableDetector $d) => $d->limit(1));

        $this->expectException(InvalidArgumentException::class);

        $config->apply([], []); // never registered → not in the set
    }

    public function test_configure_without_a_type_hint_throws(): void
    {
        $config = new Config()->configure(fn ($d) => null);

        $this->expectException(InvalidArgumentException::class);

        $config->apply([new ConfigTunableDetector], []);
    }

    public function test_load_reads_the_config_file_from_a_project(): void
    {
        $dir = $this->project(
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nuse JesseGall\\CodeCommandments\\Tests\\ConfigTunableDetector;\n"
            . "return fn (Config \$c) => \$c->disable(ConfigTunableDetector::class);\n"
        );

        $result = Config::load($dir)->apply([new ConfigTunableDetector], []);

        $this->assertSame([], $result['backend'], 'the file disabled the detector');
    }

    public function test_load_without_a_config_file_is_a_no_op(): void
    {
        $result = Config::load($this->project(null))->apply([new ConfigTunableDetector], []);

        $this->assertCount(1, $result['backend']);
    }

    public function test_a_configured_threshold_changes_real_detection(): void
    {
        // The same 3-value clump recurs across TWO classes — flagged by default (min 2 classes).
        $src = <<<'PHP'
            <?php
            class ShopA { public function a(string $shopId, string $userId, string $channelId): void {} }
            class ShopB { public function b(string $shopId, string $userId, string $channelId): void {} }
            PHP;
        $codebase = AstCodebase::fromString($src);

        $configured = new Config()->configure(fn (DataClumpDetector $d) => $d->minClasses(3))->apply([new DataClumpDetector], []);

        $this->assertNotEmpty(new DataClumpDetector()->find($codebase), 'default (min 2) flags the clump');
        $this->assertEmpty($configured['backend'][0]->find($codebase), 'raising min to 3 clears it — only 2 classes share it');
    }

    private function project(?string $configPhp): string
    {
        $dir = sys_get_temp_dir() . '/cc-config-' . uniqid('', true);
        mkdir($dir . '/.commandments', 0777, true);
        $this->dirs[] = $dir;

        if ($configPhp !== null) {
            file_put_contents($dir . '/.commandments/config.php', $configPhp);
        }

        return $dir;
    }
}

/**
 * A backend detector with a tunable threshold — the fake used to exercise the config layer.
 */
final class ConfigTunableDetector implements BackendDetector
{
    public int $limit = 3;

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function sin(): Sin
    {
        return new ArrayBag;
    }

    public function find(AstCodebase $codebase): array
    {
        return [];
    }
}

/**
 * A frontend detector, to prove {@see Config::register} routes by engine.
 */
final class ConfigFrontendDetector implements FrontendDetector
{
    public function sin(): Sin
    {
        return new PropDrilling;
    }

    public function find(VueCodebase $components): array
    {
        return [];
    }
}
