package engine

import (
	"fmt"
	"slices"
	"strings"

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
		if namer := spokenIn(m.file.Language()).namesScope; namer != nil {
			return namer(m)
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

// Field is the name of the role the node fills in its parent: "" for a file's root.
func (m Match) Field() string {
	if m.node == nil {
		return ""
	}

	return m.node.InRole()
}

// Role is the broad part the node plays: a member of a type, a statement, an expression, a type as written, markup,
// or other.
func (m Match) Role() string {
	switch {
	case m.node == nil:
		return ""
	case m.node.Is("declaration") && m.Parent().Kind() == "type":
		return "member"
	case m.node.Kind == "type-expression" || m.node.Kind == "tuple-type":
		return "type"
	case m.node.Is("statement"):
		return "statement"
	case m.node.Is("expression"):
		return "expression"
	case m.node.Is("markup"):
		return "markup"
	}

	return "other"
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

func (m Match) TargetSymbol() string {
	if m.node == nil || m.node.Target == nil {
		return ""
	}

	return m.node.Target.Symbol
}

// HasFlag answers the syntax facts the old tree spelled as flags, read from the facts version 5 states them as.
func (m Match) HasFlag(flag string) bool {
	if m.node == nil {
		return false
	}
	node := m.node
	switch flag {
	case "variadic":
		return node.Collects == "rest"
	case "keywords":
		return node.Collects == "keywords"
	case "by-ref":
		return node.Passing == "reference"
	case "promoted":
		return m.Kind() == "parameter" && m.isImplicitMemberOf(m.Parent().EnclosingType())
	case "keyword-only":
		return node.Position == "keyword"
	case "optional":
		return node.Optional
	case "spread":
		return node.Spread != "" && node.Spread != "none" || node.Expansion != "" && node.Expansion != "none"
	case "named":
		return node.Kind == "argument" && node.Label != ""
	case "async":
		return node.Async
	case "generator":
		return node.Generator
	case "short-ternary":
		return node.Kind == "fallback" && node.Trigger == "falsy"
	case "shorthand":
		return node.Shorthand
	case "component":
		return node.Kind == "element" && node.Form == "component"
	case "nullable-sugar":
		return node.Kind == "type-expression" && node.Form == "nullable"
	case "constant":
		return node.Kind == "variable" && node.Form == "const"
	case "group":
		return node.Group
	case "step":
		return node.InRole() == "steps"
	case "elif":
		return node.Kind == "if" && node.InRole() == "else"
	}

	return false
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
	return m.node != nil && m.node.ConstantValue != nil
}

func (m Match) Text() (string, bool) {
	if m.node == nil || m.node.Value == nil {
		return "", false
	}

	return m.node.Value.Text()
}

func (m Match) isImplicitMemberOf(owner Match) bool {
	return owner.Exists() && slices.Contains(owner.node.Linked("implicitMembers"), m.node.ID)
}

// Is answers a neutral question by the version 5 kind and facts that state it.
func (m Match) Is(neutral Neutral) bool {
	if m.node == nil {
		return false
	}
	node := m.node
	switch neutral {
	case Function:
		return node.Kind == "function"
	case TypeDeclaration:
		return node.Kind == "type" || node.Kind == "alias"
	case Branch:
		return node.Kind == "if" || node.Kind == "dispatch" || node.Kind == "select" || node.Kind == "conditional"
	case BailOut:
		return node.Is("exit") && node.Kind != "goto"
	case MemberAccess:
		return node.Kind == "access"
	case NullSafe:
		return node.NullConditional
	case SelfReference:
		return node.Kind == "reference" && node.Form == "self"
	case Identifier:
		return node.Kind == "reference" && node.Form != "self"
	case Conjunction:
		return node.Kind == "logical"
	case Catch:
		return node.Kind == "handler"
	}

	return node.Kind == string(neutral)
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

	CountedVisit()

	owned := m.node.Children()
	children := make([]Match, 0, len(owned))
	for _, child := range owned {
		children = append(children, Match{node: child, file: m.file})
	}

	return children
}

func (m Match) Child(field string) Match {
	if m.node == nil {
		return Match{}
	}
	if child, present := m.node.Child(field); present {
		return Match{node: child, file: m.file}
	}

	return Match{}
}

func (m Match) Descendants() []Match {
	if m.node == nil {
		return nil
	}

	return m.below(nil)
}

func (m Match) below(found []Match) []Match {
	CountedVisit()

	for _, child := range m.node.Children() {
		under := Match{node: child, file: m.file}
		found = under.below(append(found, under))
	}

	return found
}

func (m Match) ChildrenIn(field string) []Match {
	if m.node == nil {
		return nil
	}
	held, _ := m.node.Role(field)
	filling := make([]Match, 0, len(held))
	for _, child := range held {
		filling = append(filling, Match{node: child, file: m.file})
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

func (m Match) IsWithinFunction() bool {
	return m.Closest(Function).Exists()
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
		if comment.Form == "doc" && (Commented{Comment: comment, File: m.file}).Prose() != "" {
			return true
		}
	}

	return false
}

func (m Match) SameSyntax(other Match) bool {
	return m.Exists() && other.Exists() && sameSyntax(m.node, other.node, true)
}

func (m Match) EndsBefore(other Match) bool {
	return m.Exists() && other.Exists() && m.file == other.file && m.node.Span.End < other.node.Span.Start
}

func sameSyntax(a, b *contract.Node, root bool) bool {
	first, second := a.Children(), b.Children()
	if a.Kind != b.Kind || !root && a.InRole() != b.InRole() || a.Name != b.Name || a.LiteralKind != b.LiteralKind || a.Operator != b.Operator ||
		a.Form != b.Form || !slices.Equal(a.Modifiers, b.Modifiers) || len(first) != len(second) {
		return false
	}
	if (a.Value == nil) != (b.Value == nil) || (a.Value != nil && !a.Value.Equal(*b.Value)) {
		return false
	}
	for i := range first {
		if !sameSyntax(first[i], second[i], false) {
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

func NameScopes(language contract.Language, namer func(Match) string) {
	registers(language, func(row *spoken) { row.namesScope = namer })
}

func RunBodiesAs(language contract.Language, runs func(Match) bool) {
	registers(language, func(row *spoken) { row.runsBody = runs })
}

func (m Match) RunsABody() bool {
	if m.file != nil {
		if runs := spokenIn(m.file.Language()).runsBody; runs != nil {
			return runs(m)
		}
	}

	return m.Is(Function)
}
