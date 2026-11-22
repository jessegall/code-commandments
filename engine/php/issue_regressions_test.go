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

func TestAFieldReadAfterAnAnonymousClassKeepsItsClass(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;
class Clock { public function now(): int { return 0; } }
class Ledger {
    private Clock $clock;
    public function stamp(): int {
        $visitor = new class { public function visit(): void {} };
        return $this->clock->now();
    }
    public function later(): int {
        return $this->clock->now();
    }
}
`)
	var typed []string
	for _, access := range codebase.WhereKind("access").Get() {
		member, _ := access.Single("member")
		if member.Node().Name != "clock" {
			continue
		}
		name := ""
		if found := access.Typed(); found != nil {
			name = found.Name
		}
		typed = append(typed, name)
	}
	if len(typed) != 2 || typed[0] != `App\Clock` || typed[1] != `App\Clock` {
		t.Errorf("the field reads after an anonymous class are typed %q, not App\\Clock twice", typed)
	}
}

func TestAPhpArrayOrScalarIsTypedAsCopiedByValue(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;
class Registry {
    private array $entries = [];
    private Registry $parent;
    public function entries(): array { return $this->entries; }
    public function parent(): Registry { return $this->parent; }
}
`)
	copied := map[string]bool{}
	for _, access := range codebase.WhereKind("access").Get() {
		member, _ := access.Single("member")
		copied[member.Node().Name] = access.Typed().IsValueType()
	}
	if !copied["entries"] || copied["parent"] {
		t.Errorf("an array field and an object field read as copied by value %v, not only the array", copied)
	}
}

func TestAnInlinedFingerprintOfAValueThatReadsItsOwnBindingEnds(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;
class Slugger {
    public function slug(string $text): string {
        $text = trim($text);
        return $text;
    }
}
`)
	for _, function := range codebase.WhereKind("function").Get() {
		if written, _ := function.FingerprintAs(engine.Fingerprinting{Mode: "inlined"}); written == "" {
			t.Errorf("the inlined fingerprint of %s is empty", function.Node().Name)
		}
	}
}

func TestANullsafeCallIsTypedAsNullable(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;
class Clock { public function now(): int { return 0; } }
class Ledger {
    private ?Clock $clock = null;
    public function stamp(): ?int { return $this->clock?->now(); }
}
`)
	for _, call := range codebase.WhereKind("call").Get() {
		if call.Line() == 6 && !call.Typed().IsNullable() {
			t.Errorf("the nullsafe call is typed %+v, which admits no null", call.Typed())
		}
	}
}
