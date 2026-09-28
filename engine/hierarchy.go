package engine

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Extends are the nodes naming the types the type declaration extends directly, in source order.
func (m Match) Extends() []Match {
	return m.listed(func(read Lists) func(Match) []Match { return read.Extends })
}

// Implements are the nodes naming the contracts the type declaration honours directly, in source order.
func (m Match) Implements() []Match {
	return m.listed(func(read Lists) func(Match) []Match { return read.Implements })
}

// Annotations are the nodes naming each attribute or decorator the declaration carries, in source order.
func (m Match) Annotations() []Match {
	return m.listed(func(read Lists) func(Match) []Match { return read.Annotations })
}

// TypeKind is what kind of type the type declaration declares, one of TypeKinds; empty for any other node.
func (m Match) TypeKind() string {
	if read := m.lists().TypeKind; read != nil && m.Is(TypeDeclaration) {
		return read(m)
	}

	return ""
}

// ReturnType is the node the function's return type is written as; no node when none is written.
func (m Match) ReturnType() Match {
	if read := m.lists().ReturnType; read != nil && m.Is(Function) {
		return read(m)
	}

	return Match{}
}

// ParameterType is the node the parameter's type is written as; no node when none is written.
func (m Match) ParameterType() Match {
	if read := m.lists().ParameterType; read != nil && m.Is(Parameter) {
		return read(m)
	}

	return Match{}
}

// IsAnnotated says whether an attribute or decorator the declaration carries names the type; in C#, where
// `[Serializable]` is SerializableAttribute, a name without its Attribute suffix names it too.
func (m Match) IsAnnotated(want string) bool {
	for _, annotation := range m.Annotations() {
		if annotation.Names(want) || m.file.Language() == contract.CSharp && annotation.Names(want+"Attribute") {
			return true
		}
	}

	return false
}

// Names says whether the node names the type: by the symbol it resolves to when want is qualified, and
// otherwise by that symbol's last part, or, when it resolves to none, by the last part of what it says.
func (m Match) Names(want string) bool {
	return NamesType(m.named(), want)
}

// named is the type the node names: the symbol it resolves to, else its name, else what it says.
func (m Match) named() string {
	if refers := m.Refers(); refers != "" {
		return refers
	}

	if name := m.Name(); name != "" {
		return name
	}

	return strings.TrimSpace(m.Written())
}

// NamesType says whether the symbol is the type want names: the whole symbol when want is qualified, its last
// part when want is bare. A leading `\` and C#'s global:: are no part of either.
func NamesType(symbol, want string) bool {
	symbol, want = bareSymbol(symbol), bareSymbol(want)
	if symbol == "" || want == "" {
		return false
	}

	if strings.ContainsAny(want, `\.`) {
		return symbol == want
	}

	return lastPart(symbol) == want
}

// bareSymbol is the symbol less a leading `\` or global::, and a TypeScript symbol less the file it is in.
func bareSymbol(symbol string) string {
	if _, name, inFile := strings.Cut(symbol, "#"); inFile {
		symbol = name
	}

	return strings.TrimPrefix(strings.TrimPrefix(symbol, "global::"), `\`)
}

// lastPart is the symbol's last segment, after its last `\` or `.`.
func lastPart(symbol string) string {
	return symbol[strings.LastIndexAny(symbol, `\.`)+1:]
}

// Lineage is every type the type declaration extends, nearest first: through the declarations the codebase
// holds and the ones its language's program names outside the scan. A type neither says anything about ends
// its line, and a type met twice is followed once.
func (m Match) Lineage() []string {
	return m.climb(named(m.Extends()), m.extendsOf)
}

// Contracts is every contract the type declaration honours: its own and its lineage's, and the contracts those
// extend. In a language that declares no contracts apart, Python, a contract is a type it inherits from, so its
// contracts are its lineage.
func (m Match) Contracts() []string {
	if m.lists().Implements == nil {
		return m.Lineage()
	}

	direct := named(m.Implements())
	for _, ancestor := range m.Lineage() {
		direct = append(direct, m.implementsOf(ancestor)...)
	}

	return m.climb(direct, func(contract string) []string {
		return append(m.extendsOf(contract), m.implementsOf(contract)...)
	})
}

// climb follows each symbol to the ones step leads it to, breadth first, each once.
func (m Match) climb(start []string, step func(string) []string) []string {
	var reached []string
	for queue := start; len(queue) > 0; queue = queue[1:] {
		if slices.Contains(reached, queue[0]) {
			continue
		}

		reached = append(reached, queue[0])
		queue = append(queue, step(queue[0])...)
	}

	return reached
}

// extendsOf are the types the symbol's declaration extends, in the scan or outside it.
func (m Match) extendsOf(symbol string) []string {
	return m.supertypesOf(symbol, Match.Extends, func(outside contract.OutsideSymbol) []string { return outside.Extends })
}

// implementsOf are the contracts the symbol's declaration honours directly, in the scan or outside it.
func (m Match) implementsOf(symbol string) []string {
	return m.supertypesOf(symbol, Match.Implements, func(outside contract.OutsideSymbol) []string { return outside.Implements })
}

func (m Match) supertypesOf(symbol string, inScan func(Match) []Match, outside func(contract.OutsideSymbol) []string) []string {
	if m.file == nil {
		return nil
	}

	if declarations := m.file.Codebase().Declarations(symbol); len(declarations) > 0 {
		var supertypes []string
		for _, declaration := range declarations {
			supertypes = append(supertypes, named(inScan(declaration))...)
		}

		return supertypes
	}

	if declared, found := outsideSymbols(m.file.Codebase(), m.file.Language())[symbol]; found {
		return outside(declared)
	}

	return nil
}

// OutsideKind is the kind of type the symbol declares outside the scan, as its language's program names it;
// empty when the program names no such symbol.
func (m Match) OutsideKind(symbol string) string {
	if m.file == nil {
		return ""
	}

	return outsideSymbols(m.file.Codebase(), m.file.Language())[symbol].Kind
}

// outsideSymbols are the declarations the language's program names outside the scan, by symbol.
func outsideSymbols(c *Codebase, language contract.Language) map[string]contract.OutsideSymbol {
	return Analysis(c, "outside-symbols:"+string(language), func(c *Codebase) map[string]contract.OutsideSymbol {
		index := map[string]contract.OutsideSymbol{}
		if program, wrote := c.Program(language); wrote {
			for _, symbol := range program.Symbols {
				index[symbol.Symbol] = symbol
			}
		}

		return index
	})
}

// named are the types the nodes name.
func named(nodes []Match) []string {
	names := make([]string, 0, len(nodes))
	for _, node := range nodes {
		names = append(names, node.named())
	}

	return names
}
