package engine

import (
	"regexp"
)

// Enums stores declared enum values by the symbol that owns them.
type Enums map[string][]string

var numericText = regexp.MustCompile(`^[ \t\n\r\v\f]*[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?[ \t\n\r\v\f]*$`)

func (m Match) EnumChainOwner() string {
	return ""
}

func (m Match) EnumMembership() []string {
	return nil
}

func (m Match) EnumArms() []string {
	return nil
}
