package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// RedundantNativeCastDetector finds a value built by hand for a Data slot the package casts natively: a date, an enum.
type RedundantNativeCastDetector struct{}

func init() { detectors.Register(catalog.PHP, RedundantNativeCastDetector{}) }

func (RedundantNativeCastDetector) Sin() sins.Sin { return backendsins.RedundantNativeCast{} }

func (RedundantNativeCastDetector) Find(codebase *engine.Codebase) []engine.Match {
	factories := php.In(codebase).WhereKind("Expr_StaticCall").Where(func(n engine.Match) bool {
		return n.Child("name").Name() == "from" || n.Child("name").Name() == "parse"
	})

	return append(nativeCastGate(factories), nativeCastGate(php.In(codebase).WhereNew())...)
}

func nativeCastGate(query *engine.Query) []engine.Match {
	return query.
		Where(engine.As(spatienode.Node.ConstructsNativeCastValue)).
		Where(engine.As(spatienode.Node.HasSingleArgument)).
		Where(engine.As(spatienode.Node.SlotAcceptsNativeCast)).
		Reject(engine.As(spatienode.Node.HydrationSlotHasCast)).
		Get()
}

func (RedundantNativeCastDetector) Repentable() {}

func (RedundantNativeCastDetector) CrossFile() {}
