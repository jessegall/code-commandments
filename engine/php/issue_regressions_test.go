package php_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/namespaces"
)

func TestEloquentFactoryBindingDoesNotCloseNamespaceCycle(t *testing.T) {
	codebase := phpSourceHolding(t, `{"packages": [{"name": "laravel/framework", "version": "v11.9.0"}]}`, `<?php
namespace App\Models;
use App\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model {
    use HasFactory;
    protected static function newFactory(): CustomerFactory { return CustomerFactory::new(); }
}
namespace App\Factories;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
class CustomerFactory extends Factory {
    protected $model = Customer::class;
}
`)
	if cycle := namespaces.Of(codebase).ArrowsClosingAMutualPair(); len(cycle) != 0 {
		t.Fatalf("an Eloquent model-to-factory association closed a cycle: %v", cycle)
	}
}
