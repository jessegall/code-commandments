package php

import (
	"github.com/jessegall/code-commandments/engine"
)

// EnumComparison holds the operands of a strict comparison with a case of the subject's enum.
type EnumComparison struct {
	Subject engine.Match
	Case    engine.Match
	Enum    string
	Negated bool
}
