package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// NullToOptionalMap is the null-to-optional-map sin.
type NullToOptionalMap struct{}

func init() { sins.Register(catalog.Backend, NullToOptionalMap{}) }

// Definition is what the sin states about itself.
func (NullToOptionalMap) Definition() sins.Definition {
	return sins.Definition{
		Name:        "null-to-optional-map",
		Skill:       spatieskills.SpatieData{},
		Description: `A producer hand-maps null→` + "`" + `new Optional` + "`" + ` — ` + "`" + `$x === null ? new Optional : Foo::from($x)` + "`" + ` or ` + "`" + `expr() ?? new Optional` + "`" + ` — instead of one named factory (Spatie's ` + "`" + `optional()` + "`" + ` maps null→null, the opposite of what a ` + "`" + `T|Optional` + "`" + ` slot needs)`,
		Rule:        `Map an absent value onto ` + "`" + `Optional` + "`" + ` in ONE named factory, not a ternary at every producer. Use the scaffolded ` + "`" + `optionalOrMissing()` + "`" + ` (null → ` + "`" + `new Optional` + "`" + `, else ` + "`" + `::from` + "`" + `), the omit-from-wire counterpart to Spatie's ` + "`" + `optional()` + "`" + `.`,
		Suggestion:  `Replace ` + "`" + `$x === null ? new Optional : Foo::from($x)` + "`" + ` / ` + "`" + `expr() ?? new Optional` + "`" + ` with ` + "`" + `Foo::optionalOrMissing($x)` + "`" + ` (scaffold the ` + "`" + `OptionalOrMissing` + "`" + ` trait).`,
		Scaffolds: []sins.Scaffold{
			{Path: "Support/OptionalOrMissing.php", Stub: "OptionalOrMissing.php.stub", Target: sins.BackendRoot},
		},
		Requires: requiresSpatieData,
	}
}
