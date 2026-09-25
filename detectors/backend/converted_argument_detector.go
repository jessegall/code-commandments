package backend

import (
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ConvertedArgumentDetector finds a scalar parameter that callers mostly fill by converting a value through the same
// static factory: the parameter should take the converted type.
type ConvertedArgumentDetector struct{}

func init() { detectors.Register(catalog.Backend, ConvertedArgumentDetector{}) }

// dominant is the share of a slot's call sites that must convert for the conversion to belong in the callee.
const dominant = 0.5

// Sin is the sin the detector finds.
func (ConvertedArgumentDetector) Sin() sins.Sin { return backendsins.ConvertedArgument{} }

// GroupKey is the slot a call converts an argument for and the conversion it uses.
func (ConvertedArgumentDetector) GroupKey(finding engine.Match) (string, bool) {
	codebase := finding.Codebase()
	key := conversionFingerprint(finding, codebase)

	return key, key != ""
}

// Find is every call converting an argument for a scalar slot the same way twice or more, where that conversion
// fills half or more of the slot's call sites.
func (ConvertedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	supplied := suppliedSlots(codebase)
	return recurring(callSites(codebase), ConvertedArgumentDetector{}.GroupKey, 2, func(group []engine.Match) bool {
		slot, _, _ := strings.Cut(conversionFingerprint(group[0], codebase), "=")
		sites := supplied[slot]

		return sites > 0 && float64(len(group))/float64(sites) >= dominant
	})
}

func conversionFingerprint(call engine.Match, codebase *engine.Codebase) string {
	types := php.TypesOf(codebase)
	owner, method := types.Callee(call)
	if owner == "" {
		return ""
	}
	for position, argument := range php.Arguments(call) {
		wrapper := conversionIn(argument, call)
		declared := types.ParamTypeOf(owner, method, position)
		if wrapper != "" && declared != "" && !php.IsClassName(declared) {
			return owner + "::" + method + "#" + strconv.Itoa(position) + "=" + wrapper
		}
	}

	return ""
}

// conversionIn is the Class::method a positional argument is converted through by a one-argument static call to
// another class; empty otherwise.
func conversionIn(argument, call engine.Match) string {
	value := argument.Child("value")
	if slices.Contains(argument.Node().Flags, "spread") || argument.Child("name").Exists() || value.Kind() != "Expr_StaticCall" {
		return ""
	}
	class, name := value.Child("class"), value.Child("name")
	if !strings.HasPrefix(class.Kind(), "Name") || name.Kind() != "Identifier" {
		return ""
	}
	passed := 0
	for _, child := range value.Children() {
		if child.Node().Field == "args" {
			passed++
		}
	}
	if lowered := strings.ToLower(class.Name()); passed != 1 || lowered == "self" || lowered == "static" || lowered == "parent" {
		return ""
	}
	if class.Name() == php.EnclosingClassName(call) {
		return ""
	}

	return class.Name() + "::" + name.Name()
}

// suppliedSlots is how many call sites fill each callee slot, per codebase.
func suppliedSlots(codebase *engine.Codebase) map[string]int {
	return engine.Analysis(codebase, "backend/supplied-slots", func(codebase *engine.Codebase) map[string]int {
		types := php.TypesOf(codebase)
		counts := map[string]int{}
		for _, call := range callSites(codebase) {
			owner, method := types.Callee(call)
			if owner == "" {
				continue
			}
			for position := range php.Arguments(call) {
				counts[owner+"::"+method+"#"+strconv.Itoa(position)]++
			}
		}

		return counts
	})
}
