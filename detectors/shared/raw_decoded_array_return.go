package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// RawDecodedArrayReturn finds a function exposing an unchecked decoded value at its return boundary.
type RawDecodedArrayReturn struct{}

func (RawDecodedArrayReturn) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.ReturnsRawDecodedValue).Get()
}

func (RawDecodedArrayReturn) Sin() Text {
	return Text{
		Name:        "raw-decoded-array-return",
		Skill:       skills.Absence{},
		Description: "A function returns decoded data before checking and giving it a type",
		Rule:        "Validate decoded data at the boundary and return the type the caller can rely on.",
		Suggestion:  "Parse the decoded payload into a named result with checked fields.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-raw-decoded-return", Detector: "RawDecodedReturnDetector"},
		},
	}
}
