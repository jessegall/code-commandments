package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// PreferOptionalCreate is the prefer-optional-create sin.
type PreferOptionalCreate struct{}

func init() { sins.Register(catalog.Backend, PreferOptionalCreate{}) }

// Definition is what the sin states about itself.
func (PreferOptionalCreate) Definition() sins.Definition {
	return sins.Definition{
		Name:        "prefer-optional-create",
		Skill:       spatieskills.SpatieData{},
		Description: `A raw ` + "`" + `new Optional` + "`" + ` is constructed in a runtime expression where Spatie's built-in ` + "`" + `Optional::create()` + "`" + ` factory reads clearer`,
		Rule:        `Use ` + "`" + `Optional::create()` + "`" + `, not ` + "`" + `new Optional` + "`" + `, everywhere a static call is legal — a parameter/property default must stay ` + "`" + `new Optional` + "`" + ` (a factory call is illegal there), everywhere else prefer the factory.`,
		Suggestion:  "Replace `new Optional` / `new Optional()` with `Optional::create()`.",
		Requires:    requiresSpatieData,
	}
}
