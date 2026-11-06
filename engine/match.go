package engine

import (
	"fmt"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// Neutral is a language-neutral question a node answers, drawn from the contract's closed set.
type Neutral string

const (
	Function            Neutral = "function"
	TypeDeclaration     Neutral = "type-declaration"
	Parameter           Neutral = "parameter"
	Block               Neutral = "block"
	Branch              Neutral = "branch"
	Loop                Neutral = "loop"
	Return              Neutral = "return"
	Throw               Neutral = "throw"
	BailOut             Neutral = "bail-out"
	ExpressionStatement Neutral = "expression-statement"
	Call                Neutral = "call"
	Construction        Neutral = "construction"
	MemberAccess        Neutral = "member-access"
	NullSafe            Neutral = "null-safe"
	SelfReference       Neutral = "self-reference"
	Identifier          Neutral = "identifier"
	Assignment          Neutral = "assignment"
	Comparison          Neutral = "comparison"
	Conjunction         Neutral = "conjunction"
	Literal             Neutral = "literal"
	Import              Neutral = "import"
	Catch               Neutral = "catch"
)

var Neutrals = []Neutral{Function, TypeDeclaration, Parameter, Block, Branch, Loop, Return, Throw, BailOut, ExpressionStatement, Call, Construction, MemberAccess, NullSafe, SelfReference, Identifier, Assignment, Comparison, Conjunction, Literal, Import, Catch}

// Located is anything a finding can point at.
type Located interface {
	File() string
	Line() int
	Location() string
	Scope() string
}

// Match is one node of one file.
type Match struct {
	node *contract.Node
	file *File
}

var _ Located = Match{}

func (m Match) Exists() bool {
	return m.node != nil
}

func (m Match) Codebase() *Codebase {
	if m.file == nil {
		return nil
	}

	return m.file.codebase
}

func (m Match) Node() *contract.Node {
	return m.node
}

func (m Match) Source() *File {
	return m.file
}

func (m Match) File() string {
	if m.file == nil {
		return ""
	}

	return m.file.Path
}

func (m Match) Line() int {
	if m.node == nil {
		return 0
	}

	return m.node.Span.Line
}

func (m Match) Location() string {
	return fmt.Sprintf("%s:%d", m.File(), m.Line())
}

func (m Match) Scope() string {
	if m.file != nil {
		if namer, named := scopeNamers.Load(m.file.Language()); named {
			return namer.(func(Match) string)(m)
		}
	}

	declaration, function := m.EnclosingType(), m.EnclosingFunction()
	if m.Is(TypeDeclaration) {
		declaration = m
	}
	if m.Is(Function) && m.Name() != "" {
		function = m
	}

	scope := "(file)"
	if declaration.Exists() {
		scope = declaration.Identity()
	}
	if function.Exists() {
		return scope + "::" + function.Name()
	}

	return scope
}

func (m Match) Identity() string {
	if m.node == nil {
		return ""
	}
	if m.node.Symbol != "" {
		return m.node.Symbol
	}

	return m.node.Name
}

func (m Match) Field() string {
	if m.node == nil {
		return ""
	}

	return m.node.Field
}

func (m Match) Role() string {
	if m.node == nil {
		return ""
	}

	return m.node.Role
}

func (m Match) Kind() string {
	if m.node == nil {
		return ""
	}

	return m.node.Kind
}

var calleeFields = []string{"name", "func", "expression", "fun", "Expression"}

var namedFields = []string{"name", "Name"}

func (m Match) CalleeName() string {
	for _, field := range calleeFields {
		callee := m.Child(field)
		if !callee.Exists() {
			continue
		}

		name := calleeOf(callee)

		return name[strings.LastIndexAny(name, `\.`)+1:]
	}

	return ""
}

func (m Match) NameRead() string {
	return calleeOf(m)
}

func calleeOf(callee Match) string {
	if name := callee.Name(); name != "" {
		return name
	}

	for _, field := range namedFields {
		if named := callee.Child(field); named.Exists() {
			return named.Name()
		}
	}

	return ""
}

func (m Match) Receiver() Match {
	if read := m.grammar().Receiver; read != nil {
		return read(m)
	}

	return Match{}
}

func (m Match) Body() []Match {
	return m.listed(m.grammar().Body)
}

func (m Match) Otherwise() []Match {
	return m.listed(m.grammar().Otherwise)
}

func (m Match) Condition() Match {
	if read := m.grammar().Condition; read != nil && m.node != nil {
		return read(m)
	}

	return Match{}
}

func (m Match) Computes() bool {
	if m.Is(Assignment) || m.Is(Comparison) || m.Is(Conjunction) {
		return true
	}
	if read := m.grammar().Computes; read != nil && m.node != nil {
		return read(m)
	}

	return false
}

func (m Match) ElementName() string {
	if read := m.grammar().ElementName; read != nil && m.node != nil {
		return read(m)
	}

	return ""
}

func (m Match) ComparisonSubject() Match {
	if read := m.grammar().ComparisonSubject; read != nil && m.node != nil {
		return read(m)
	}

	return Match{}
}

func (m Match) Declares() bool {
	read := m.grammar().Declares

	return m.node != nil && read != nil && read(m)
}

func (m Match) Binds() bool {
	read := m.grammar().Binds

	return m.node != nil && read != nil && read(m)
}

func (m Match) Refers() string {
	if m.node == nil {
		return ""
	}

	return m.node.Refers
}

func (m Match) Resolves() string {
	if m.node == nil {
		return ""
	}

	return m.node.Resolves
}

func (m Match) HasFlag(flag string) bool {
	return m.node != nil && slices.Contains(m.node.Flags, flag)
}

func (m Match) Operator() string {
	if m.node == nil {
		return ""
	}

	return m.node.Operator
}

func (m Match) HasModifier(modifier string) bool {
	return m.node != nil && slices.Contains(m.node.Modifiers, modifier)
}

func (m Match) Name() string {
	if m.node == nil {
		return ""
	}

	return m.node.Name
}

func (m Match) WritesAttribute(name string) bool {
	read := m.grammar().WritesAttribute

	return m.node != nil && read != nil && read(m, name)
}

func (m Match) DeclaredName() string {
	if name := m.Name(); name != "" || m.node == nil {
		return name
	}
	if read := m.grammar().DeclaredName; read != nil {
		return read(m)
	}

	return ""
}

func (m Match) Written() string {
	if m.node == nil {
		return ""
	}
	source, err := m.file.Source()
	if err != nil {
		return ""
	}

	return string(source[m.node.Span.Start:m.node.Span.End])
}

func (m Match) IsConstant() bool {
	return m.node != nil && m.node.Constant
}

func (m Match) Text() (string, bool) {
	if m.node == nil || m.node.Value == nil {
		return "", false
	}

	return m.node.Value.Text()
}

func (m Match) Is(neutral Neutral) bool {
	return m.node != nil && m.node.Answers(string(neutral))
}

func (m Match) Parent() Match {
	if m.node == nil {
		return Match{}
	}
	parent, ok := m.node.Parent()
	if !ok {
		return Match{}
	}

	return Match{node: parent, file: m.file}
}

func (m Match) Children() []Match {
	if m.node == nil {
		return nil
	}
	children := make([]Match, 0, len(m.node.Children))
	for _, child := range m.node.Children {
		children = append(children, Match{node: child, file: m.file})
	}

	return children
}

func (m Match) Child(field string) Match {
	for _, child := range m.Children() {
		if child.node.Field == field {
			return child
		}
	}

	return Match{}
}

func (m Match) Descendants() []Match {
	var below []Match
	for _, child := range m.Children() {
		below = append(below, child)
		below = append(below, child.Descendants()...)
	}

	return below
}

func (m Match) ChildrenIn(field string) []Match {
	var filling []Match
	for _, child := range m.Children() {
		if child.node.Field == field {
			filling = append(filling, child)
		}
	}

	return filling
}

func (m Match) Root() Match {
	if m.file == nil {
		return Match{}
	}

	return m.file.Match(0)
}

func (m Match) Closest(neutral Neutral) Match {
	for ancestor := m.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		if ancestor.Is(neutral) {
			return ancestor
		}
	}

	return Match{}
}

func (m Match) EnclosingType() Match {
	return m.Closest(TypeDeclaration)
}

func (m Match) EnclosingFunction() Match {
	for function := m.Closest(Function); function.Exists(); function = function.Closest(Function) {
		if function.Name() != "" {
			return function
		}
	}

	return Match{}
}

func (m Match) IsWithinLoop() bool {
	for ancestor := m.Parent(); ancestor.Exists() && !ancestor.Is(Function); ancestor = ancestor.Parent() {
		if ancestor.Is(Loop) {
			return true
		}
	}

	return false
}

func (m Match) Comments() []contract.Comment {
	if m.node == nil {
		return nil
	}

	return m.file.Comments(m.node)
}

func (m Match) CommentsAbove() []contract.Comment {
	if m.node == nil {
		return nil
	}

	return m.file.CommentsAbove(m.node)
}

func (m Match) IsDocumented() bool {
	for _, comment := range m.Comments() {
		if comment.Kind == "doc" {
			return true
		}
	}

	return false
}

func (m Match) SameSyntax(other Match) bool {
	return m.Exists() && other.Exists() && sameSyntax(m.node, other.node, true)
}

func sameSyntax(a, b *contract.Node, root bool) bool {
	if a.Kind != b.Kind || !root && a.Field != b.Field || a.Name != b.Name || a.Literal != b.Literal || a.Operator != b.Operator ||
		!slices.Equal(a.Modifiers, b.Modifiers) || !slices.Equal(a.Flags, b.Flags) || len(a.Children) != len(b.Children) {
		return false
	}
	if (a.Value == nil) != (b.Value == nil) || (a.Value != nil && !a.Value.Equal(*b.Value)) {
		return false
	}
	for i := range a.Children {
		if !sameSyntax(a.Children[i], b.Children[i], false) {
			return false
		}
	}

	return true
}

func (m Match) Span() (Span, error) {
	if m.node == nil {
		return Span{}, fmt.Errorf("no node to span")
	}
	source, err := m.file.Source()
	if err != nil {
		return Span{}, err
	}

	return Span{Path: m.file.Path, Source: source, Start: m.node.Span.Start, End: m.node.Span.End}, nil
}

var scopeNamers sync.Map

func NameScopes(language contract.Language, namer func(Match) string) {
	scopeNamers.Store(language, namer)
}

var bodyRunners sync.Map

func RunBodiesAs(language contract.Language, runs func(Match) bool) {
	bodyRunners.Store(language, runs)
}

func (m Match) RunsABody() bool {
	if m.file != nil {
		if runs, counted := bodyRunners.Load(m.file.Language()); counted {
			return runs.(func(Match) bool)(m)
		}
	}

	return m.Is(Function)
}
