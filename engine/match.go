package engine

import (
	"fmt"
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

// Neutral is a language-neutral question a node answers, drawn from the contract's closed set.
type Neutral string

// The neutral kinds a node may answer; see contract/CONTRACT.md, "Neutral kinds".
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
	Literal             Neutral = "literal"
	Import              Neutral = "import"
	Catch               Neutral = "catch"
)

// Located is anything a finding can point at.
type Located interface {
	File() string
	Line() int
	Location() string
	Scope() string
}

// Match is one node of one file. The zero Match is no node: every question on it answers no.
type Match struct {
	node *contract.Node
	file *File
}

var _ Located = Match{}

// Exists says whether the match holds a node; navigation past the tree answers one that does not.
func (m Match) Exists() bool {
	return m.node != nil
}

// Codebase is the codebase the match's file was read into; none for no node.
func (m Match) Codebase() *Codebase {
	if m.file == nil {
		return nil
	}

	return m.file.codebase
}

// Node is the generic tree node the match holds.
func (m Match) Node() *contract.Node {
	return m.node
}

// Source is the file the match sits in.
func (m Match) Source() *File {
	return m.file
}

// File is the path of the file the match sits in.
func (m Match) File() string {
	if m.file == nil {
		return ""
	}

	return m.file.Path
}

// Line is the 1-based line the node starts on.
func (m Match) Line() int {
	if m.node == nil {
		return 0
	}

	return m.node.Span.Line
}

// Location is the match's path:line, the form a finding is reported as.
func (m Match) Location() string {
	return fmt.Sprintf("%s:%d", m.File(), m.Line())
}

// Scope is the enclosing type and function, Type::function, (file) outside any type.
func (m Match) Scope() string {
	scope := "(file)"
	if declaration := m.EnclosingType(); declaration.Exists() {
		scope = declaration.Identity()
	}
	if function := m.EnclosingFunction(); function.Exists() {
		return scope + "::" + function.Name()
	}

	return scope
}

// Identity is the declaration's symbol id, or its name where the resolver gave none.
func (m Match) Identity() string {
	if m.node == nil {
		return ""
	}
	if m.node.Symbol != "" {
		return m.node.Symbol
	}

	return m.node.Name
}

// Kind is the language's own name for the node.
func (m Match) Kind() string {
	if m.node == nil {
		return ""
	}

	return m.node.Kind
}

// Name is the name as written, for a declaration, a name or a member.
func (m Match) Name() string {
	if m.node == nil {
		return ""
	}

	return m.node.Name
}

// Written is the node's source text: the bytes of its span, as the file spells them; empty for no node.
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

// Text is a literal's decoded string value; false when the node is no string literal.
func (m Match) Text() (string, bool) {
	if m.node == nil || m.node.Value == nil {
		return "", false
	}

	return m.node.Value.Text()
}

// Is says whether the node answers a neutral kind.
func (m Match) Is(neutral Neutral) bool {
	return m.node != nil && m.node.Answers(string(neutral))
}

// Parent is the node whose children hold this one; no node above the root.
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

// Children is the node's child nodes, in source order.
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

// Child is the first child filling the field, such as "var" or "body"; no node when none does.
func (m Match) Child(field string) Match {
	for _, child := range m.Children() {
		if child.node.Field == field {
			return child
		}
	}

	return Match{}
}

// Descendants is every node below this one, in pre-order: children before their own children's siblings.
func (m Match) Descendants() []Match {
	var below []Match
	for _, child := range m.Children() {
		below = append(below, child)
		below = append(below, child.Descendants()...)
	}

	return below
}

// ChildrenIn is every child filling the field, in source order, such as a class's "bases" or a body's statements.
func (m Match) ChildrenIn(field string) []Match {
	var filling []Match
	for _, child := range m.Children() {
		if child.node.Field == field {
			filling = append(filling, child)
		}
	}

	return filling
}

// Root is the root node of the match's file; no node for no match.
func (m Match) Root() Match {
	if m.file == nil {
		return Match{}
	}

	return m.file.Match(0)
}

// Closest is the nearest ancestor that answers the neutral kind; no node when none does.
func (m Match) Closest(neutral Neutral) Match {
	for ancestor := m.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		if ancestor.Is(neutral) {
			return ancestor
		}
	}

	return Match{}
}

// EnclosingType is the type declaration the node sits in; no node at file level.
func (m Match) EnclosingType() Match {
	return m.Closest(TypeDeclaration)
}

// EnclosingFunction is the nearest named function-like the node sits in; closures are passed over.
func (m Match) EnclosingFunction() Match {
	for function := m.Closest(Function); function.Exists(); function = function.Closest(Function) {
		if function.Name() != "" {
			return function
		}
	}

	return Match{}
}

// IsWithinLoop says whether a loop encloses the node inside its own function.
func (m Match) IsWithinLoop() bool {
	for ancestor := m.Parent(); ancestor.Exists() && !ancestor.Is(Function); ancestor = ancestor.Parent() {
		if ancestor.Is(Loop) {
			return true
		}
	}

	return false
}

// Comments is every comment attached to the node, leading and trailing.
func (m Match) Comments() []contract.Comment {
	if m.node == nil {
		return nil
	}

	return m.file.Comments(m.node)
}

// CommentsAbove is the run of own-line comments directly above the node, nearest last.
func (m Match) CommentsAbove() []contract.Comment {
	if m.node == nil {
		return nil
	}

	return m.file.CommentsAbove(m.node)
}

// IsDocumented says whether a doc comment is attached to the node.
func (m Match) IsDocumented() bool {
	for _, comment := range m.Comments() {
		if comment.Kind == "doc" {
			return true
		}
	}

	return false
}

// SameSyntax says whether two nodes are written alike: the same kinds in the same slots, with the same names,
// literals, operators, modifiers and flags, child for child. Where they sit and how they are spaced is no part of it.
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

// Span is the node's byte range in its file.
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
