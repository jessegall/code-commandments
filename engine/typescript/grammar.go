package typescript

import (
	"slices"
	"strings"
)

// A type written as a string is read by the grammar the PHP tool's own TypeScript parser reads types with, and
// printed as its type nodes print: the types an extracted component declares are built from strings, and must come
// out byte for byte as the PHP tool writes them.

// lexeme is one token of TypeScript source: an identifier, a string, a number, or one punctuation byte.
type lexeme struct {
	kind  string
	value string
	start int
	end   int
}

const (
	identifierToken = "id"
	punctToken      = "punct"
	stringToken     = "string"
	numberToken     = "num"
	noToken         = "none"
)

func (l lexeme) is(kind, value string) bool {
	return l.kind == kind && (value == "" || l.value == value)
}

func (l lexeme) isPunct(value string) bool      { return l.is(punctToken, value) }
func (l lexeme) isIdentifier(value string) bool { return l.is(identifierToken, value) }
func (l lexeme) isNone() bool                   { return l.kind == noToken }

func (l lexeme) isTypeOpener() bool {
	return l.kind == punctToken && slices.Contains([]string{"(", "[", "{", "<"}, l.value)
}

func (l lexeme) isTypeCloser() bool {
	return l.kind == punctToken && slices.Contains([]string{")", "]", "}", ">"}, l.value)
}

func (l lexeme) isGroupOpener() bool {
	return l.kind == punctToken && slices.Contains([]string{"(", "[", "{"}, l.value)
}

func (l lexeme) isGroupCloser() bool {
	return l.kind == punctToken && slices.Contains([]string{")", "]", "}"}, l.value)
}

// lex cuts source into tokens as the PHP tool's lexer does: whitespace and comments dropped, strings and names and
// numbers whole, anything else one byte at a time.
func lex(source string) []lexeme {
	var tokens []lexeme
	for at := 0; at < len(source); {
		c := source[at]
		end := at + 1
		kind := punctToken
		switch {
		case isCtypeSpace(c):
			at++

			continue
		case c == '"' || c == '\'' || c == '`':
			kind, end = stringToken, skipString(source, at, c)
		case isAlpha(c) || c == '_' || c == '$':
			kind = identifierToken
			for end < len(source) && (isAlnum(source[end]) || source[end] == '_' || source[end] == '$') {
				end++
			}
		case c >= '0' && c <= '9':
			kind = numberToken
			for end < len(source) && (isAlnum(source[end]) || source[end] == '.' || source[end] == '_') {
				end++
			}
		case c == '/' && at+1 < len(source) && source[at+1] == '/':
			for at < len(source) && source[at] != '\n' {
				at++
			}

			continue
		case c == '/' && at+1 < len(source) && source[at+1] == '*':
			at += 2
			for at < len(source) && !(source[at] == '*' && at+1 < len(source) && source[at+1] == '/') {
				at++
			}
			at = min(at+2, len(source))

			continue
		}
		tokens = append(tokens, lexeme{kind: kind, value: source[at:end], start: at, end: end})
		at = end
	}

	return tokens
}

func skipString(source string, at int, quote byte) int {
	for at++; at < len(source); at++ {
		if source[at] == '\\' {
			at++
		} else if source[at] == quote {
			return at + 1
		}
	}

	return len(source)
}

func isCtypeSpace(c byte) bool {
	return c == ' ' || c == '\t' || c == '\n' || c == '\v' || c == '\f' || c == '\r'
}

func isAlpha(c byte) bool { return c >= 'a' && c <= 'z' || c >= 'A' && c <= 'Z' }
func isAlnum(c byte) bool { return isAlpha(c) || c >= '0' && c <= '9' }

// TypeNode is a type as the PHP tool's grammar reads it.
type TypeNode interface {
	Render() string
	References() []string
	UnwrapRef() TypeNode
	AdmitsAbsence() bool
	FieldsWith(declared func(name string) Fields) Fields
}

// Fields is a type's members by name, each its type as printed, in the order it declares them.
type Fields struct {
	Names []string
	Types map[string]string
}

// Set records a member's type; a member named again keeps its place.
func (f *Fields) Set(name, typed string) {
	if f.Types == nil {
		f.Types = map[string]string{}
	}
	if _, seen := f.Types[name]; !seen {
		f.Names = append(f.Names, name)
	}
	f.Types[name] = typed
}

// Get is a member's type.
func (f Fields) Get(name string) (string, bool) {
	typed, ok := f.Types[name]

	return typed, ok
}

// plain is what every type node answers unless it says otherwise.
type plain struct{}

func (plain) References() []string                  { return nil }
func (plain) AdmitsAbsence() bool                   { return false }
func (plain) FieldsWith(func(string) Fields) Fields { return Fields{} }

// KeywordType is a keyword: string, number, void, null.
type KeywordType struct {
	plain
	Name string
}

func (t KeywordType) Render() string      { return t.Name }
func (t KeywordType) UnwrapRef() TypeNode { return t }
func (t KeywordType) AdmitsAbsence() bool { return t.Name == "null" || t.Name == "undefined" }

// LiteralType is a literal as written: 'draft', 3, true.
type LiteralType struct {
	plain
	Raw string
}

func (t LiteralType) Render() string      { return t.Raw }
func (t LiteralType) UnwrapRef() TypeNode { return t }

// VerbatimType is a type the grammar does not model, kept as written.
type VerbatimType struct {
	plain
	Raw string
}

func (t VerbatimType) Render() string      { return t.Raw }
func (t VerbatimType) UnwrapRef() TypeNode { return t }

// TypeofType is `typeof name`.
type TypeofType struct {
	plain
	Target string
}

func (t TypeofType) Render() string      { return "typeof " + t.Target }
func (t TypeofType) UnwrapRef() TypeNode { return t }

// refWrappers are the reactive wrappers a template reads through to their value.
var refWrappers = []string{"Ref", "ComputedRef", "ShallowRef", "WritableComputedRef", "MaybeRef", "MaybeRefOrGetter"}

// NamedType is a named type and its arguments: Order, Ref<number>.
type NamedType struct {
	plain
	Name      string
	Arguments []TypeNode
}

func (t NamedType) Render() string {
	if len(t.Arguments) == 0 {
		return t.Name
	}

	return t.Name + "<" + renderAll(t.Arguments, ", ") + ">"
}

func (t NamedType) References() []string {
	names := []string{t.Name}
	for _, argument := range t.Arguments {
		names = append(names, argument.References()...)
	}

	return names
}

func (t NamedType) UnwrapRef() TypeNode {
	if slices.Contains(refWrappers, t.Name) && len(t.Arguments) > 0 {
		return t.Arguments[0]
	}

	return t
}

func (t NamedType) FieldsWith(declared func(string) Fields) Fields { return declared(t.Name) }

// ArrayType is `T[]`.
type ArrayType struct {
	plain
	Element TypeNode
}

func (t ArrayType) Render() string       { return t.Element.Render() + "[]" }
func (t ArrayType) References() []string { return t.Element.References() }
func (t ArrayType) UnwrapRef() TypeNode  { return t }

// IndexedAccessType is `T[K]`.
type IndexedAccessType struct {
	plain
	Object, Index TypeNode
}

func (t IndexedAccessType) Render() string { return t.Object.Render() + "[" + t.Index.Render() + "]" }
func (t IndexedAccessType) References() []string {
	return append(t.Object.References(), t.Index.References()...)
}
func (t IndexedAccessType) UnwrapRef() TypeNode { return t }

// ParenType is `(T)`.
type ParenType struct {
	plain
	Inner TypeNode
}

func (t ParenType) Render() string       { return "(" + t.Inner.Render() + ")" }
func (t ParenType) References() []string { return t.Inner.References() }
func (t ParenType) UnwrapRef() TypeNode  { return t }

// TupleType is `[A, B]`.
type TupleType struct {
	plain
	Elements []TypeNode
}

func (t TupleType) Render() string       { return "[" + renderAll(t.Elements, ", ") + "]" }
func (t TupleType) References() []string { return referencesOf(t.Elements) }
func (t TupleType) UnwrapRef() TypeNode  { return t }

// CompositeType is a union or an intersection.
type CompositeType struct {
	plain
	Operator string
	Members  []TypeNode
}

func (t CompositeType) Render() string       { return renderAll(t.Members, " "+t.Operator+" ") }
func (t CompositeType) References() []string { return referencesOf(t.Members) }

func (t CompositeType) AdmitsAbsence() bool {
	if t.Operator != "|" {
		return false
	}

	return slices.ContainsFunc(t.Members, TypeNode.AdmitsAbsence)
}

func (t CompositeType) UnwrapRef() TypeNode {
	var flattened []TypeNode
	for _, member := range t.Members {
		unwrapped := member.UnwrapRef()
		if composite, same := unwrapped.(CompositeType); same && composite.Operator == t.Operator {
			flattened = append(flattened, composite.Members...)
		} else {
			flattened = append(flattened, unwrapped)
		}
	}
	seen := map[string]bool{}
	var unique []TypeNode
	for _, member := range flattened {
		if rendered := member.Render(); !seen[rendered] {
			seen[rendered] = true
			unique = append(unique, member)
		}
	}
	if len(unique) == 1 {
		return unique[0]
	}

	return CompositeType{Operator: t.Operator, Members: unique}
}

// Param is a parameter of a function type or a method.
type Param struct {
	Name     string
	Type     TypeNode
	Optional bool
	Rest     bool
}

// Render is the parameter as written: `...name?: T`.
func (p Param) Render() string {
	prefix, suffix := "", ""
	if p.Rest {
		prefix = "..."
	}
	if p.Optional {
		suffix = "?"
	}
	if p.Type == nil {
		return prefix + p.Name + suffix
	}

	return prefix + p.Name + suffix + ": " + p.Type.Render()
}

func (p Param) references() []string {
	if p.Type == nil {
		return nil
	}

	return p.Type.References()
}

// FunctionType is `(params) => R`.
type FunctionType struct {
	plain
	Params  []Param
	Returns TypeNode
}

func (t FunctionType) Render() string {
	params := make([]string, 0, len(t.Params))
	for _, param := range t.Params {
		params = append(params, param.Render())
	}

	return "(" + strings.Join(params, ", ") + ") => " + t.Returns.Render()
}

func (t FunctionType) References() []string {
	names := t.Returns.References()
	for _, param := range t.Params {
		names = append(names, param.references()...)
	}

	return names
}

func (t FunctionType) UnwrapRef() TypeNode { return t }

// Member is a member of an object type: a property or a method.
type Member struct {
	Name     string
	Optional bool
	Property TypeNode
	Params   []Param
	Returns  TypeNode
}

// Type is the member's type: its property type, or the function type of its method.
func (m Member) Type() TypeNode {
	if m.Property != nil {
		return m.Property
	}

	return FunctionType{Params: m.Params, Returns: m.Returns}
}

// Render is the member as an object type writes it.
func (m Member) Render() string {
	optional := ""
	if m.Optional {
		optional = "?"
	}
	if m.Property != nil {
		return m.Name + optional + ": " + m.Property.Render()
	}
	params := make([]string, 0, len(m.Params))
	for _, param := range m.Params {
		params = append(params, param.Render())
	}

	return m.Name + optional + "(" + strings.Join(params, ", ") + "): " + m.Returns.Render()
}

// ObjectType is `{ a: T; b(): U }`.
type ObjectType struct {
	plain
	Members []Member
}

func (t ObjectType) Render() string {
	if len(t.Members) == 0 {
		return "{}"
	}
	members := make([]string, 0, len(t.Members))
	for _, member := range t.Members {
		members = append(members, member.Render())
	}

	return "{ " + strings.Join(members, "; ") + " }"
}

func (t ObjectType) References() []string {
	var names []string
	for _, member := range t.Members {
		names = append(names, member.Type().References()...)
	}

	return names
}

func (t ObjectType) UnwrapRef() TypeNode { return t }

// Fields is each member's type, printed, by name.
func (t ObjectType) Fields() Fields {
	var fields Fields
	for _, member := range t.Members {
		fields.Set(member.Name, member.Type().Render())
	}

	return fields
}

func (t ObjectType) FieldsWith(func(string) Fields) Fields { return t.Fields() }

func renderAll(types []TypeNode, separator string) string {
	rendered := make([]string, 0, len(types))
	for _, typed := range types {
		rendered = append(rendered, typed.Render())
	}

	return strings.Join(rendered, separator)
}

func referencesOf(types []TypeNode) []string {
	var names []string
	for _, typed := range types {
		names = append(names, typed.References()...)
	}

	return names
}

// ParseType reads a type written as a string.
func ParseType(source string) TypeNode {
	return (&typeParser{source: source, tokens: lex(source)}).parseType()
}

// UnwrapRefText is a type written as a string with the reactive wrapper it reads through taken off, as printed; the
// text itself when nothing prints.
func UnwrapRefText(source string) string {
	if unwrapped := ParseType(source).UnwrapRef().Render(); unwrapped != "" {
		return unwrapped
	}

	return source
}

// unparsed is a region the grammar does not model; the nearest type position keeps it as written.
type unparsed struct{}

// typeParser reads types off tokens as the PHP tool's parser does.
type typeParser struct {
	source string
	tokens []lexeme
	pos    int
	depth  int
}

var keywordTypes = []string{"string", "number", "boolean", "void", "unknown", "never", "any", "null", "undefined", "object", "symbol", "bigint", "this"}

var verbatimLeads = []string{"keyof", "readonly", "infer", "unique", "new", "abstract", "asserts"}

var typeOperators = []string{"keyof", "typeof", "readonly", "infer", "in", "extends", "as", "is", "new", "unique", "abstract", "asserts"}

var modifierKeywords = []string{"public", "protected", "private", "static", "readonly", "abstract", "override", "declare", "async"}

const maxTypeDepth = 256

func (p *typeParser) at(offset int) lexeme {
	if at := p.pos + offset; at < len(p.tokens) {
		return p.tokens[at]
	}

	return lexeme{kind: noToken, start: len(p.source), end: len(p.source)}
}

func (p *typeParser) peek() lexeme { return p.at(0) }
func (p *typeParser) eof() bool    { return p.pos >= len(p.tokens) }

func (p *typeParser) advance() lexeme {
	if p.eof() {
		panic(unparsed{})
	}
	token := p.tokens[p.pos]
	p.pos++

	return token
}

func (p *typeParser) atPunct(value string) bool { return p.peek().isPunct(value) }
func (p *typeParser) atID(value string) bool    { return p.peek().isIdentifier(value) }

func (p *typeParser) advanceIfPunct(value string) bool {
	if p.atPunct(value) {
		p.advance()

		return true
	}

	return false
}

func (p *typeParser) expectPunct(value string) {
	if !p.advanceIfPunct(value) {
		panic(unparsed{})
	}
}

func (p *typeParser) inside(closer string) bool { return !p.atPunct(closer) && !p.eof() }

// speculate runs a parse and rewinds when it meets a region the grammar does not model.
func speculate[T any](p *typeParser, parse func() T) (result T, ok bool) {
	start := p.pos
	defer func() {
		if failure := recover(); failure != nil {
			if _, isUnparsed := failure.(unparsed); !isUnparsed {
				panic(failure)
			}
			p.pos = start
			ok = false
		}
	}()

	return parse(), true
}

func (p *typeParser) parseType() TypeNode {
	p.depth++
	defer func() { p.depth-- }()
	typed, ok := speculate(p, func() TypeNode {
		if p.depth > maxTypeDepth {
			panic(unparsed{})
		}
		typed := p.parseUnion()
		if p.atID("extends") {
			panic(unparsed{})
		}

		return typed
	})
	if ok {
		return typed
	}
	start := p.pos
	verbatim := p.captureTypeVerbatim()
	if p.pos == start && !p.eof() {
		verbatim = p.advance().value
	}

	return VerbatimType{Raw: verbatim}
}

func (p *typeParser) parseUnion() TypeNode {
	p.advanceIfPunct("|")
	members := []TypeNode{p.parseIntersection()}
	for p.atPunct("|") {
		p.advance()
		members = append(members, p.parseIntersection())
	}
	if len(members) == 1 {
		return members[0]
	}

	return CompositeType{Operator: "|", Members: members}
}

func (p *typeParser) parseIntersection() TypeNode {
	p.advanceIfPunct("&")
	members := []TypeNode{p.parsePostfix()}
	for p.atPunct("&") {
		p.advance()
		members = append(members, p.parsePostfix())
	}
	if len(members) == 1 {
		return members[0]
	}

	return CompositeType{Operator: "&", Members: members}
}

func (p *typeParser) parsePostfix() TypeNode {
	typed := p.parsePrimary()
	for p.atPunct("[") {
		p.advance()
		if p.atPunct("]") {
			p.advance()
			typed = ArrayType{Element: typed}

			continue
		}
		index := p.parseType()
		p.expectPunct("]")
		typed = IndexedAccessType{Object: typed, Index: index}
	}

	return typed
}

func (p *typeParser) parsePrimary() TypeNode {
	token := p.peek()
	switch {
	case token.isNone():
		panic(unparsed{})
	case token.isPunct("("):
		return p.parseParenOrFunction()
	case token.isPunct("{"):
		return ObjectType{Members: p.parseTypeMembers()}
	case token.isPunct("["):
		return p.parseTuple()
	case token.isPunct("-") && p.at(1).is(numberToken, ""):
		p.advance()

		return LiteralType{Raw: "-" + p.advance().value}
	case token.is(stringToken, "") || token.is(numberToken, ""):
		return LiteralType{Raw: p.advance().value}
	case token.isIdentifier("typeof"):
		p.advance()

		return TypeofType{Target: p.qualifiedName()}
	case token.isIdentifier(""):
		if slices.Contains(verbatimLeads, token.value) {
			panic(unparsed{})
		}

		return p.parseNamedOrKeyword()
	}
	panic(unparsed{})
}

func (p *typeParser) parseNamedOrKeyword() TypeNode {
	name := p.qualifiedName()
	switch {
	case p.atPunct("<"):
		return NamedType{Name: name, Arguments: p.parseTypeArguments()}
	case name == "true" || name == "false":
		return LiteralType{Raw: name}
	case slices.Contains(keywordTypes, name):
		return KeywordType{Name: name}
	}

	return NamedType{Name: name}
}

func (p *typeParser) parseParenOrFunction() TypeNode {
	function, ok := speculate(p, func() TypeNode {
		params := p.parseParams()
		if !p.atPunct("=") || !p.at(1).isPunct(">") {
			panic(unparsed{})
		}
		p.advance()
		p.advance()

		return FunctionType{Params: params, Returns: p.parseType()}
	})
	if ok {
		return function
	}
	p.expectPunct("(")
	inner := p.parseType()
	p.expectPunct(")")

	return ParenType{Inner: inner}
}

func (p *typeParser) parseTuple() TypeNode {
	p.advance()
	var elements []TypeNode
	for p.inside("]") {
		elements = append(elements, p.parseType())
		if p.atPunct(",") {
			p.advance()
		}
	}
	p.expectPunct("]")

	return TupleType{Elements: elements}
}

func (p *typeParser) parseTypeArguments() []TypeNode {
	p.advance()
	var arguments []TypeNode
	for !p.atPunct(">") && !p.eof() {
		arguments = append(arguments, p.parseType())
		if p.atPunct(",") {
			p.advance()
		}
	}
	p.expectPunct(">")

	return arguments
}

// parseTypeMembers reads an object type's members strictly: a member it does not model leaves the whole type verbatim.
func (p *typeParser) parseTypeMembers() []Member {
	p.expectPunct("{")
	var members []Member
	for p.inside("}") {
		named := p.peek().isIdentifier("") || p.peek().is(stringToken, "") || p.atID("readonly")
		if !named {
			panic(unparsed{})
		}
		members = append(members, p.parseTypeMember())
		p.advanceIfPunct(";")
		p.advanceIfPunct(",")
	}
	p.expectPunct("}")

	return members
}

func (p *typeParser) parseTypeMember() Member {
	if p.atReadonlyModifier() {
		p.advance()
	}
	name := p.advance().value
	optional := p.advanceIfPunct("?")
	if p.atPunct("(") {
		params := p.parseParams()
		var returns TypeNode = KeywordType{Name: "void"}
		if p.advanceIfPunct(":") {
			returns = p.parseType()
		}

		return Member{Name: name, Optional: optional, Params: params, Returns: returns}
	}
	if p.advanceIfPunct(":") {
		return Member{Name: name, Optional: optional, Property: p.parseType()}
	}
	panic(unparsed{})
}

func (p *typeParser) atReadonlyModifier() bool {
	if !p.atID("readonly") {
		return false
	}
	next := p.at(1)

	return !next.isNone() && !next.isPunct("?") && !next.isPunct(":") && !next.isPunct("(")
}

func (p *typeParser) parseParams() []Param {
	p.expectPunct("(")
	var params []Param
	for p.inside(")") {
		p.memberModifiers()
		rest := p.advanceIfThreeDots()
		if !p.peek().isIdentifier("") && !p.atPunct("{") && !p.atPunct("[") {
			panic(unparsed{})
		}
		var name string
		if p.atPunct("{") || p.atPunct("[") {
			name = p.parsePattern()
		} else {
			name = p.advance().value
		}
		optional := p.advanceIfPunct("?")
		var typed TypeNode
		if p.advanceIfPunct(":") {
			typed = p.parseType()
		}
		p.skipDefaultValue()
		params = append(params, Param{Name: name, Type: typed, Optional: optional, Rest: rest})
		if p.atPunct(",") {
			p.advance()
		}
	}
	p.expectPunct(")")

	return params
}

func (p *typeParser) memberModifiers() {
	for p.peek().isIdentifier("") && slices.Contains(modifierKeywords, p.peek().value) {
		if next := p.at(1); next.isPunct("(") || next.isPunct("=") || next.isPunct(":") {
			return
		}
		p.advance()
	}
}

func (p *typeParser) advanceIfThreeDots() bool {
	if !(p.atPunct(".") && p.at(1).isPunct(".") && p.at(2).isPunct(".")) {
		return false
	}
	p.advance()
	p.advance()
	p.advance()

	return true
}

// parsePattern reads a destructured parameter, printed: `{ a, b: c, ...rest }`, `[a, , b]`.
func (p *typeParser) parsePattern() string {
	if p.atPunct("{") {
		p.advance()
		var locals []string
		keys := map[string]string{}
		rest := ""
		for p.inside("}") {
			if p.advanceIfThreeDots() {
				rest = p.advance().value
			} else {
				key := p.advance().value
				local := key
				if p.advanceIfPunct(":") {
					local = p.advance().value
				}
				if _, seen := keys[local]; !seen {
					locals = append(locals, local)
				}
				keys[local] = key
				p.skipDefaultValue()
			}
			if p.atPunct(",") {
				p.advance()
			}
		}
		p.advanceIfPunct("}")
		var parts []string
		for _, local := range locals {
			if keys[local] == local {
				parts = append(parts, local)
			} else {
				parts = append(parts, keys[local]+": "+local)
			}
		}
		if rest != "" {
			parts = append(parts, "..."+rest)
		}

		return "{ " + strings.Join(parts, ", ") + " }"
	}
	p.advance()
	var elements []string
	for p.inside("]") {
		if p.atPunct(",") {
			elements = append(elements, "")
			p.advance()

			continue
		}
		p.advanceIfThreeDots()
		elements = append(elements, p.advance().value)
		p.skipDefaultValue()
		if p.atPunct(",") {
			p.advance()
		}
	}
	p.advanceIfPunct("]")

	return "[" + strings.Join(elements, ", ") + "]"
}

func (p *typeParser) skipDefaultValue() {
	if !p.atPunct("=") || p.at(1).isPunct(">") {
		return
	}
	p.advance()
	depth := 0
	for !p.eof() {
		token := p.peek()
		if depth == 0 && token.kind == punctToken && slices.Contains([]string{",", ")", "}", "]"}, token.value) {
			return
		}
		if token.isGroupOpener() {
			depth++
		} else if token.isGroupCloser() {
			depth--
		}
		p.advance()
	}
}

func (p *typeParser) qualifiedName() string {
	name := p.advance().value
	for p.atPunct(".") && p.at(1).isIdentifier("") {
		p.advance()
		name += "." + p.advance().value
	}

	return name
}

// captureTypeVerbatim reads a type the grammar does not model up to where it ends, as written.
func (p *typeParser) captureTypeVerbatim() string {
	depth := 0
	start := p.peek().start
	end := start
	var previous *lexeme
	for !p.eof() {
		token := p.peek()
		if depth > 0 && token.isPunct("=") && p.at(1).isPunct(">") {
			end = p.at(1).end
			p.advance()
			p.advance()

			continue
		}
		if depth == 0 {
			if token.kind == punctToken && slices.Contains([]string{",", ";", ")", "]", "}", ">"}, token.value) {
				break
			}
			if token.isPunct("=") {
				break
			}
			if token.isPunct("{") && previous != nil && completesType(*previous) {
				break
			}
		}
		if token.isTypeOpener() {
			depth++
		} else if token.isTypeCloser() {
			depth--
		}
		end = token.end
		current := token
		previous = &current
		p.advance()
	}

	return strings.Trim(p.source[start:end], " \t\n\r\x00\x0b")
}

func completesType(token lexeme) bool {
	if token.isIdentifier("") {
		return !slices.Contains(typeOperators, token.value)
	}

	return token.is(stringToken, "") || token.is(numberToken, "") || token.isPunct("]") || token.isPunct(">") || token.isPunct(")")
}
