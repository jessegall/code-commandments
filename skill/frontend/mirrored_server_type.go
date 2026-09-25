package frontend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

func init() {
	skill.Register(catalog.Frontend, MirroredServerType{})
}

//go:embed text/mirrored-server-type/intro.md
var mirroredServerTypeIntro string

//go:embed text/mirrored-server-type/principle.md
var mirroredServerTypePrinciple string

// MirroredServerType teaches: a hand-written TS type that mirrors a backend Data class is a duplicated contract — mark the Data class `#[TypeScript]`, generate the type, and import the generated one.
type MirroredServerType struct{}

func (MirroredServerType) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "frontend/mirrored-server-type",
		Tier:      skill.KeepInMind,
		Order:     25,
		Title:     "One source of truth for a server contract — generate the type, don't hand-copy it",
		Trigger:   "You are about to hand-write a TypeScript `interface`/`type` whose fields match a backend `Data` class — a `UserData`, an `OrderData`, the shape an endpoint returns. Read this BEFORE typing out fields that already exist on the server. If the backend owns the shape, the frontend must GENERATE its type from it, not re-declare it.",
		Intro:     mirroredServerTypeIntro,
		Summary:   "a hand-written TS type that mirrors a backend Data class is a duplicated contract — mark the Data class `#[TypeScript]`, generate the type, and import the generated one.",
		Principle: mirroredServerTypePrinciple,
		Languages: []string{"vue", "ts"},
	}
}
