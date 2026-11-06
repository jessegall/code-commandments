package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// MemberAfterMethodDetector finds a property, constant, enum case or trait use declared below a method of its class.
type MemberAfterMethodDetector struct{}

func init() { detectors.Register(catalog.PHP, MemberAfterMethodDetector{}) }

func (MemberAfterMethodDetector) Sin() sins.Sin { return backendsins.MemberAfterMethod{} }

func (MemberAfterMethodDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind(classMembers...).
		Where(engine.As(php.Node.IsBelowAMethodInItsClass)).
		Get()
}

func (MemberAfterMethodDetector) Repentable() {}
