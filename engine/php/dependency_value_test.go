package php_test

import (
	"testing"

)

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

