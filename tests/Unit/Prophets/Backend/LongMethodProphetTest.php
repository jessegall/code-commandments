<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\LongMethodProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class LongMethodProphetTest extends TestCase
{
    private LongMethodProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new LongMethodProphet();
        $this->prophet->configure(['max_method_lines' => 5]);
    }

    public function test_detects_long_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class OrderService
{
    public function process()
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderService.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('OrderService::process()', $judgment->sins[0]->message);
        $this->assertStringContainsString('lines long', $judgment->sins[0]->message);
    }

    public function test_passes_with_short_methods(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class OrderService
{
    public function process()
    {
        return true;
    }

    public function cancel()
    {
        return false;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderService.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_excludes_constructors(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly PaymentGateway $payments,
        private readonly NotificationService $notifications,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcher $events,
        private readonly CacheManager $cache,
    ) {
    }

    public function process()
    {
        return true;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderService.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_configurable_threshold(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class OrderService
{
    public function process()
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $g = 7;
        $h = 8;
    }
}
PHP;

        // With threshold of 15, this 10-line method should pass
        $this->prophet->configure(['max_method_lines' => 15]);
        $judgment = $this->prophet->judge('/app/Services/OrderService.php', $content);
        $this->assertTrue($judgment->isRighteous());

        // With threshold of 5, this should fail
        $this->prophet = new LongMethodProphet();
        $this->prophet->configure(['max_method_lines' => 5]);
        $judgment = $this->prophet->judge('/app/Services/OrderService.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_reports_sin_per_method(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class OrderService
{
    public function process()
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
    }

    public function cancel()
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
    }

    public function refund()
    {
        return true;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderService.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertCount(2, $judgment->sins);
        $this->assertStringContainsString('process()', $judgment->sins[0]->message);
        $this->assertStringContainsString('cancel()', $judgment->sins[1]->message);
    }

    public function test_reports_correct_line_number(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class OrderService
{
    public function shortMethod()
    {
        return true;
    }

    public function longMethod()
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderService.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertCount(1, $judgment->sins);
        $this->assertEquals(11, $judgment->sins[0]->line);
    }

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
