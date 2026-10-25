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
		"inside one of several lambdas a `params` parameter takes": {
			"namespace Shop;\n\nusing System;\nusing System.Linq.Expressions;\n\npublic sealed class Saga { public string Tenant { get; set; } = \"\"; public string? Key { get; set; } }\n\npublic sealed class Store\n{\n    public void UniqueIndex(params Expression<Func<Saga, object>>[] by) { }\n}\n\npublic sealed class Setup\n{\n    public void Run(Store store)\n    {\n        store.UniqueIndex(saga => saga.Tenant, saga => saga.Key!);\n    }\n}\n",
			0,
		},
		"inside a lambda a named argument hands over": {
			"namespace Shop;\n\nusing System;\nusing System.Linq.Expressions;\n\npublic sealed class Saga { public string? Key { get; set; } }\n\npublic sealed class Store\n{\n    public void Index(string name, Expression<Func<Saga, object>> by) { }\n}\n\npublic sealed class Setup\n{\n    public void Run(Store store)\n    {\n        store.Index(name: \"uix_saga_key\", by: saga => saga.Key!);\n    }\n}\n",
			0,
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

// TestAForgivenNullIsReadByWhoHoldsTheLambda holds both halves of the reading that answers the reports about LINQ:
// a `!` in a lambda a queryable takes forgives nothing, because the lambda is translated to SQL and never run,
// while the same `!` in a lambda over a materialised list forgives a null for real and stays a finding. The
// difference is the type of what takes the lambda, which is what the engine reads.
func TestAForgivenNullIsReadByWhoHoldsTheLambda(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{
		"Sales.cs": `using System.Collections.Generic;
using System.Linq;

namespace Shop.Sales;

public sealed class Disposal
{
    public decimal? SalePrice { get; init; }
}

public sealed class Item
{
    public Disposal? Disposal { get; init; }
}

public sealed class Sales
{
    public List<Item> Sold(IQueryable<Item> items, List<Item> held)
    {
        var queried = items.Where(i => i.Disposal!.SalePrice != null).ToList();
        var inMemory = held.Where(i => i.Disposal!.SalePrice != null).ToList();

        return queried.Count > inMemory.Count ? queried : inMemory;
    }
}
`,
	})

	read := map[int]bool{}
	for _, forgiven := range cs.In(codebase).WhereKind("SuppressNullableWarningExpression").Get() {
		read[forgiven.Line()] = cs.Node{Match: forgiven}.SitsInAnExpressionTree()
	}

	if !read[20] {
		t.Error("a `!` in a lambda a queryable takes is read as forgiving a null, and it forgives nothing: the lambda becomes SQL")
	}

	if read[21] {
		t.Error("a `!` in a lambda over a list is read as sitting in a tree, and that lambda really runs")
	}
}

// TestAnUnboundCallWithholdsTheFinding holds the rule to what the engine could read: where the compilation has no
// reference for the method a lambda is handed to, as a project whose packages are not restored arrives, nothing
// here can tell whether that lambda becomes a tree or runs. A rule may not report a fact it could not read, so the
// `!` inside it is left alone — which is what the report about a validation library's RuleFor asked for.
func TestAnUnboundCallWithholdsTheFinding(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{
		"Validators.cs": `using FluentValidation;

namespace Shop.Validation;

public sealed class Logo
{
    public string Path { get; init; } = "";
}

public sealed class Settings
{
    public Logo? Logo { get; init; }
}

public sealed class SettingsValidator : AbstractValidator<Settings>
{
    public SettingsValidator()
    {
        RuleFor(settings => settings.Logo!.Path);
    }
}
`,
	})

	forgiven := cs.In(codebase).WhereKind("SuppressNullableWarningExpression").Get()
	if len(forgiven) != 1 {
		t.Fatalf("the fixture holds %d forgiven nulls, want the one", len(forgiven))
	}

	if !(cs.Node{Match: forgiven[0]}).SitsInAnExpressionTree() {
		t.Error("a `!` in a lambda handed to a call the compiler never bound is reported, and nothing here could read whether that lambda runs")
	}
}
