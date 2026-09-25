package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// RawDecodedReturn is `return json.loads(…)` — decoded text from outside handed on as bare dicts and lists, its shape known to no type.
type RawDecodedReturn struct{}

func init() {
	sins.Register(catalog.Python, RawDecodedReturn{})
}

// Definition is what the sin states about itself.
func (RawDecodedReturn) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-raw-decoded-return",
		Skill:       skills.ValueObjects{},
		Description: "`return json.loads(…)` — decoded text from outside handed on as bare dicts and lists, its shape known to no type",
		Rule:        "Parse decoded data into a typed value at the boundary; never hand back a raw `json.loads(...)` result.",
		Suggestion:  "Build the dataclass (or a TypedDict-typed value) from the decoded data where it arrives — `Settings.from_json(json.loads(text))`.",
	}
}
