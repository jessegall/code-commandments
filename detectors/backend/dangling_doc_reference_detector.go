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

// DanglingDocReferenceDetector finds a {@see} or {@link} to a class of the project's own namespace that it does not
// declare.
type DanglingDocReferenceDetector struct{}

func init() { detectors.Register(catalog.Backend, DanglingDocReferenceDetector{}) }

// Sin is the sin the detector finds.
func (DanglingDocReferenceDetector) Sin() sins.Sin { return backendsins.DanglingDocReference{} }

// Find is every class and method whose doc comment points at a first-party class nothing declares.
func (DanglingDocReferenceDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereKind("Stmt_Class", "Stmt_ClassMethod").
		Where(engine.As(func(n php.Node) bool { return pointsAtNothing(codebase, n) })).
		Get()
}

// pointsAtNothing says whether the node's doc comment names a class under its own root namespace that no file
// declares; another vendor's namespace cannot be checked from here.
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

// rootNamespace is a class name's first segment; empty for no name.
func rootNamespace(class string) string {
	first, _, _ := strings.Cut(strings.TrimLeft(class, `\`), `\`)

	return first
}

// WholeTree says the verdict needs every file: a reference is dangling only if no file declares it.
func (DanglingDocReferenceDetector) WholeTree() {}
