package phptypes

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// OptionAsNullable is the option-as-nullable sin.
type OptionAsNullable struct{}

func init() { sins.Register(catalog.Backend, OptionAsNullable{}) }

// Definition is what the sin states about itself.
func (OptionAsNullable) Definition() sins.Definition {
	return sins.Definition{
		Name:        "option-as-nullable",
		Skill:       skills.Absence{},
		Description: "`Option<T>` used as if it were nullable — `?Option`, `Option | null`, `unwrapOr(null)`",
		Rule:        `Use ` + "`" + `Option` + "`" + ` as a real option (` + "`" + `some` + "`" + `/` + "`" + `none` + "`" + `/` + "`" + `match` + "`" + `); never ` + "`" + `?Option` + "`" + `/` + "`" + `Option | null` + "`" + `/` + "`" + `unwrapOr(null)` + "`" + `.`,
		Suggestion:  "Wrap at the seam with `Option::fromNullable($x)`, then consume with `match`/`unwrapOr`.",
		Requires:    requiresPhpTypes,
	}
}
