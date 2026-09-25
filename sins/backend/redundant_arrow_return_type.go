package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RedundantArrowReturnType is the redundant-arrow-return-type sin.
type RedundantArrowReturnType struct{}

func init() { sins.Register(catalog.Backend, RedundantArrowReturnType{}) }

// Definition is what the sin states about itself.
func (RedundantArrowReturnType) Definition() sins.Definition {
	return sins.Definition{
		Name:        "redundant-arrow-return-type",
		Skill:       skills.TypeHonesty{},
		Description: `An arrow function whose return type only repeats what its one expression provably yields — ` + "`" + `fn (): string => $this->name` + "`" + ` on a ` + "`" + `string` + "`" + ` property`,
		Rule:        `Leave the return type off an arrow function whose expression already proves the type. Declare one when the type is genuinely ambiguous or you are narrowing it — never to restate a property or a method you can read from here.`,
		Suggestion:  "drop the `: Type` — `repent` does this for you",
	}
}
