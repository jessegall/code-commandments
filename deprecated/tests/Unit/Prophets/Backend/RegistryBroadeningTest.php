<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoNullCoalesceToNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryReturnContractProphet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PHPUnit\Framework\TestCase;

/**
 * The markerless-registry broadenings (RegistryReturnContract warning +
 * registry-scoped NoNullCoalesceToNull) fire on the right shapes and — the
 * critical half — produce NO blocking sins on ordinary code (caches, service
 * providers, config readers).
 */
class RegistryBroadeningTest extends TestCase
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

    public function test_markerless_non_finder_getter_warns(): void
    {
        $prophet = new RegistryReturnContractProphet();
        $judgment = $prophet->judge('/x.php', <<<'PHP'
<?php
namespace App;
final class UserDirectory {
    private array $byId = [];
    public function add($u): void { $this->byId[$u->id] = $u; }
    public function getById(int $id): ?User { return $this->byId[$id] ?? null; }
}
PHP);

        // No marker → no sins; shape-detected non-finder getter → a warning.
        $this->assertTrue($judgment->isRighteous());
        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('registry-return-shape:getById', $judgment->warnings[0]->symbol);
    }

    public function test_markerless_option_getter_is_left_alone(): void
    {
        // Returning Option is a genuine-absence opt-in — never flagged markerless.
        $prophet = new RegistryReturnContractProphet();
        $judgment = $prophet->judge('/x.php', <<<'PHP'
<?php
namespace App;
use Support\Option;
final class TemplateStore {
    private array $templates = [];
    public function put(string $k, $t): void { $this->templates[$k] = $t; }
    public function lookup(string $k): Option { return Option::fromValue($this->templates[$k] ?? null); }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
        $this->assertCount(0, $judgment->warnings);
    }

    public function test_markerless_finder_fires_only_with_de_null_callers(): void
    {
        // find() is a finder name — fires only when the cross-file census proves
        // every caller de-nulls it.
        $registry = $this->write(<<<'PHP'
<?php
namespace Demo;
final class GatewayRegistry {
    private array $gateways = [];
    public function register(string $k, $g): void { $this->gateways[$k] = $g; }
    public function find(string $k): ?Gateway { return $this->gateways[$k] ?? null; }
}
PHP);
        $consumer = $this->write(<<<'PHP'
<?php
namespace Demo;
final class Checkout {
    public function __construct(private GatewayRegistry $registry) {}
    public function charge(string $k): void {
        $gateway = $this->registry->find($k) ?? throw new \RuntimeException('x');
        $gateway->charge();
    }
}
PHP);

        $prophet = new RegistryReturnContractProphet();
        $prophet->setCodebaseIndex(CodebaseIndex::build([$registry, $consumer]));
        $judgment = $prophet->judge($registry, file_get_contents($registry));

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('registry-return-shape:find', $judgment->warnings[0]->symbol);
    }

    public function test_finder_with_handling_caller_is_left_alone(): void
    {
        // ex05 findByEmail: caller `?-> … ?? 'literal'` handles absence → genuine.
        $registry = $this->write(<<<'PHP'
<?php
namespace Demo;
final class UserDirectory {
    private array $byEmail = [];
    public function add($u): void { $this->byEmail[$u->email] = $u; }
    public function findByEmail(string $e): ?User { return $this->byEmail[$e] ?? null; }
}
PHP);
        $consumer = $this->write(<<<'PHP'
<?php
namespace Demo;
final class Greeter {
    public function __construct(private UserDirectory $dir) {}
    public function greet(string $e): string {
        return $this->dir->findByEmail($e)?->name() ?? 'not found';
    }
}
PHP);

        $prophet = new RegistryReturnContractProphet();
        $prophet->setCodebaseIndex(CodebaseIndex::build([$registry, $consumer]));
        $judgment = $prophet->judge($registry, file_get_contents($registry));

        $this->assertTrue($judgment->isRighteous());
        $this->assertCount(0, $judgment->warnings);
    }

    public function test_negative_corpus_produces_no_sins(): void
    {
        // None of these ordinary shapes may produce a BLOCKING sin from the
        // broadened detectors (a non-blocking heuristic warning is acceptable).
        $cases = [
            // A cache: a miss returning null is legitimate.
            'cache' => <<<'PHP'
<?php
namespace App;
final class FooCache {
    private array $entries = [];
    public function put(string $k, $v): void { $this->entries[$k] = $v; }
    public function peek(string $k): ?Foo { return $this->entries[$k] ?? null; }
}
PHP,
            // A Laravel service provider register() hook.
            'provider' => <<<'PHP'
<?php
namespace App;
final class CacheServiceProvider extends ServiceProvider {
    private array $bindings = [];
    public function register(): void { $this->bindings['x'] = 1; }
    public function get(string $k) { return $this->bindings[$k] ?? null; }
}
PHP,
            // A config reader: `$config[$k] ?? null` is load-bearing.
            'config' => <<<'PHP'
<?php
namespace App;
final class Config {
    public function __construct(private array $config) {}
    public function get(string $k) { return $this->config[$k] ?? null; }
}
PHP,
        ];

        foreach ($cases as $label => $code) {
            $registry = new RegistryReturnContractProphet();
            $coalesce = new NoNullCoalesceToNullProphet();

            $this->assertTrue($registry->judge('/x.php', $code)->isRighteous(), "RegistryReturnContract sin on {$label}");
            $this->assertTrue($coalesce->judge('/x.php', $code)->isRighteous(), "NoNullCoalesceToNull sin on {$label}");
        }
    }

    private function write(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rbt') . '.php';
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }
}
