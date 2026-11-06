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

// PreferOptionalCreateDetector finds new Optional where Optional::create() belongs.
type PreferOptionalCreateDetector struct{}

func init() { detectors.Register(catalog.PHP, PreferOptionalCreateDetector{}) }

func (PreferOptionalCreateDetector) Sin() sins.Sin { return backendsins.PreferOptionalCreate{} }

func (PreferOptionalCreateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereNew().
		Where(engine.As(spatienode.Node.IsReplaceableNewOptional)).
		Get()
}

func (PreferOptionalCreateDetector) Repentable() {}
