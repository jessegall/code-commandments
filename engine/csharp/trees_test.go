package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// TestANullForgivenInsideAnExpressionTreeForgivesNothing holds the reading a report of a real false positive asked
// for: a lambda handed to an `Expression<...>` parameter is never run, it is read and translated, so a `!` inside it
// silences a conversion and dereferences nothing. A lambda handed to a `Func<...>` IS run, and its `!` is the sin.
func TestANullForgivenInsideAnExpressionTreeForgivesNothing(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"inside a lambda the call only reads": {
			"namespace Shop;\n\nusing System;\nusing System.Linq.Expressions;\n\npublic sealed class Saga { public string? Key { get; set; } }\n\npublic sealed class Store\n{\n    public void Index(Expression<Func<Saga, object>> by) { }\n}\n\npublic sealed class Setup\n{\n    public void Run(Store store)\n    {\n        store.Index(saga => saga.Key!);\n    }\n}\n",
			0,
		},
		"inside a lambda the call runs": {
			"namespace Shop;\n\nusing System;\n\npublic sealed class Saga { public string? Key { get; set; } }\n\npublic sealed class Store\n{\n    public void Each(Func<Saga, object> by) { }\n}\n\npublic sealed class Setup\n{\n    public void Run(Store store)\n    {\n        store.Each(saga => saga.Key!);\n    }\n}\n",
			1,
		},
		"on a value the method dereferences itself": {
			"namespace Shop;\n\npublic sealed class Saga { public string? Key { get; set; } }\n\npublic sealed class Setup\n{\n    public int Length(Saga saga) => saga.Key!.Length;\n}\n",
			1,
		},
		"inside a tree held by a variable of its own": {
			"namespace Shop;\n\nusing System;\nusing System.Linq.Expressions;\n\npublic sealed class Saga { public string? Key { get; set; } }\n\npublic sealed class Setup\n{\n    public void Run()\n    {\n        Expression<Func<Saga, object>> by = saga => saga.Key!;\n    }\n}\n",
			0,
		},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := csharptest.FromSource(t, map[string]string{"Shop/Thing.cs": test.source})
			found := cs.In(codebase).
				WhereExpression().
				Where(engine.As(cs.Node.ForgivesNull)).
				Reject(engine.As(cs.Node.SitsInAnExpressionTree)).
				Count()

			if found != test.want {
				t.Errorf("found %d, want %d", found, test.want)
			}
		})
	}
}
