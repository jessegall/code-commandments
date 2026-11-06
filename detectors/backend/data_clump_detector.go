package backend

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// DataClumpDetector finds three or more scalar parameters that travel together through methods of different classes: a value object waiting to be named.
type DataClumpDetector struct {
	minClasses int
}

func init() { detectors.Register(catalog.PHP, DataClumpDetector{minClasses: 2}) }

func (DataClumpDetector) Sin() sins.Sin { return backendsins.DataClump{} }

func (d DataClumpDetector) MinClasses(classes int) DataClumpDetector {
	d.minClasses = classes

	return d
}

func (d DataClumpDetector) Find(codebase *engine.Codebase) []engine.Match {
	byClump := map[string][]engine.Match{}
	var clumps []string
	for _, method := range php.In(codebase).WhereKind("Stmt_ClassMethod").Get() {
		node := php.Node{Match: method}
		signature := node.ValueParamSignature()
		if len(signature) == 0 || node.IsConstructorDeclaration() || node.IsNamedConstructor() || node.NameIsInherited() {
			continue
		}
		key := strings.Join(signature, ", ")
		if byClump[key] == nil {
			clumps = append(clumps, key)
		}
		byClump[key] = append(byClump[key], method)
	}
	var findings []engine.Match
	for _, clump := range clumps {
		if distinctClasses(byClump[clump]) >= d.minClasses {
			findings = append(findings, byClump[clump]...)
		}
	}

	return findings
}

func distinctClasses(methods []engine.Match) int {
	classes := map[string]bool{}
	for _, method := range methods {
		class := php.EnclosingClassName(method)
		if class == "" {
			class = method.File()
		}
		classes[class] = true
	}

	return len(classes)
}

func (DataClumpDetector) CrossFile() {}
