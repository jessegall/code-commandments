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

// DanglingDocReferenceDetector finds a {@see} or {@link} to a class of the project's own namespace that it does not declare.
type DanglingDocReferenceDetector struct{}

func init() { detectors.Register(catalog.PHP, DanglingDocReferenceDetector{}) }

func (DanglingDocReferenceDetector) Sin() sins.Sin { return backendsins.DanglingDocReference{} }

func (DanglingDocReferenceDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class", "Stmt_ClassMethod").
		Where(engine.As(func(n php.Node) bool { return pointsAtNothing(codebase, n) })).
		Get()
}

func pointsAtNothing(codebase *engine.Codebase, node php.Node) bool {
	root := rootNamespace(php.EnclosingClassName(node.Match))
	if root == "" {
		return false
	}
	for _, reference := range node.DocReferences() {
		if rootNamespace(reference) != root {
			continue
		}
		if _, declared := php.ProgramOf(codebase).Declaration(reference); !declared {
			return true
		}
	}

	return false
}

func rootNamespace(class string) string {
	first, _, _ := strings.Cut(strings.TrimLeft(class, `\`), `\`)

	return first
}

func (DanglingDocReferenceDetector) WholeTree() {}

func (DanglingDocReferenceDetector) CrossFile() {}
