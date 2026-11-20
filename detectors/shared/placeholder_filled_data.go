package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// PlaceholderFilledData finds a blank value supplied to a required data field.
type PlaceholderFilledData struct{}

func (PlaceholderFilledData) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.FillsDataWithPlaceholder).Reject(engine.Match.IsTest).Get()
}

func (PlaceholderFilledData) Sin() Text {
	return Text{
		Name:        "placeholder-filled-data",
		Skill:       skills.Absence{},
		Description: "A blank string fills a required string field in a data type",
		Rule:        "Supply the required value or make its absence part of the type.",
		Suggestion:  "Validate the data before constructing it, or make a separate type for the incomplete state.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-placeholder-filled-data"},
			catalog.CSharp: {Sin: "csharp-placeholder-filled-data"},
		},
	}
}

func (PlaceholderFilledData) CrossFile() {}
