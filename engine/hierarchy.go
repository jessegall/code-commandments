package engine

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Extends are the nodes naming the types the type declaration extends directly, in source order.
func (m Match) Extends() []Match {
	return m.listed(m.grammar().Extends)
}

// Implements are the nodes naming the contracts the type declaration honours directly, in source order.
func (m Match) Implements() []Match {
	return m.listed(m.grammar().Implements)
}

// Annotations are the nodes naming each attribute or decorator the declaration carries, in source order.
func (m Match) Annotations() []Match {
	return m.listed(m.grammar().Annotations)
}

// TypeKind is what kind of type the type declaration declares, one of TypeKinds; empty for any other node.
func (m Match) TypeKind() string {
	if read := m.grammar().TypeKind; read != nil && m.Is(TypeDeclaration) {
		return read(m)
	}

	return ""
}

// ReturnType is the node the function's return type is written as; no node when none is written.
func (m Match) ReturnType() Match {
	if read := m.grammar().ReturnType; read != nil && m.Is(Function) {
		return read(m)
	}

	return Match{}
}

// ParameterType is the node the parameter's type is written as; no node when none is written.
func (m Match) ParameterType() Match {
	if read := m.grammar().ParameterType; read != nil && m.Is(Parameter) {
		return read(m)
	}

	return Match{}
}

// IsAnnotated says whether an attribute or decorator the declaration carries names the type, by any name its
// language lets one be written by: `[Serializable]` is SerializableAttribute in C#.
func (m Match) IsAnnotated(want string) bool {
	names := []string{want}
	if read := m.grammar().AnnotationNames; read != nil {
		names = read(want)
	}

	for _, annotation := range m.Annotations() {
		if slices.ContainsFunc(names, annotation.Names) {
			return true
		}
	}

	return false
}

// Names says whether the node names the type: by the symbol it resolves to when want is qualified, and
// otherwise by that symbol's last part, or, when it resolves to none, by the last part of what it says.
func (m Match) Names(want string) bool {
	return NamesType(m.Named(), want)
}

// Named is the type the node names: the symbol it resolves to, or the class its compiler says calling it builds,
// else its name, else what it says.
func (m Match) Named() string {
	if m.node == nil {
		return ""
	}

	if refers := m.Refers(); refers != "" {
		return refers
	}

	if resolved := m.node.Resolved; resolved != nil && resolved.Constructs != "" {
		return resolved.Constructs
	}

	if name := m.Name(); name != "" {
		return name
	}

	return strings.TrimSpace(m.Written())
}

// NamesType says whether the symbol is the type want names: the whole symbol when want is qualified, its last
// part when want is bare. A leading `\` and C#'s global:: are no part of either.
func NamesType(symbol, want string) bool {
	symbol, want = BareSymbol(symbol), BareSymbol(want)
	if symbol == "" || want == "" {
		return false
	}

	if strings.ContainsAny(want, `\.`) {
		return symbol == want
	}

	return LastPart(symbol) == want
}

// BareSymbol is the symbol less a leading `\` or global::, a TypeScript symbol less the file it is in, and a
// generic type less its type arguments: List<T> is List.
func BareSymbol(symbol string) string {
	if _, name, inFile := strings.Cut(symbol, "#"); inFile {
		symbol = name
	}

	return strings.TrimPrefix(strings.TrimPrefix(withoutTypeArguments(symbol), "global::"), `\`)
}

// withoutTypeArguments is the symbol less every type argument list and generic arity it carries, wherever it
// stands: N.Outer<T>.Inner is N.Outer.Inner, System.Action`1 is System.Action.
func withoutTypeArguments(symbol string) string {
	var kept strings.Builder
	depth, arity := 0, false

	for _, character := range symbol {
		switch {
		case character == '<':
			depth++
		case character == '>' && depth > 0:
			depth--
		case character == '`' && depth == 0:
			arity = true
		case arity && character >= '0' && character <= '9':
		case depth == 0:
			arity = false
			kept.WriteRune(character)
		}
	}

	return kept.String()
}

// LastPart is the symbol's last segment, after its last `\` or `.`.
func LastPart(symbol string) string {
	return symbol[strings.LastIndexAny(symbol, `\.`)+1:]
}

// Class is the class the node is about: the one a type declaration declares, else the one it names, constructs or
// holds a value of, as its language tells; empty when it is about none.
func (m Match) Class() string {
	if m.node == nil {
		return ""
	}
	if m.Is(TypeDeclaration) {
		return m.Identity()
	}
	if read := m.grammar().ClassOf; read != nil {
		return read(m)
	}

	return m.Refers()
}

// Traits are the nodes naming the traits the type declaration uses directly, in source order.
func (m Match) Traits() []Match {
	return m.listed(m.grammar().Traits)
}

// Lineage is every type the node's class extends, nearest first: a type declaration's own, or the class the node
// names, constructs or holds a value of. It reads through the declarations the codebase holds and the ones its
// language's program names outside the scan. A type neither says anything about ends its line, and a type met
// twice is followed once.
func (m Match) Lineage() []string {
	if !m.Is(TypeDeclaration) {
		return m.climb(m.extendsOf(m.Class()), m.extendsOf)
	}

	return m.climb(named(m.Extends()), m.extendsOf)
}

// Parents are the types the node's class extends directly.
func (m Match) Parents() []string {
	if !m.Is(TypeDeclaration) {
		return m.extendsOf(m.Class())
	}

	return named(m.Extends())
}

// Contracts is every contract the node's class honours: its own and its lineage's, and the contracts those
// extend.
func (m Match) Contracts() []string {
	direct := named(m.Implements())
	if !m.Is(TypeDeclaration) {
		direct = m.implementsOf(m.Class())
	}
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

// UsedTraits is every trait the node's class uses: its own, its lineage's, and the traits those use.
func (m Match) UsedTraits() []string {
	class := m.Class()
	if class == "" {
		return nil
	}
	direct := m.usesOf(class)
	for _, ancestor := range m.Lineage() {
		direct = append(direct, m.usesOf(ancestor)...)
	}

	return m.climb(direct, m.usesOf)
}

// usesOf are the traits the symbol's declaration uses directly, in the scan or outside it.
func (m Match) usesOf(symbol string) []string {
	return m.supertypesOf(symbol, Match.Traits, func(outside contract.OutsideSymbol) []string { return outside.Uses })
}

// implementsOf are the contracts the symbol's declaration honours directly, in the scan or outside it.
func (m Match) implementsOf(symbol string) []string {
	return m.supertypesOf(symbol, Match.Implements, func(outside contract.OutsideSymbol) []string { return outside.Implements })
}

func (m Match) supertypesOf(symbol string, inScan func(Match) []Match, outside func(contract.OutsideSymbol) []string) []string {
	if m.file == nil || symbol == "" {
		return nil
	}

	var supertypes []string
	declared := false
	for _, declaration := range m.file.Codebase().Declarations(symbol) {
		if declaration.file.Language() == m.file.Language() {
			declared = true
			supertypes = append(supertypes, named(inScan(declaration))...)
		}
	}

	if declared {
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
		names = append(names, node.Named())
	}

	return names
}
