package engine

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

func (m Match) Extends() []Match {
	return m.listed(m.grammar().Extends)
}

func (m Match) Implements() []Match {
	return m.listed(m.grammar().Implements)
}

func (m Match) Annotations() []Match {
	return m.listed(m.grammar().Annotations)
}

func (m Match) TypeKind() string {
	if read := m.grammar().TypeKind; read != nil && m.Is(TypeDeclaration) {
		return read(m)
	}

	return ""
}

func (m Match) ReturnType() Match {
	if read := m.grammar().ReturnType; read != nil && m.Is(Function) {
		return read(m)
	}

	return Match{}
}

func (m Match) ParameterType() Match {
	if read := m.grammar().ParameterType; read != nil && m.Is(Parameter) {
		return read(m)
	}

	return Match{}
}

func (m Match) IsAnnotated(want string) bool {
	return m.Annotated(want).Exists()
}

func (m Match) Annotated(want string) Match {
	names := []string{want}
	if read := m.grammar().AnnotationNames; read != nil {
		names = read(want)
	}

	for _, annotation := range m.Annotations() {
		if annotation.writtenBy(names) {
			return annotation
		}
	}

	return Match{}
}

func (m Match) writtenBy(names []string) bool {
	if slices.ContainsFunc(names, m.Names) {
		return true
	}

	named := m.Child("name")

	return named.Exists() && slices.ContainsFunc(names, named.Names)
}

func (m Match) Names(want string) bool {
	return NamesType(m.Named(), want)
}

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

func BareSymbol(symbol string) string {
	if _, name, inFile := strings.Cut(symbol, "#"); inFile {
		symbol = name
	}

	return strings.TrimPrefix(strings.TrimPrefix(withoutTypeArguments(symbol), "global::"), `\`)
}

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

func LastPart(symbol string) string {
	return symbol[strings.LastIndexAny(symbol, `\.`)+1:]
}

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

func (m Match) TypeName() string {
	if m.node == nil {
		return ""
	}

	if read := m.grammar().TypeOf; read != nil {
		return read(m)
	}

	return m.Class()
}

func (m Match) Traits() []Match {
	return m.listed(m.grammar().Traits)
}

func (m Match) Lineage() []string {
	if !m.Is(TypeDeclaration) {
		return m.climb(m.extendsOf(m.Class()), m.extendsOf)
	}

	return m.climb(named(m.Extends()), m.extendsOf)
}

func (m Match) Parents() []string {
	if !m.Is(TypeDeclaration) {
		return m.extendsOf(m.Class())
	}

	return named(m.Extends())
}

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

func (m Match) extendsOf(symbol string) []string {
	return m.supertypesOf(symbol, Match.Extends, func(outside contract.OutsideSymbol) []string { return outside.Extends })
}

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

func (m Match) usesOf(symbol string) []string {
	return m.supertypesOf(symbol, Match.Traits, func(outside contract.OutsideSymbol) []string { return outside.Uses })
}

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
		if declaration.file.Language() != m.file.Language() {
			continue
		}
		declared = true
		supertypes = append(supertypes, named(inScan(declaration))...)
	}

	if declared {
		return supertypes
	}

	if declared, found := outsideSymbols(m.file.Codebase(), m.file.Language())[symbol]; found {
		return outside(declared)
	}

	return nil
}

func (m Match) OutsideKind(symbol string) string {
	if m.file == nil {
		return ""
	}

	return outsideSymbols(m.file.Codebase(), m.file.Language())[symbol].Kind
}

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

func named(nodes []Match) []string {
	names := make([]string, 0, len(nodes))
	for _, node := range nodes {
		names = append(names, node.Named())
	}

	return names
}
