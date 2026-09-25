package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// ManualInputCast is the manual-input-cast sin.
type ManualInputCast struct{}

func init() { sins.Register(catalog.Backend, ManualInputCast{}) }

// Definition is what the sin states about itself.
func (ManualInputCast) Definition() sins.Definition {
	return sins.Definition{
		Name:        "manual-input-cast",
		Skill:       spatieskills.SpatieData{},
		Description: `A ` + "`" + `Data` + "`" + ` value-object property is hand-built at every construction site, instead of a ` + "`" + `#[WithCast]` + "`" + ` / ` + "`" + `Castable` + "`" + ` that owns the hydration once`,
		Rule:        `Hydrate a value-object property with a ` + "`" + `#[WithCast]` + "`" + ` (or a ` + "`" + `Castable` + "`" + ` value object), never by hand-building it at every ` + "`" + `new` + "`" + `/` + "`" + `::from` + "`" + ` call site.`,
		Suggestion:  `Type the property as the value object and add ` + "`" + `#[WithCast(SomeCast::class)]` + "`" + ` (or make the value object ` + "`" + `Castable` + "`" + `) so the ` + "`" + `simple → complex` + "`" + ` mapping lives in one place.`,
		Requires:    requiresSpatieData,
	}
}
