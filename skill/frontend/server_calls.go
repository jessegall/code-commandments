package frontend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

func init() {
	skill.Register(catalog.Vue, ServerCalls{})
}

//go:embed text/server-calls/intro.md
var serverCallsIntro string

//go:embed text/server-calls/principle.md
var serverCallsPrinciple string

// ServerCalls teaches: the frontend talks to the server through one layer, and every server address comes from the generated routes, never a string written by hand.
type ServerCalls struct{}

func (ServerCalls) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "vue/server-calls",
		Tier:      skill.KeepInMind,
		Order:     26,
		Title:     "One way to the server: generated routes and one client",
		Trigger:   "You are about to call `fetch(` in a component, write a server path like `'/plans/' + id` or `` `/api/tasks/${id}` ``, or set request headers by hand. Read this BEFORE you do: the server already declares its routes, and the frontend reaches them through the generated route helpers and one client module.",
		Intro:     serverCallsIntro,
		Summary:   "reach the server through one client module and the generated routes; never `fetch` from a component or write a server path by hand.",
		Principle: serverCallsPrinciple,
		Languages: []string{"vue", "ts"},
		Related: []skill.Relation{
			{Slug: "vue/mirrored-server-type", Note: "the same rule for the shapes the server sends: generate them, never copy them."},
		},
	}
}
