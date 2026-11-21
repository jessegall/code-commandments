package engine

import (
	"strings"
)

func (m Match) DeclaresValueEquality() bool {
	if !m.Is(TypeDeclaration) {
		return false
	}
	for _, method := range m.Methods() {
		if strings.EqualFold(method.Name(), "equals") && len(method.Parameters()) == 1 && method.returnsBoolean() {
			return true
		}
	}
	return false
}

