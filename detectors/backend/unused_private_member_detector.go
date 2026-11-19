package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// UnusedPrivateMemberDetector finds the unused-private-member sin.
type UnusedPrivateMemberDetector struct{}

func init() { detectors.Register(catalog.PHP, UnusedPrivateMemberDetector{}) }

func (UnusedPrivateMemberDetector) Sin() sins.Sin { return backendsins.UnusedPrivateMember{} }

func (UnusedPrivateMemberDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsUnusedPrivateMember)).
		Reject(engine.Match.IsTest).
		Get()
}
