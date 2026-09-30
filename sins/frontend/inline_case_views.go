package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, InlineCaseViews{})
}

// InlineCaseViews is the sin "inline-case-views".
type InlineCaseViews struct{}

func (InlineCaseViews) Definition() sins.Definition {
	return sins.Definition{
		Name:        "inline-case-views",
		Skill:       frontendskill.VueComponents{},
		Description: "A dispatch whose cases each render a whole view inline — one component doing a job per case",
		Rule:        "Give each case of a dispatch that renders a whole view its own component; the dispatch only picks one.",
	}
}
