package backend

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RepeatedNamedCallDetector finds one variadic with-style method called with the same named construction at several sites: a method missing on the receiver's type.
type RepeatedNamedCallDetector struct {
	threshold int
}

func init() { detectors.Register(catalog.PHP, RepeatedNamedCallDetector{threshold: 2}) }

func (RepeatedNamedCallDetector) Sin() sins.Sin { return backendsins.RepeatedNamedCall{} }

func (d RepeatedNamedCallDetector) Threshold(times int) RepeatedNamedCallDetector {
	d.threshold = max(2, times)

	return d
}

func (RepeatedNamedCallDetector) GroupKey(finding engine.Match) (string, bool) {
	codebase := finding.Codebase()
	key := namedCallFingerprint(finding, codebase)

	return key, key != ""
}

func (d RepeatedNamedCallDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := php.In(codebase).Where(func(m engine.Match) bool { return (php.Node{Match: m}).MethodCallName() != "" }).Get()

	return recurring(candidates, d.GroupKey, d.threshold, func([]engine.Match) bool { return true })
}

func namedCallFingerprint(call engine.Match, codebase *engine.Codebase) string {
	types := php.TypesOf(codebase)
	named := (php.Node{Match: call}).NamedArguments()
	if len(named) == 0 || !slices.ContainsFunc(named, carriesConstruction) {
		return ""
	}
	method, receiver := (php.Node{Match: call}).MethodCallName(), php.ReceiverTypeOf(call)
	if method == "" || receiver == "" || !types.MethodIsVariadic(receiver, method) {
		return ""
	}
	var slots []string
	for _, argument := range named {
		slots = append(slots, argument.Child("name").Name()+"="+shapeOf(argument.Child("value")))
	}
	slices.Sort(slots)

	return types.DeclaringClassOfMethod(receiver, method) + "::" + method + "#" + strings.Join(slots, ",")
}

func carriesConstruction(argument engine.Match) bool {
	switch argument.Child("value").Kind() {
	case "Expr_StaticCall", "Expr_MethodCall", "Expr_New", "Expr_Array":
		return true
	}

	return false
}

func shapeOf(expr engine.Match) string {
	name := expr.Child("name")
	named := func() string {
		if name.Kind() == "Identifier" || strings.HasPrefix(name.Kind(), "Name") {
			return name.Name()
		}

		return "?"
	}
	switch expr.Kind() {
	case "Expr_MethodCall", "Expr_NullsafeMethodCall":
		return shapeOf(expr.Child("var")) + "->" + named()
	case "Expr_StaticCall":
		return "::" + named()
	case "Expr_FuncCall":
		return "fn:" + named()
	case "Expr_New":
		return "new"
	case "Expr_Array":
		return "[]"
	}

	return expr.Kind()
}

func (RepeatedNamedCallDetector) CrossFile() {}
