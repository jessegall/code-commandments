package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ErasedNullObject is the erased-null-object sin.
type ErasedNullObject struct{}

func init() { sins.Register(catalog.Backend, ErasedNullObject{}) }

// Definition is what the sin states about itself.
func (ErasedNullObject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "erased-null-object",
		Skill:       skills.Absence{},
		Description: "A blank-rendering Null Object written into a `string` slot — coerced back to `''`",
		Rule:        `A Null Object only models absence while the TYPE admits it; never hand one to a ` + "`" + `string` + "`" + `-typed slot, which coerces it to ` + "`" + `''` + "`" + ` and erases it.`,
		Suggestion:  `Widen the type to carry the object (` + "`" + `Stringable` + "`" + `, or the class itself) where the Null Object is the point; otherwise drop the wrapper — and where the blank meant "missing", say that in the type with ` + "`" + `?string` + "`" + ` or an ` + "`" + `Option<string>` + "`" + `.`,
	}
}
