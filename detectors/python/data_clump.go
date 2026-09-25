package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
	"strings"
)

// clumpOwners is how many owners must share a signature for it to be a clump.
const clumpOwners = 2

// DataClumpDetector finds the same three or more scalar parameters taken by defs of two or more owners: values that travel together.
type DataClumpDetector struct{}

func init() {
	detectors.Register(catalog.Python, DataClumpDetector{})
}

// Sin is the sin the detector finds.
func (DataClumpDetector) Sin() sins.Sin {
	return pysins.DataClump{}
}

// Find is every place the sin is committed.
func (DataClumpDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program
	candidates := py.In(codebase).
		WhereFunction().
		Where(engine.As(hasValueParamSignature)).
		Reject(engine.As(py.Node.IsConstructorDeclaration)).
		Reject(engine.As(program.IsOverride)).
		Reject(engine.As(py.Node.IsNamedConstructor)).
		Get()
	bySignature := map[string][]engine.Match{}
	for _, match := range candidates {
		key := strings.Join(py.Node{Match: match}.ValueParamSignature(), ", ")
		bySignature[key] = append(bySignature[key], match)
	}
	var findings []engine.Match
	for _, matches := range bySignature {
		owners := map[string]bool{}
		for _, match := range matches {
			owners[py.Node{Match: match}.Owner()] = true
		}
		if len(owners) >= clumpOwners {
			findings = append(findings, matches...)
		}
	}

	return findings
}

// hasValueParamSignature says whether the def takes three or more scalar parameters.
func hasValueParamSignature(n py.Node) bool {
	return len(n.ValueParamSignature()) > 0
}
