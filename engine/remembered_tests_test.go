package engine_test

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func untestedFunctions(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Reject(engine.Match.IsTest).Get()
}

func TestAFileThatBecameATestIsNotAnsweredFromItsOldMemory(t *testing.T) {
	memory := engine.Remembering(time.Now)
	module := "def total() -> int:\n    return 1\n"

	for name, moved := range map[string][2]*engine.Codebase{
		"a module moved into tests/": {
			pythontest.FromSource(t, map[string]string{"shop/__init__.py": "", "shop/prices.py": module}),
			pythontest.FromSource(t, map[string]string{"tests/__init__.py": "", "tests/prices.py": module}),
		},
		"a.ts renamed to a.test.ts": {
			frontendtest.FromSource(t, map[string]string{"a.ts": "export function total(): number { return 1; }\n"}),
			frontendtest.FromSource(t, map[string]string{"a.test.ts": "export function total(): number { return 1; }\n"}),
		},
	} {
		if found := memory.Find(moved[0], untestedFunctions); len(found) != 1 {
			t.Fatalf("%s: before the move the rule finds %d functions, want 1", name, len(found))
		}
		if found := memory.Find(moved[1], untestedFunctions); len(found) != 0 {
			t.Errorf("%s: after the move the rule still finds %v from what it remembered", name, found)
		}
	}
}
