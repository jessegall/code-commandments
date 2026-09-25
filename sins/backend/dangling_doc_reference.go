package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// DanglingDocReference is the dangling-doc-reference sin.
type DanglingDocReference struct{}

func init() { sins.Register(catalog.Backend, DanglingDocReference{}) }

// Definition is what the sin states about itself.
func (DanglingDocReference) Definition() sins.Definition {
	return sins.Definition{
		Name:        "dangling-doc-reference",
		Skill:       skills.Documentation{},
		Description: `A docblock ` + "`" + `{@see}` + "`" + `/` + "`" + `{@link}` + "`" + ` cross-references a FIRST-PARTY class that does not exist in the codebase — documentation pointing at a name that was renamed or removed, never at what the code actually is`,
		Rule:        `A ` + "`" + `{@see}` + "`" + `/` + "`" + `{@link}` + "`" + ` must resolve to a real class. A cross-reference to a first-party class the codebase no longer declares is stale documentation — repoint it at the current class or delete it. (References into another vendor namespace are left alone; they can't be verified here.)`,
	}
}
