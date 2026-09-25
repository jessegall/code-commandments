package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// NullableCallback is the nullable-callback sin.
type NullableCallback struct{}

func init() { sins.Register(catalog.Backend, NullableCallback{}) }

// Definition is what the sin states about itself.
func (NullableCallback) Definition() sins.Definition {
	return sins.Definition{
		Name:        "nullable-callback",
		Skill:       skills.Absence{},
		Description: "Nullable callback normalised in the body instead of a Null Object default",
		Rule:        `Default an optional callback to a Null Object in the signature; don't null-normalise a ` + "`" + `?callable` + "`" + ` in the body.`,
		Suggestion:  "Create a reusable no-op invokable (`Invokable` + `NoOp`) and default the param to `new NoOp`.",
		Scaffolds: []sins.Scaffold{
			{Path: "Support/Invokable.php", Stub: "Invokable.php.stub", Target: sins.BackendRoot},
			{Path: "Support/NoOp.php", Stub: "NoOp.php.stub", Target: sins.BackendRoot},
		},
	}
}
