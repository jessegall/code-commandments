package backend

import (
	"slices"
	"strconv"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/namespaces"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// DerivedArgumentDetector finds arguments every caller derives from an object it also passes, or reassembles from
// one object three or more times over: the callee should take the object and derive them itself.
type DerivedArgumentDetector struct{}

func init() { detectors.Register(catalog.Backend, DerivedArgumentDetector{}) }

// reassembled is how many scalar slots one call must fill from one object for them to be that object taken apart.
const reassembled = 3

// Sin is the sin the detector finds.
func (DerivedArgumentDetector) Sin() sins.Sin { return backendsins.DerivedArgument{} }

// Find is every call filling a slot redundantly, where every call site of that slot does and no caller of that
// method name went unread.
func (DerivedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	types := php.TypesOf(codebase)
	redundant := map[string][]engine.Match{}
	var slots []string
	supplied, named, unresolved := map[string]int{}, map[string]string{}, map[string]bool{}
	for _, call := range callSites(codebase) {
		owner, method := types.Callee(call)
		if owner == "" {
			if call.Kind() != "Expr_New" && call.Kind() != "Expr_StaticCall" {
				unresolved[call.Child("name").Name()] = true
			}
			continue
		}
		if buildsItsOwnType(call) {
			continue
		}
		for position := range php.Arguments(call) {
			supplied[slotName(owner, method, position)]++
		}
		for _, position := range redundantPositions(codebase, call, owner, method) {
			slot := slotName(owner, method, position)
			if redundant[slot] == nil {
				slots = append(slots, slot)
			}
			redundant[slot] = append(redundant[slot], call)
			named[slot] = method
		}
	}
	var findings []engine.Match
	for _, slot := range slots {
		calls := redundant[slot]
		if len(calls) != supplied[slot] || unresolved[named[slot]] {
			continue
		}
		for _, call := range calls {
			if !slices.ContainsFunc(findings, func(found engine.Match) bool { return found.Node() == call.Node() }) {
				findings = append(findings, call)
			}
		}
	}

	return findings
}

func slotName(owner, method string, position int) string {
	return owner + "::" + method + "#" + strconv.Itoa(position)
}

// redundantPositions is every argument position of the call filled from an object the call also passes whole, or
// from one object that fills three or more scalar slots.
func redundantPositions(codebase *engine.Codebase, call engine.Match, owner, method string) []int {
	types := php.TypesOf(codebase)
	whole := map[string]bool{}
	flattened := map[string][]int{}
	var hashes []string
	for position, argument := range php.Arguments(call) {
		subject := subjectOf(argument)
		if !subject.Exists() || describesItself(codebase, subject, call) || wouldInvertADependency(codebase, owner, subject, call) {
			continue
		}
		declared := types.ParamTypeOf(owner, method, position)
		if declared == "" || declared == "mixed" {
			return nil
		}
		hash := php.StructuralHash(subject)
		if subject.Node() == argument.Child("value").Node() {
			whole[hash] = true
			continue
		}
		if !php.IsClassName(declared) {
			if flattened[hash] == nil {
				hashes = append(hashes, hash)
			}
			flattened[hash] = append(flattened[hash], position)
		}
	}
	var positions []int
	for _, hash := range hashes {
		if whole[hash] || len(flattened[hash]) >= reassembled {
			positions = append(positions, flattened[hash]...)
		}
	}

	return positions
}

// describesItself says whether the subject is the calling class's own instance: passing it on is no derivation.
func describesItself(codebase *engine.Codebase, subject, call engine.Match) bool {
	self := php.EnclosingClassName(call)
	if !(php.Node{Match: call}).EnclosingFunctionLike().Exists() || self == "" {
		return false
	}
	resolved := php.TypesOf(codebase).TypeOf(subject)

	return resolved == "self" || resolved == "static" || resolved == self
}

// wouldInvertADependency says whether handing the callee the subject's type would make its namespace reference one
// that already references it.
func wouldInvertADependency(codebase *engine.Codebase, owner string, subject, call engine.Match) bool {
	if !(php.Node{Match: call}).EnclosingFunctionLike().Exists() || php.EnclosingClassName(call) == "" {
		return false
	}

	return namespaces.Of(codebase).WouldCloseACycle(owner, php.TypesOf(codebase).TypeOf(subject))
}

// buildsItsOwnType says whether the call is a new of the class it is written in.
func buildsItsOwnType(call engine.Match) bool {
	built := (php.Node{Match: call}).NewClassName()

	return built != "" && built == php.EnclosingClassName(call)
}

// subjectOf is the object a positional argument is derived from: a variable or class passed whole, or the one
// variable or class a read or call reads; no node when there is none.
func subjectOf(argument engine.Match) engine.Match {
	if slices.Contains(argument.Node().Flags, "spread") || argument.Child("name").Exists() {
		return engine.Match{}
	}
	value := argument.Child("value")
	switch value.Kind() {
	case "Expr_ClassConstFetch":
		if php.NamesAClass(value) {
			return value
		}

		return engine.Match{}
	case "Expr_Variable":
		if value.Name() == "this" {
			return engine.Match{}
		}

		return value
	case "Expr_Array":
		return engine.Match{}
	}
	if php.RoutesThroughSelf(value) || !php.IsReadOfSomething(value) {
		return engine.Match{}
	}
	sole := php.SoleOperandIn(value)
	switch {
	case sole.Kind() == "Expr_ClassConstFetch" && php.NamesAClass(sole):
		return sole
	case sole.Kind() == "Expr_Variable" && sole.Name() != "this":
		return sole
	}

	return engine.Match{}
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (DerivedArgumentDetector) WholeTree() {}
