package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// SwallowedException is a bare `catch` or `catch (Exception)` whose body is empty, continues, or returns nothing — every failure, expected or not, made to vanish.
type SwallowedException struct{}

func init() {
	sins.Register(catalog.CSharp, SwallowedException{})
}

// Definition is what the sin states about itself.
func (SwallowedException) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-swallowed-exception",
		Skill:       skills.Exceptions{},
		Description: "A bare `catch` or `catch (Exception)` whose body is empty, continues, or returns nothing — every failure, expected or not, made to vanish",
		Rule:        "Never swallow every failure: catch the one you expect and act on it, or let it propagate to a boundary that records it.",
		Suggestion:  "Name the exception you expect (`catch (FileNotFoundException)`), or filter it with `when`, and do what its meaning calls for; anything else propagates. At a real boundary, log or report before moving on.",
	}
}
