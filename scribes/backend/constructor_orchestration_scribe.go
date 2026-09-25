package backend

import (
	rules "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.ConstructorOrchestrationDetector{}, func() scribes.Scribe { return ConstructorOrchestrationScribe{} })
}

// ConstructorOrchestrationScribe moves a value a Data's constructor derives into a computed get hook on its property.
type ConstructorOrchestrationScribe struct{}

// Rewrite hoists each single-line assignment onto a typed declared property into that property's get hook.
func (ConstructorOrchestrationScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		if finding.Kind() == "Expr_Assign" {
			hoist(draft, finding)
		}
	}

	return draft.Rewrites(), nil
}

// hoist marks the property `#[Computed]`, takes its readonly off, turns its `;` into the get hook that computes the
// value, and deletes the constructor's assignment.
func hoist(draft *scribes.Draft, assign engine.Match) {
	class := php.Node{Match: assign}.EnclosingClassLike()
	statement := assign.Parent()
	name := spatie.Node{Match: assign}.AssignedPropertyName()
	if !class.Exists() || !statement.Exists() || name == "" {
		return
	}
	property, declared := declaredProperty(class.Match, name)
	span, err := assign.Span()
	if err != nil || !declared || !property.Child("type").Exists() || span.Line() != engine.Source(span.Source).LineAt(span.End-1) {
		return
	}
	writer := For(draft, assign)
	writer.StampAttribute(property, "#[Computed]", computed)
	writer.DropModifier(property, "readonly")
	end := property.Node().Span.End
	writer.Rewrite(scribes.Edit{Start: end - 1, End: end, Text: " { get => " + textOf(assign.Child("expr")) + "; }"})
	writer.DeleteStatementLine(statement)
}

// declaredProperty is the class's property declaring the name alone; a grouped `public $a, $b;` cannot be split.
func declaredProperty(class engine.Match, name string) (engine.Match, bool) {
	for _, property := range class.ChildrenIn("stmts") {
		if property.Kind() != "Stmt_Property" {
			continue
		}
		if declared := property.ChildrenIn("props"); len(declared) == 1 && declared[0].Child("name").Name() == name {
			return property, true
		}
	}

	return engine.Match{}, false
}
