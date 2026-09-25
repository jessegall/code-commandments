package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

const cart = `using System.Collections.Generic;

namespace Shop;

public enum Status { Open, Paid }

public sealed class Cart
{
    private readonly List<string>? lines;

    public string Name { get; } = "cart";

    public int Count(Status status) => status == Status.Paid ? 0 : lines!.Count;

    public void Clear()
    {
        if (lines is null) return;
        lines.Clear();
    }
}
`

func TestAMethodRunsTheBodyAfterItsArrow(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Cart.cs": cart})
	count := csharptest.First(t, codebase, "Shop/Cart.cs", "MethodDeclaration")
	if !count.RunsABody() || !count.FunctionBody().Is("ArrowExpressionClause") {
		t.Errorf("Count runs %s, not its arrow body", count.FunctionBody().Kind())
	}
	if !count.FunctionBody().IsReturn() || count.FunctionBody().IsExpressionStatement() {
		t.Error("Count's arrow body returns its value")
	}
	answers := count.FunctionBody().ReturnedValue().Answers()
	if len(answers) != 2 || answers[0].Text() != "0" {
		t.Errorf("the conditional answers %d values", len(answers))
	}
}

func TestAComparisonWithAnEnumMemberNamesItsSubject(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Cart.cs": cart})
	equals := csharptest.First(t, codebase, "Shop/Cart.cs", "EqualsExpression")
	if subject := equals.ComparisonSubject(); subject.Name() != "status" {
		t.Errorf("the comparison's subject is %q, not status", subject.Name())
	}
	if equals.ComparisonSubject().Type().Name() != "global::Shop.Status" {
		t.Errorf("status is %q", equals.ComparisonSubject().Type().Name())
	}
}

func TestADeclarationNamesTheTypeItsWrittenTypeStandsFor(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Cart.cs": cart})
	field := csharptest.First(t, codebase, "Shop/Cart.cs", "FieldDeclaration")
	declared := field.Children()[0].DeclaredType()
	if declared.Name() != "global::System.Collections.Generic.List<global::System.String>" || !declared.IsNullable() {
		t.Errorf("the field declares %q", declared.Name())
	}
	if inner := declared.Inner(); len(inner) != 1 || inner[0] != "global::System.String" {
		t.Errorf("the field's inner types are %v", inner)
	}
	property := csharptest.First(t, codebase, "Shop/Cart.cs", "PropertyDeclaration")
	if property.DeclaredType().Name() != "global::System.String" {
		t.Errorf("the property declares %q", property.DeclaredType().Name())
	}
}

func TestAForgivenNullableIsMarked(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Cart.cs": cart})
	if !csharptest.First(t, codebase, "Shop/Cart.cs", "SuppressNullableWarningExpression").ForgivesNull() {
		t.Error("lines! forgives a declared-nullable field")
	}
}

func TestTheStatementsOfABlockAreStatements(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Cart.cs": cart})
	statements := csharp.In(codebase).WhereStatement().Count()
	if statements != 4 {
		t.Errorf("found %d statements, not the block, the if, its return and the call", statements)
	}
}

func TestAFindingIsScopedByItsKindAndTheNameItDeclares(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Cart.cs": cart})
	if scope := csharptest.First(t, codebase, "Shop/Cart.cs", "MethodDeclaration").Scope(); scope != "MethodDeclaration Count" {
		t.Errorf("the method is scoped %q", scope)
	}
	if scope := csharptest.First(t, codebase, "Shop/Cart.cs", "GetAccessorDeclaration").Scope(); scope != "GetAccessorDeclaration Name" {
		t.Errorf("the accessor is scoped %q", scope)
	}
	if scope := csharptest.First(t, codebase, "Shop/Cart.cs", "IfStatement").Scope(); scope != "IfStatement" {
		t.Errorf("the if is scoped %q", scope)
	}
}
