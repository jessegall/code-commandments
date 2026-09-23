<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend\TypeScript;

use JesseGall\CodeCommandments\Detectors\Frontend\TypeScript\NearDuplicateFunctionDetector;
use JesseGall\CodeCommandments\Ts\NodeMatch;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class NearDuplicateFunctionDetectorTest extends TestCase
{
    private const string LOAD = <<<'TS'
        async function loadOrders(page: number) {
            const response = await http.get(`/orders?page=${page}`);
            if (response.status !== 200) {
                throw new Error(response.statusText);
            }
            const rows = response.data.items.filter((item) => item.visible);
            return rows.map((item) => item.id);
        }
        TS;

    public function test_flags_two_bodies_that_differ_only_in_a_literal_and_their_locals(): void
    {
        $customers = str_replace(['loadOrders', '/orders', 'rows'], ['loadCustomers', '/customers', 'visible'], self::LOAD);

        $this->assertSame(['FunctionDecl loadOrders', 'FunctionDecl loadCustomers'], $this->findIn(self::LOAD . "\n" . $customers));
    }

    public function test_leaves_byte_identical_bodies_to_the_exact_detector(): void
    {
        $copy = str_replace('loadOrders', 'fetchOrders', self::LOAD);

        $this->assertSame([], $this->findIn(self::LOAD . "\n" . $copy));
    }

    public function test_leaves_bodies_that_read_different_members(): void
    {
        $other = str_replace(['loadOrders', 'item.visible'], ['loadArchived', 'item.archived'], self::LOAD);

        $this->assertSame([], $this->findIn(self::LOAD . "\n" . $other));
    }

    public function test_leaves_a_sole_return_that_differs_only_in_data(): void
    {
        $this->assertSame([], $this->findIn(<<<'TS'
            function orderColumns() { return [{ key: 'id', label: 'Id', width: 80 }, { key: 'total', label: 'Total', width: 120 }, { key: 'status', label: 'Status', width: 100 }]; }
            function customerColumns() { return [{ key: 'name', label: 'Name', width: 200 }, { key: 'email', label: 'Email', width: 240 }, { key: 'city', label: 'City', width: 140 }]; }
            TS));
    }

    public function test_leaves_lookup_tables_written_as_a_switch(): void
    {
        $this->assertSame([], $this->findIn(<<<'TS'
            function statusVariant(status: string): string {
                switch (status) {
                    case 'published': return 'default';
                    case 'processing': return 'secondary';
                    case 'failed': return 'destructive';
                    case 'pending': return 'outline';
                    case 'archived': return 'ghost';
                    case 'draft': return 'muted';
                    default: return 'outline';
                }
            }
            function statusDot(status: string): string {
                switch (status) {
                    case 'idle': return 'bg-gray-500';
                    case 'picking': return 'bg-blue-500';
                    case 'offline': return 'bg-red-500';
                    case 'paused': return 'bg-yellow-500';
                    case 'busy': return 'bg-orange-500';
                    case 'away': return 'bg-slate-500';
                    default: return 'bg-white';
                }
            }
            TS));
    }

    public function test_a_switch_that_computes_its_answers_is_still_compared(): void
    {
        $found = $this->findIn(<<<'TS'
            function orderLabel(status: string): string {
                switch (status) {
                    case 'paid': return translate('order.paid');
                    case 'sent': return translate('order.sent');
                    case 'lost': return translate('order.lost');
                    default: return translate('order.open');
                }
            }
            function invoiceLabel(status: string): string {
                switch (status) {
                    case 'paid': return translate('invoice.paid');
                    case 'sent': return translate('invoice.sent');
                    case 'lost': return translate('invoice.lost');
                    default: return translate('invoice.open');
                }
            }
            TS);

        $this->assertCount(2, $found);
    }

    public function test_leaves_constructors_of_different_classes(): void
    {
        $this->assertSame([], $this->findIn(<<<'TS'
            class OrderStore {
                constructor(private readonly http: Http, private readonly cache: Cache) {
                    this.items = [];
                    this.loaded = false;
                    this.http.on('order', (order) => this.items.push(order));
                    this.cache.remember('orders', () => this.items);
                }
            }
            class CustomerStore {
                constructor(private readonly http: Http, private readonly cache: Cache) {
                    this.items = [];
                    this.loaded = false;
                    this.http.on('customer', (customer) => this.items.push(customer));
                    this.cache.remember('customers', () => this.items);
                }
            }
            TS));
    }

    /**
     * @return list<string>
     */
    private function findIn(string $typeScript): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), new NearDuplicateFunctionDetector()->find(Codebase::fromTypeScript($typeScript)));
    }
}
