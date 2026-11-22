package php_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
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

func TestAModelsForwardedQueryCallExecutesAQueryWithoutItsTarget(t *testing.T) {
	codebase := phpSourceHolding(t, `{"packages": [{"name": "laravel/framework", "version": "v11.9.0"}]}`, `<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Picker extends Model {}
class Audit {
    public function names(array $ids): void {
        foreach ($ids as $id) { Picker::find($id); }
        $picker = new Picker();
        $picker->save();
        $picker->label();
        Picker::where('name', 'x')->count();
    }
}
`)
	calls := codebase.WhereKind("call").Get()
	effects := map[string]engine.Truth{}
	for _, call := range calls {
		callee, _ := call.Single("callee")
		member, _ := callee.Single("member")
		effects[member.Node().Name] = call.HasEffect("executes-query")
	}
	if effects["find"] != engine.True || effects["save"] != engine.True || effects["label"] == engine.True || effects["count"] != engine.True {
		t.Errorf("the forwarded find, the inherited save and a method no package names read %v", effects)
	}
}
