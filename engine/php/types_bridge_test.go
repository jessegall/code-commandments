package php

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.RegisterPackageFacts(engine.PackageFacts{Package: "laravel/framework", Ecosystem: engine.Composer, Versions: ">=8", Entries: []engine.PackageEntry{
		{Type: `Illuminate\Database\Eloquent\Builder`, Name: "get", Effect: "probe-reads-rows", Execution: "call"},
	}})
}

func TestTheBridgeTypesAReceiverAndBindsTheCallToTheMethodItsTypeDeclares(t *testing.T) {
	folder := written(t, map[string]string{
		"composer.lock": `{"packages": [{"name": "laravel/framework", "version": "v11.0.0"}]}`,
		"Builder.php":   "<?php\nnamespace Illuminate\\Database\\Eloquent;\nclass Builder { public function get(): array { return []; } }\n",
		"Query.php":     "<?php\nnamespace App;\nuse Illuminate\\Database\\Eloquent\\Builder;\nclass Query extends Builder {}\n",
		"Reports.php":   "<?php\nnamespace App;\nclass Reports { public function get(): array { return []; } }\n",
		"Shop.php": "<?php\nnamespace App;\nfinal class Shop\n{\n    public function __construct(private Query $query, private Reports $reports) {}\n\n" +
			"    public function rows(): array\n    {\n        $copy = $this->query;\n        $this->reports->get();\n\n        return $copy->get();\n    }\n}\n",
	})
	codebase := engine.Load(bridged(t, folder))
	targets := map[string]engine.Match{}
	for _, call := range codebase.WhereKind("call").Get() {
		if target := call.Node().Target; target != nil {
			targets[target.Type] = call
		}
	}
	inherited, lookalike := targets[`Illuminate\Database\Eloquent\Builder`], targets[`App\Reports`]
	if !inherited.Exists() || !lookalike.Exists() {
		t.Fatalf("the bridge bound the calls to %v, where both get calls bind to the class that declares get", targets)
	}
	if effect := inherited.HasEffect("probe-reads-rows"); effect != engine.True {
		t.Errorf("get on a local holding the Builder subclass reads %v, where Laravel's Builder::get has the effect", effect)
	}
	if effect := lookalike.HasEffect("probe-reads-rows"); effect != engine.False {
		t.Errorf("the project's own Reports::get reads %v, where a method sharing only the name has no effect", effect)
	}
	if typed := inherited.Node().Resolved; typed == nil || typed.Kind != "keyword" || typed.Name != "array" {
		t.Errorf("the call's resolved type is %+v, where Builder::get declares array", typed)
	}
}
