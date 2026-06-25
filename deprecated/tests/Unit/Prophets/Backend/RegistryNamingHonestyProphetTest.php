<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\RegistryNamingHonestyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryPatternProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PHPUnit\Framework\TestCase;

class RegistryNamingHonestyProphetTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            @unlink($f);
        }

        $this->tempFiles = [];

        parent::tearDown();
    }

    public function test_flags_registry_shaped_class_not_named_registry(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class UnpackPortResolver {
    private array $unpackers = [];
    public function register(string $class, $u): void { $this->unpackers[$class] = $u; }
    public function portsFor(string $class) { return $this->unpackers[$class] ?? null; }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('registry-naming:UnpackPortResolver', $judgment->warnings[0]->symbol);
    }

    public function test_does_not_flag_a_class_named_registry(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class ChannelRegistry {
    private array $channels = [];
    public function register(string $k, $c): void { $this->channels[$k] = $c; }
    public function find(string $k) { return $this->channels[$k] ?? null; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_non_registry_class(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class Calculator {
    public function add(int $a, int $b): int { return $a + $b; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_when_marked_with_interface(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class GatewayBag implements Registry {
    private array $gateways = [];
    public function register(string $k, $g): void { $this->gateways[$k] = $g; }
    public function find(string $k) { return $this->gateways[$k] ?? null; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_pattern_prophet_quiet_for_a_single_registry(): void
    {
        $a = $this->write(<<<'PHP'
<?php
namespace Demo;
final class OnlyRegistry {
    private array $items = [];
    public function register(string $k, $v): void { $this->items[$k] = $v; }
    public function find(string $k) { return $this->items[$k] ?? null; }
}
PHP);

        $prophet = new RegistryPatternProphet();
        $prophet->setCodebaseIndex(CodebaseIndex::build([$a]));

        $this->assertTrue($prophet->judge($a, file_get_contents($a))->isRighteous());
    }

    public function test_pattern_prophet_fires_when_two_hand_rolled_registries(): void
    {
        $a = $this->write(<<<'PHP'
<?php
namespace Demo;
final class ChannelRegistry {
    private array $channels = [];
    public function register(string $k, $v): void { $this->channels[$k] = $v; }
    public function find(string $k) { return $this->channels[$k] ?? null; }
}
PHP);
        $b = $this->write(<<<'PHP'
<?php
namespace Demo;
final class TemplateRegistry {
    private array $templates = [];
    public function register(string $k, $v): void { $this->templates[$k] = $v; }
    public function find(string $k) { return $this->templates[$k] ?? null; }
}
PHP);

        $prophet = new RegistryPatternProphet();
        $prophet->setCodebaseIndex(CodebaseIndex::build([$a, $b]));

        $judgment = $prophet->judge($a, file_get_contents($a));
        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('registry-pattern:ChannelRegistry', $judgment->warnings[0]->symbol);
    }

    private function judge(string $body): Judgment
    {
        return (new RegistryNamingHonestyProphet())->judge('/x.php', "<?php\n\nnamespace App;\n\n{$body}\n");
    }

    private function write(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rnh') . '.php';
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }
}
