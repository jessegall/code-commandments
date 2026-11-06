package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// MemberOutOfOrderDetector finds a member of a class's head that sits below one ranking after it: trait uses, cases, constants, then properties.
type MemberOutOfOrderDetector struct{}

func init() { detectors.Register(catalog.PHP, MemberOutOfOrderDetector{}) }

func (MemberOutOfOrderDetector) Sin() sins.Sin { return backendsins.MemberOutOfOrder{} }

func (MemberOutOfOrderDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind(classMembers...).
		Reject(engine.As(php.Node.IsBelowAMethodInItsClass)).
		Where(engine.As(php.Node.BreaksClassLayoutOrder)).
		Get()
}

func (MemberOutOfOrderDetector) Repentable() {}
