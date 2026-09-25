package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// SwallowCatch is the swallow-catch sin.
type SwallowCatch struct{}

func init() { sins.Register(catalog.Backend, SwallowCatch{}) }

// Definition is what the sin states about itself.
func (SwallowCatch) Definition() sins.Definition {
	return sins.Definition{
		Name:        "swallow-catch",
		Skill:       skills.Exceptions{},
		Description: "`catch` whose only effect is `return null/false/[]`; empty catch (silent swallow)",
		Rule:        `Let a failure throw, or surface it named with the cause; never swallow a catch into ` + "`" + `null` + "`" + `/` + "`" + `false` + "`" + `/` + "`" + `[]` + "`" + `/` + "`" + `none()` + "`" + ` or an empty body.`,
		Suggestion:  "Rethrow wrapped (`previous: $e`), or catch-log-skip at one named boundary.",
	}
}
