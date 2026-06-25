<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\CallConsumptionCensus;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PHPUnit\Framework\TestCase;

class CallConsumptionCensusTest extends TestCase
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

    public function test_all_callers_de_null_when_every_caller_throws(): void
    {
        $registry = $this->write(<<<'PHP'
<?php
namespace Demo;
class Registry {
    private array $items = [];
    public function register(string $k, $v): void { $this->items[$k] = $v; }
    public function get(string $k) { return $this->items[$k] ?? null; }
}
PHP);
        $consumer = $this->write(<<<'PHP'
<?php
namespace Demo;
class Consumer {
    public function __construct(private Registry $reg) {}
    public function use(string $k) {
        return $this->reg->get($k) ?? throw new \RuntimeException('missing');
    }
}
PHP);

        $census = new CallConsumptionCensus(CodebaseIndex::build([$registry, $consumer]));

        $this->assertTrue($census->allCallersDeNull('Demo\\Registry', 'get'));
    }

    public function test_not_all_de_null_when_a_caller_handles_absence(): void
    {
        // The ex05 hinge: `$x?->m() ?? 'literal'` is HANDLES, not de-null.
        $registry = $this->write(<<<'PHP'
<?php
namespace Demo;
class Directory {
    private array $byEmail = [];
    public function add(string $k, $v): void { $this->byEmail[$k] = $v; }
    public function findByEmail(string $k) { return $this->byEmail[$k] ?? null; }
}
PHP);
        $consumer = $this->write(<<<'PHP'
<?php
namespace Demo;
class Greeter {
    public function __construct(private Directory $dir) {}
    public function greet(string $email): string {
        return $this->dir->findByEmail($email)?->name() ?? 'not found';
    }
}
PHP);

        $census = new CallConsumptionCensus(CodebaseIndex::build([$registry, $consumer]));

        $this->assertFalse($census->allCallersDeNull('Demo\\Directory', 'findByEmail'));
        $this->assertTrue($census->consumption('Demo\\Directory', 'findByEmail')['anyHandles']);
    }

    public function test_follows_passthrough_wrappers_transitively(): void
    {
        $registry = $this->write(<<<'PHP'
<?php
namespace Demo;
class ChannelRegistry {
    private array $channels = [];
    public function register(string $k, $v): void { $this->channels[$k] = $v; }
    public function find(string $k) { return $this->channels[$k] ?? null; }
}
PHP);
        $dispatcher = $this->write(<<<'PHP'
<?php
namespace Demo;
class Dispatcher {
    public function __construct(private ChannelRegistry $channels) {}
    private function channelFor(string $k) {
        return $this->channels->find($k);
    }
    public function dispatch(string $k): void {
        $channel = $this->channelFor($k) ?? throw new \RuntimeException('no channel');
        $channel->send();
    }
}
PHP);

        $census = new CallConsumptionCensus(CodebaseIndex::build([$registry, $dispatcher]));

        // find()'s only caller (channelFor) is a passthrough; two hops up the
        // dispatch does `?? throw` → the absence is an invariant.
        $this->assertTrue($census->allCallersDeNull('Demo\\ChannelRegistry', 'find'));
    }

    public function test_no_visible_callers_is_not_all_de_null(): void
    {
        $registry = $this->write(<<<'PHP'
<?php
namespace Demo;
class Lonely {
    private array $items = [];
    public function register(string $k, $v): void { $this->items[$k] = $v; }
    public function get(string $k) { return $this->items[$k] ?? null; }
}
PHP);

        $census = new CallConsumptionCensus(CodebaseIndex::build([$registry]));

        $this->assertFalse($census->allCallersDeNull('Demo\\Lonely', 'get'));
    }

    private function write(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'ccc') . '.php';
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }
}
