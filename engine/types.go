package engine

import "slices"

// TypeSystem is what a language knows of its program's types: each declaration by its symbol, the classes each extends and the contracts each honours.
type TypeSystem interface {
	Declaration(symbol string) (Match, bool)
	Declarations() map[string]Match
	Ancestors(symbol string) []string
	Contracts(symbol string) []string
}

// DeclarationTypes reads the type relationships supplied by the codebase's declarations.
type DeclarationTypes map[string]Match

func TypesFromDeclarations(codebase *Codebase) TypeSystem {
	return Analysis(codebase, "declaration-types", func(codebase *Codebase) TypeSystem {
		types := DeclarationTypes{}
		for _, declaration := range codebase.Reporting().WhereTypeDeclaration().Get() {
			if symbol := declaration.Identity(); symbol != "" {
				types[symbol] = declaration
			}
		}

		return types
	})
}

func (types DeclarationTypes) Declaration(symbol string) (Match, bool) {
	declaration, known := types[symbol]

	return declaration, known
}

func (types DeclarationTypes) Declarations() map[string]Match {
	return types
}

func (types DeclarationTypes) Ancestors(symbol string) []string {
	return types[symbol].Lineage()
}

func (types DeclarationTypes) Contracts(symbol string) []string {
	return types[symbol].Contracts()
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

func TypeLacksMember(types TypeSystem, symbol string, providesMember func(string) bool) bool {
	if symbol == "" {
		return false
	}
	owners := append([]string{symbol}, Supertypes(types, symbol)...)
	for _, owner := range owners {
		if declaration, known := types.Declaration(owner); !known || declaration.IsInDependency() || providesMember(owner) {
			return false
		}
	}

	return true
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

func (m Match) PublishedContracts(registrars []string) []string {
	read := m.grammar().PublishedContracts
	if read == nil {
		return nil
	}

	return read(m, registrars)
}
