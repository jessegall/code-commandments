package engine_test

import (
	"fmt"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestAPropertyAModelReadsThroughItsRelationMethodIsTypedByTheRelatedType(t *testing.T) {
	engine.RegisterPackageFacts(engine.PackageFacts{Package: "probe/relations", Ecosystem: engine.Composer, Versions: ">=1",
		Relations: []engine.PackageRelation{
			{Type: `Probe\Model`, Relation: `Probe\BelongsTo`},
			{Type: `Probe\Model`, Relation: `Probe\BelongsToMany`, Collection: `Probe\Collection`},
		}})
	root := t.TempDir()
	method := func(id int, name string, typed int) string {
		return fmt.Sprintf(`{"id": %d, "kind": "function", "form": "method", "span": [0, 10, 1], "name": %q, "symbol": "App\\Event::%s", "parts": {"returnType": {"id": %d, "kind": "type-expression", "form": "named", "span": [0, 5, 1], "resolvedType": %d}}}`, id, name, name, id+1, typed)
	}
	read := func(id int, name string) string {
		return fmt.Sprintf(`{"id": %d, "kind": "access", "form": "instance", "span": [20, 30, 3], "parts": {"subject": {"id": %d, "kind": "reference", "form": "name", "span": [20, 26, 3], "name": "event", "resolvedType": 3}, "member": {"id": %d, "kind": "reference", "form": "name", "span": [27, 30, 3], "name": %q}}}`, id, id+1, id+2, name)
	}
	stream := fmt.Sprintf(`{"header": {"contract": "tree", "version": 5, "language": "php", "bridge": {"name": "probe", "version": "8.0.0"}, "roots": [%q]}}
{"file": {"path": %q, "language": "php", "errors": 0, "module": "App", "resolver": {"tool": "php-parser", "ran": true}, "root": {"id": 0, "kind": "file", "span": [0, 40, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 40, 1], "name": "App", "parts": {"statements": [{"id": 2, "kind": "type", "form": "class", "span": [0, 19, 1], "name": "Event", "symbol": "App\\Event", "parts": {"bases": [{"id": 3, "kind": "type-expression", "form": "named", "span": [12, 17, 1], "name": "Model", "refers": "Probe\\Model"}], "writtenMembers": [%s, %s]}}, %s, %s]}}]}}, "comments": [], "types": [{"text": "\\Probe\\BelongsTo<\\App\\Game>", "kind": "named", "name": "Probe\\BelongsTo", "origin": "docblock", "args": [{"text": "\\App\\Game", "kind": "named", "name": "App\\Game", "origin": "docblock"}]}, {"text": "\\Probe\\BelongsToMany<\\App\\Hub>", "kind": "named", "name": "Probe\\BelongsToMany", "origin": "docblock", "args": [{"text": "\\App\\Hub", "kind": "named", "name": "App\\Hub", "origin": "docblock"}]}, {"text": "\\Probe\\Collection", "kind": "named", "name": "Probe\\Collection", "origin": "compiler"}, {"text": "App\\Event", "kind": "named", "name": "App\\Event", "origin": "compiler"}]}}
{"program": {"symbols": [{"symbol": "Probe\\Model", "kind": "class", "name": "Model"}, {"symbol": "Probe\\BelongsTo", "kind": "class", "name": "BelongsTo"}, {"symbol": "Probe\\BelongsToMany", "kind": "class", "name": "BelongsToMany"}]}}
{"trailer": {"files": 1}}
`, root, root+"/Event.php", method(4, "legacyGame", 0), method(6, "hubs", 1), read(8, "legacyGame"), read(11, "hubs"))
	codebase, err := engine.FromString(stream, nil)
	if err != nil {
		t.Fatal(err)
	}
	typed := map[string]string{}
	for _, access := range codebase.WhereKind("access").Get() {
		if named := access.Typed(); named != nil {
			typed[access.Child("member").Name()] = named.Name
			if len(named.Args) == 1 {
				typed[access.Child("member").Name()] += "<" + named.Args[0].Name + ">"
			}
		}
	}
	if typed["legacyGame"] != `App\Game` || typed["hubs"] != `Probe\Collection<App\Hub>` {
		t.Errorf("the relation properties read %v", typed)
	}
}
