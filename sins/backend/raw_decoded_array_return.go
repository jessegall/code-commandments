package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RawDecodedArrayReturn is the raw-decoded-array-return sin.
type RawDecodedArrayReturn struct{}

func init() { sins.Register(catalog.Backend, RawDecodedArrayReturn{}) }

// Definition is what the sin states about itself.
func (RawDecodedArrayReturn) Definition() sins.Definition {
	return sins.Definition{
		Name:        "raw-decoded-array-return",
		Skill:       skills.ValueObjects{},
		Description: "Returning a raw decoded boundary array (`json_decode(...)`) untyped",
		Rule:        "Return a typed object from a decoded boundary; never hand back a raw `json_decode(...)` array.",
		Suggestion:  "Decode into a Spatie `Data` object: `X::from(json_decode(...))`.",
	}
}
