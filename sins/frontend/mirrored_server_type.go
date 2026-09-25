package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, MirroredServerType{})
}

// MirroredServerType is the sin "mirrored-server-type".
type MirroredServerType struct{}

func (MirroredServerType) Definition() sins.Definition {
	return sins.Definition{
		Name:        "mirrored-server-type",
		Skill:       frontendskill.MirroredServerType{},
		Description: "A hand-written TypeScript type mirrors a backend `Data` class one-to-one — two sources of truth for one contract that drift the moment the server shape changes",
		Rule:        "Let the server own the shape: mark the `Data` class `#[TypeScript]`, generate the type, and import the generated one. Never hand-maintain a copy of a server contract.",
	}
}
