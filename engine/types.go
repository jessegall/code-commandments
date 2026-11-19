package engine

import "slices"

// TypeSystem is what a language knows of its program's types: each declaration by its symbol, the classes each extends and the contracts each honours.
type TypeSystem interface {
	Declaration(symbol string) (Match, bool)
	Declarations() map[string]Match
	Ancestors(symbol string) []string
	Contracts(symbol string) []string
}

func (m Match) Types() (TypeSystem, bool) {
	read := m.grammar().Types
	if read == nil || m.file == nil {
		return nil, false
	}

	return read(m.file.codebase), true
}

func Supertypes(types TypeSystem, symbol string) []string {
	return append(types.Ancestors(symbol), types.Contracts(symbol)...)
}

func IsA(types TypeSystem, symbol, base string) bool {
	return symbol != "" && (symbol == base || slices.Contains(Supertypes(types, symbol), base))
}

func Implementers(types TypeSystem, contract string) []Match {
	var honouring []Match
	for name, declaration := range types.Declarations() {
		if name != contract && slices.Contains(types.Contracts(name), contract) {
			honouring = append(honouring, declaration)
		}
	}

	return honouring
}
