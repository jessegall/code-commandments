<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoRecordThatOutsideAggregateProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoRecordThatOutsideAggregateProphetTest extends TestCase
{
    private NoRecordThatOutsideAggregateProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoRecordThatOutsideAggregateProphet();
    }

    public function test_detects_record_that_in_controller(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'RecordThatOutsideAggregate.php');
        $judgment = $this->prophet->judge('/app/Http/Controllers/OrderController.php', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_record_that_in_domain(): void
    {
        $content = $this->getFixtureContent('Backend', 'Righteous', 'ProperAggregate.php');
        // File in domain/ directory is allowed
        $judgment = $this->prophet->judge('/domain/Order/OrderAggregate.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_allows_domain_directory(): void
    {
        $content = <<<'PHP'
<?php
namespace Domain\Order;

class OrderAggregate
{
    public function create(): self
    {
        $this->recordThat(new OrderCreated());
        return $this;
    }
}
PHP;

        $judgment = $this->prophet->judge('/domain/Order/OrderAggregate.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_record_that_in_service(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class OrderService
{
    public function createOrder($aggregate)
    {
        $aggregate->recordThat(new OrderCreated());
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderService.php', $content);
        $this->assertTrue($judgment->isFallen());
    }
}
