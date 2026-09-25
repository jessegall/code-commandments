package backend

import (
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.AllNullableDataDetector{}, func() scribes.Scribe { return NullObjectDefaultScribe{} })
}

// NullObjectDefaultScribe reshapes a Data whose every field is a nullable object defaulting to null into one whose
// fields default to their Null Objects, typed as never null.
type NullObjectDefaultScribe struct{}

// Rewrite reshapes each class whose every promoted field has an expressible Null Object; a class with one that has
// none is left whole.
func (NullObjectDefaultScribe) Rewrite(findings []engine.Match, codebase *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, class := range classDeclarations(findings) {
		reshape(draft, class, codebase)
	}

	return draft.Rewrites(), nil
}

// fieldReshape is one field's type written without null and the Null Object its default becomes.
type fieldReshape struct {
	typed, fallback engine.Match
	written, object string
}

func reshape(draft *scribes.Draft, class engine.Match, codebase *engine.Codebase) {
	var reshapes []fieldReshape
	for _, param := range php.ConstructorParams(class) {
		if len(param.Node().Modifiers) == 0 {
			continue
		}
		written, nullable := writtenNonNullType(param.Child("type"))
		if !nullable || !defaultsToNull(param) {
			return
		}
		object, expressible := php.NullObjectFor(codebase, php.Written(param.Node().Declared).NullableClass(), written)
		if !expressible {
			return
		}
		reshapes = append(reshapes, fieldReshape{typed: param.Child("type"), fallback: param.Child("default"), written: written, object: object})
	}
	writer := For(draft, class)
	for _, field := range reshapes {
		writer.Replace(field.typed, field.written)
		writer.Replace(field.fallback, field.object)
	}
}

// writtenNonNullType is a nullable type as written with its null taken off: `?T` is `T`, `A|B|null` is `A|B`.
func writtenNonNullType(typed engine.Match) (string, bool) {
	switch typed.Kind() {
	case "NullableType":
		return textOf(typed.Child("type")), true
	case "UnionType":
		var parts []string
		for _, member := range typed.ChildrenIn("types") {
			if !isNullMember(member) {
				parts = append(parts, textOf(member))
			}
		}

		return strings.Join(parts, "|"), len(parts) > 0
	}

	return "", false
}

func isNullMember(member engine.Match) bool {
	kind := member.Kind()

	return (kind == "Identifier" || strings.HasPrefix(kind, "Name")) && strings.ToLower(member.Name()) == "null"
}

// defaultsToNull says whether a parameter's default is null.
func defaultsToNull(param engine.Match) bool {
	fallback := param.Child("default")

	return fallback.Kind() == "Expr_ConstFetch" && strings.ToLower(fallback.Child("name").Name()) == "null"
}
