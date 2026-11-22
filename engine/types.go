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

// Types is the type system of the node's language, read from the declarations the codebase holds: the same for every
// language.
func (m Match) Types() (TypeSystem, bool) {
	if m.file == nil {
		return nil, false
	}
	return TypesFromDeclarations(m.file.codebase.Of(m.file.Language())), true
}

func Supertypes(types TypeSystem, symbol string) []string {
	above := append(types.Ancestors(symbol), types.Contracts(symbol)...)
	using := append([]string{symbol}, types.Ancestors(symbol)...)
	for len(using) > 0 {
		owner := using[0]
		using = using[1:]
		declared, found := types.Declaration(owner)
		if !found {
			continue
		}
		for _, trait := range declared.Traits() {
			if used := trait.Refers(); used != "" && !slices.Contains(above, used) {
				above = append(above, used)
				using = append(using, used)
			}
		}
	}

	return above
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
