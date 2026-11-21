package php_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestCrossModuleDependencyResolvesTypesByQualifiedName(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace Store\Accounts;
use Illuminate\Container\Attributes\Scoped;
#[Scoped]
final class Customer {}
namespace Store\Orders;
use Illuminate\Container\Attributes\Scoped;
use Store\Accounts\Customer as AccountCustomer;
#[Scoped]
final class Customer {}
final class Checkout {
    public function __construct(private Customer $local, private AccountCustomer $account) {}
}
`)
	parameters := codebase.WhereIs(engine.Parameter).Get()
	want := []string{`Store\Orders\Customer`, `Store\Accounts\Customer`}
	if len(parameters) != len(want) {
		t.Fatalf("want two parameters, got %d", len(parameters))
	}
	for i, parameter := range parameters {
		typed := parameter.Typed()
		if typed == nil || typed.Name != want[i] {
			t.Fatalf("%s resolves to %v, want %s", parameter.Name(), typed, want[i])
		}
		types, known := parameter.Types()
		if !known {
			t.Fatal("the shared type index is missing")
		}
		declaration, found := types.Declaration(typed.Name)
		if !found || declaration.Identity() != want[i] {
			t.Fatalf("%s should index the declaration %s", parameter.Name(), want[i])
		}
	}
	findings := shipped(t, "concrete-cross-module-dependency").Find(codebase)
	if len(findings) != 1 || findings[0].Name() != "account" {
		t.Fatalf("only the account module's dependency crosses the boundary, found %v", findings)
	}
}

func TestCrossModuleDependenciesRecognizeServiceOperations(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace Shop\Stock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Container\Attributes\Scoped;
abstract class Repository {
    protected function newQuery(): Builder { return new Builder(); }
}
final class StockRepository extends Repository {
    protected string $table = 'stock';
    public function sku(string $key): string { return $this->newQuery()->find($key)->sku; }
}
final readonly class StockLabel {
    public function __construct(private StockRepository $repository) {}
    public function forItem(string $id): string { return $this->repository->sku($id); }
}
#[Scoped]
final class Hold {
    private int $depth = 0;
    public function open(): void { $this->depth++; }
}
final class ScratchFiles {
    public function remove(string $path): void { unlink($path); }
}
final readonly class Quantity {
    public function __construct(private int $amount) {}
    public function amount(): int { return $this->amount; }
}
namespace Shop\Checkout;
use Shop\Stock\Hold;
use Shop\Stock\Quantity;
use Shop\Stock\ScratchFiles;
use Shop\Stock\StockLabel;
use Shop\Stock\StockRepository;
use Spatie\LaravelData\Attributes\FromContainer;
final class Checkout {
    public function __construct(
        #[FromContainer(StockRepository::class)] public StockRepository $entries,
        private StockLabel $labels,
        private Hold $hold,
        private ScratchFiles $files,
        private Quantity $quantity,
    ) {}
}
`)
	var parameters []string
	for _, finding := range shipped(t, "concrete-cross-module-dependency").Find(codebase) {
		parameters = append(parameters, finding.Name())
	}
	slices.Sort(parameters)
	if want := []string{"entries", "files", "hold", "labels"}; !slices.Equal(parameters, want) {
		t.Fatalf("expected concrete service parameters %v, found %v", want, parameters)
	}
}

func TestCrossModuleDependenciesAllowRootContextAndModelInstances(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace Shop;
use Illuminate\Container\Attributes\Scoped;
#[Scoped]
final class RequestContext {
    public function __construct(public string $requestId) {}
}
final class LogPurger {
    public function __construct(private Logs\LogStore $store) {}
    public function purge(string $path): void { $this->store->remove($path); }
}
final class Logs {
    public function __construct(private Logs\LogStore $writer) {}
    public function purge(string $path): void { $this->writer->remove($path); }
}
namespace Shop\Logs;
final class LogStore {
    public function remove(string $path): void { unlink($path); }
}
final class LogHistory {
    public function __construct(private \Shop\Logs $logs) {}
}
namespace Shop\Customers;
use Illuminate\Database\Eloquent\Model;
final class Customer extends Model {
    private string $label;
    public function label(): string { return $this->label; }
}
final class CustomerRecord {
    public function __construct(public \DateTimeInterface $registeredAt, public string $name) {}
}
namespace Shop\Checkout;
use Shop\Customers\Customer;
use Shop\Customers\CustomerRecord;
use Shop\RequestContext;
final class Receipt {
    public function __construct(private RequestContext $context, private Customer $customer, private CustomerRecord $record) {}
    public function label(): string { return $this->context->requestId.':'.$this->customer->label(); }
}
final class Cleanup {
    public function __construct(private \Shop\LogPurger $purger) {}
}
`)

	findings := shipped(t, "concrete-cross-module-dependency").Find(codebase)
	if len(findings) != 2 || findings[0].Name() != "store" || findings[1].Name() != "purger" {
		t.Fatalf("want only the two log service dependencies despite their common namespace root, found %v", findings)
	}
}

func TestCrossModuleDependenciesAllowTheContextSuppliedByAFactory(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace Shop\Runtime;
use Illuminate\Contracts\Container\Container;
interface Scope {}
final class Factory {
    public function __construct(public readonly Scope $scope, private Container $container) {}
    public function build(string $class): object {
        return $this->container->make($class, ['scope' => $this->scope]);
    }
}
namespace Shop\Stock;
use Illuminate\Container\Attributes\Scoped;
use Shop\Runtime\Scope;
#[Scoped]
final class StockScope implements Scope {
    public function __construct(private int $warehouse) {}
    public function warehouse(): int { return $this->warehouse; }
}
namespace Shop\Checkout;
use Shop\Stock\StockScope;
final class Receipt {
    public function __construct(private StockScope $scope) {}
    public function warehouse(): int { return $this->scope->warehouse(); }
}
`)
	if findings := shipped(t, "concrete-cross-module-dependency").Find(codebase); len(findings) != 0 {
		t.Fatalf("the factory supplies the scope for the current operation, found %v", findings)
	}
}

func TestCrossModuleDependenciesKeepLayersOfOneFeatureTogether(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace Shop\Stock;
use Illuminate\Container\Attributes\Scoped;
#[Scoped]
final class Counts {
    public function sync(): void {}
}
namespace Shop\Services\Stock;
use Illuminate\Container\Attributes\Scoped;
use Shop\Stock\Counts;
#[Scoped]
final class StockLedger {
    public function __construct(private Counts $counts) {}
    public function record(): void {}
}
namespace Shop\Services\Shipping;
use Illuminate\Container\Attributes\Scoped;
#[Scoped]
final class DeliveryQueue {
    public function schedule(): void {}
}
namespace Shop\Services;
use Illuminate\Container\Attributes\Scoped;
#[Scoped]
final class ShopStockService {
    public function load(): void {}
}
namespace Shop\Http\Controllers\Stock;
use Shop\Services\Shipping\DeliveryQueue;
use Shop\Services\Stock\StockLedger;
use Shop\Services\ShopStockService;
use Shop\Stock\Counts;
final class StockController {
    public function __construct(private StockLedger $ledger, private ShopStockService $stock, private DeliveryQueue $delivery, private Counts $countsFromHttp) {}
}
namespace Shop\Http\Controllers\Inventory;
use Shop\Services\ShopStockService;
final class InventoryController {
    public function __construct(private ShopStockService $stockForInventory) {}
}
namespace Shop\Http\Api\V2\Stock;
use Shop\Services\Shipping\DeliveryQueue;
use Shop\Services\Stock\StockLedger;
final class StockEndpoint {
    public function __construct(private StockLedger $apiLedger, private DeliveryQueue $apiDelivery) {}
}
namespace Shop\Integrations\Stock;
use Shop\Services\Stock\StockLedger;
final class StockAdapter {
    public function __construct(private StockLedger $adapterLedger) {}
}
namespace Shop\Repositories;
use Illuminate\Container\Attributes\Scoped;
#[Scoped]
final class StockRepository {}
namespace Shop\Http\Controllers;
use Shop\Repositories\StockRepository;
use Shop\Services\ShopStockService;
final class StockController {
    public function __construct(private StockRepository $repository, private ShopStockService $service) {}
}
`)
	findings := shipped(t, "concrete-cross-module-dependency").Find(codebase)
	var names []string
	for _, finding := range findings {
		names = append(names, finding.Name())
	}
	want := []string{"delivery", "countsFromHttp", "stockForInventory", "apiDelivery"}
	if !slices.Equal(names, want) {
		t.Fatalf("want %v as cross-feature or direct HTTP dependencies, found %v", want, names)
	}
}

func TestCrossModuleDependenciesDistinguishScopeDataFromScopeOperations(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace Shop\Tenancy;
use Illuminate\Container\Attributes\Scoped;
#[Scoped]
final class Warehouse {
    public private(set) int $id = 0;
    public bool $active { get => $this->id !== 0; }
    public string $label { get => $this->label; }
    public function open(int $id): void { $this->id = $id; }
}
namespace Shop\Stock;
use Shop\Tenancy\Warehouse;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
final class StockReport {
    public function __construct(#[Hidden, FromContainer(Warehouse::class)] public readonly Warehouse $current) {}
    public function warehouse(): int { return $this->current->active ? $this->current->id : 0; }
}
final class SwitchWarehouse {
    public function __construct(private Warehouse $context) {}
    public function run(int $id): void { $this->context->open($id); }
}
final class WarehouseLabel {
    public function __construct(private Warehouse $warehouse) {}
    public function label(): string { return $this->warehouse->label; }
}
`)
	findings := shipped(t, "concrete-cross-module-dependency").Find(codebase)
	if len(findings) != 2 || findings[0].Name() != "context" || findings[1].Name() != "warehouse" {
		t.Fatalf("want the scope operation and the writable property, found %v", findings)
	}
}
