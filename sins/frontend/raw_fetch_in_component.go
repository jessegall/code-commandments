package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Vue, RawFetchInComponent{})
}

// RawFetchInComponent is the sin "raw-fetch-in-component".
type RawFetchInComponent struct{}

func (RawFetchInComponent) Definition() sins.Definition {
	return sins.Definition{
		Name:        "raw-fetch-in-component",
		Skill:       frontendskill.ServerCalls{},
		Description: "A Vue component calls the global `fetch` itself instead of the one client module",
		Rule:        "Components never talk to the server with `fetch`; one client module owns the headers, the errors and the parsing.",
		Suggestion:  "Call the project's client module (or `router` / `useForm`) and move any headers the component sets into that module.",
	}
}
