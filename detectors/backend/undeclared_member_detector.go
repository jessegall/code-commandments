package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// UndeclaredMemberDetector finds a member read on a value whose declared class or interface does not have that member.
type UndeclaredMemberDetector struct{}

func init() { detectors.Register(catalog.PHP, UndeclaredMemberDetector{}) }

func (UndeclaredMemberDetector) Sin() sins.Sin { return backendsins.UndeclaredMember{} }

func (UndeclaredMemberDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.ReachesAMemberItsTypeLacks)).
		Reject(engine.Match.IsTest).
		Get()
}

func (UndeclaredMemberDetector) CrossFile() {}

func (UndeclaredMemberDetector) Unpublished() {}
