package backend

import (
	"slices"
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.NewDataObjectDetector{}, func() scribes.Scribe { return NewDataObjectScribe{} })
}

// NewDataObjectScribe builds a Data through `X::from([...])` rather than `new X(...)`.
type NewDataObjectScribe struct{}

// Rewrite replaces each construction whose keys it can name for certain with the from() it means, laid out as the
// arguments were.
func (NewDataObjectScribe) Rewrite(findings []engine.Match, codebase *engine.Codebase) (scribes.Rewrites, error) {
	shape := spatie.DataClassShapeOf(codebase)
	draft := scribes.NewDraft()
	for _, finding := range findings {
		text, rewrites := fromCall(finding, shape)
		if !rewrites {
			continue
		}
		span, err := finding.Span()
		if err != nil {
			return scribes.Rewrites{}, err
		}
		draft.Edit(span, text)
	}

	return draft.Rewrites(), nil
}

// fromCall is `X::from(['key' => value, ...])` for a construction of a Data whose shape is known and keeps its input
// names; nothing when an argument is unpacked or its key cannot be named.
func fromCall(finding engine.Match, shape *spatie.DataClassShape) (string, bool) {
	class := finding.Child("class")
	if finding.Kind() != "Expr_New" || !strings.HasPrefix(class.Kind(), "Name") {
		return "", false
	}
	declaration, known := shape.ClassFor(class.Name())
	if !known || shape.RemapsInputNames(class.Name()) {
		return "", false
	}
	params := php.ConstructorParams(declaration)
	var entries []string
	for index, argument := range finding.ChildrenIn("args") {
		key, named := textOf(argument.Child("name")), argument.Child("name").Exists()
		if !named {
			key, named = php.PromotedParamName(params, index)
		}
		if slices.Contains(argument.Node().Flags, "spread") || !named {
			return "", false
		}
		entries = append(entries, "'"+key+"' => "+textOf(argument.Child("value")))
	}

	return textOf(class) + "::from([" + fromBody(entries, finding) + "])", true
}

// fromBody is the entries on one line, or one a line when the construction laid its arguments out a line each.
func fromBody(entries []string, construction engine.Match) string {
	span, err := construction.Span()
	arguments := construction.ChildrenIn("args")
	if err != nil || len(arguments) == 0 {
		return strings.Join(entries, ", ")
	}
	source := engine.Source(span.Source)
	inner, innerOwn := source.OwnLineIndent(arguments[0].Node().Span.Start)
	outer, outerOwn := source.OwnLineIndent(span.End - 1)
	if !innerOwn || !outerOwn || span.Line() == source.LineAt(span.End-1) {
		return strings.Join(entries, ", ")
	}
	var body strings.Builder
	body.WriteString("\n")
	for _, entry := range entries {
		body.WriteString(inner + entry + ",\n")
	}

	return body.String() + outer
}
