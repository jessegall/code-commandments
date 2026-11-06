package backend

import (
	"strings"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(spatie.ManualHydrationLoopDetector{}, func() scribes.Scribe { return ManualHydrationLoopScribe{} })
}

// ManualHydrationLoopScribe rewrites `array_map(X::from(...), $items)` into `X::collect($items)`.
type ManualHydrationLoopScribe struct{}

func (ManualHydrationLoopScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, from := range findings {
		class := from.Child("class")
		if from.Kind() != "Expr_StaticCall" || !strings.HasPrefix(class.Kind(), "Name") {
			continue
		}
		call, maps := mappingArrayMap(from)
		if !maps {
			continue
		}
		items := call.ChildrenIn("args")[1].Child("value")
		For(draft, from).Replace(call, textOf(class)+"::collect("+textOf(items)+")")
	}

	return draft.Rewrites(), nil
}

func mappingArrayMap(from engine.Match) (engine.Match, bool) {
	parent := from.Parent()
	if parent.Kind() == "Expr_ArrowFunction" {
		if !passesParamVerbatim(parent, from) {
			return engine.Match{}, false
		}
		parent = parent.Parent()
	} else if !isFirstClassCallable(from) {
		return engine.Match{}, false
	}
	if parent.Kind() != "Arg" {
		return engine.Match{}, false
	}
	call := parent.Parent()
	arguments := call.ChildrenIn("args")
	name := call.Child("name")
	if call.Kind() != "Expr_FuncCall" || !strings.HasPrefix(name.Kind(), "Name") || strings.TrimLeft(textOf(name), `\`) != "array_map" ||
		len(arguments) != 2 || arguments[0].Node() != parent.Node() || arguments[1].Kind() != "Arg" {
		return engine.Match{}, false
	}

	return call, true
}

func passesParamVerbatim(arrow, from engine.Match) bool {
	params, arguments := arrow.ChildrenIn("params"), from.ChildrenIn("args")
	if len(params) != 1 || arrow.Child("expr").Node() != from.Node() || len(arguments) != 1 {
		return false
	}
	param, value := params[0].Child("var"), arguments[0].Child("value")

	return param.Kind() == "Expr_Variable" && param.Name() != "" && arguments[0].Kind() == "Arg" &&
		value.Kind() == "Expr_Variable" && value.Name() == param.Name()
}

func isFirstClassCallable(from engine.Match) bool {
	arguments := from.ChildrenIn("args")

	return len(arguments) == 1 && arguments[0].Kind() == "VariadicPlaceholder"
}
