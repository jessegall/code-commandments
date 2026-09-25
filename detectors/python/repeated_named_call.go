package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
	"slices"
	"strings"
)

// namedCallThreshold is how many sites must make the same call.
const namedCallThreshold = 2

// RepeatedNamedCallDetector finds the same keyword call, building the same shapes, made to a def taking **kwargs at two or more sites: a missing method.
type RepeatedNamedCallDetector struct{}

func init() {
	detectors.Register(catalog.Python, RepeatedNamedCallDetector{})
}

// Sin is the sin the detector finds.
func (RepeatedNamedCallDetector) Sin() sins.Sin {
	return pysins.RepeatedNamedCall{}
}

// Find is every place the sin is committed.
func (RepeatedNamedCallDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program
	calls := py.In(codebase).WhereCall().Where(engine.As(py.Node.IsEvaluated)).Get()

	return flatten(engine.RecurringBuckets(calls, func(match engine.Match) (string, bool) { return namedCallKey(program, py.Node{Match: match}) }, namedCallThreshold))
}

// namedCallKey is the call's target and the shape of what each of its keywords builds, when one of them builds
// something and the target takes **kwargs.
func namedCallKey(program *py.Program, call py.Node) (string, bool) {
	keywords := slices.DeleteFunc(call.Keywords(), func(keyword py.Node) bool { return keyword.Name() == "" })
	target, ok := program.TargetOf(call)
	if !ok || !target.TakesKeywordRest() || !slices.ContainsFunc(keywords, func(keyword py.Node) bool { return keyword.Child("value").IsConstruction() }) {
		return "", false
	}
	var slots []string
	for _, keyword := range keywords {
		slots = append(slots, keyword.Name()+"="+keyword.Child("value").ConstructionShape())
	}
	slices.Sort(slots)

	return py.DeclarationOf(target) + "#" + strings.Join(slots, ","), true
}
