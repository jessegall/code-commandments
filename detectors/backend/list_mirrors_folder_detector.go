package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ListMirrorsFolderDetector finds a hand-written class list that repeats every class of one type its folders hold.
type ListMirrorsFolderDetector struct{}

func init() { detectors.Register(catalog.PHP, ListMirrorsFolderDetector{}) }

func (ListMirrorsFolderDetector) Sin() sins.Sin { return backendsins.ListMirrorsFolder{} }

func (ListMirrorsFolderDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Expr_Array").
		Where(engine.As(php.Node.IsHandedOver)).
		Where(engine.Match.ListsEveryClassOfItsFolders).
		Reject(engine.Match.IsTest).
		Get()
}

func (ListMirrorsFolderDetector) CrossFile() {}
