// Package contract reads the generic tree every language bridge writes, as contract/CONTRACT.md defines it.
package contract

import (
	"encoding/json"
	"fmt"
)

// Language is the language a stream or a file is written in.
type Language string

const (
	PHP        Language = "php"
	Vue        Language = "vue"
	TypeScript Language = "typescript"
	Python     Language = "python"
	CSharp     Language = "csharp"
)

// Header opens a stream.
type Header struct {
	Contract string   `json:"contract"`
	Version  int      `json:"version"`
	Language Language `json:"language"`
	Bridge   Bridge   `json:"bridge"`
	Roots    []string `json:"roots"`
}

// Bridge names the program that wrote a stream.
type Bridge struct {
	Name    string `json:"name"`
	Version string `json:"version"`
}

// File is one source file's tree and comments.
type File struct {
	Path     string    `json:"path"`
	Language Language  `json:"language"`
	Errors   int       `json:"errors"`
	Context  bool      `json:"context,omitempty"`
	Test     bool      `json:"test,omitempty"`
	Module   string    `json:"module,omitempty"`
	Resolver *Resolver `json:"resolver,omitempty"`
	Root     *Node     `json:"root"`
	Comments []Comment `json:"comments"`

	nodes []*Node
}

// Resolver says which resolver a file had and whether it ran.
type Resolver struct {
	Tool string `json:"tool"`
	Ran  bool   `json:"ran"`
}

// Node answers a node by its id, once the file is read.
func (f *File) Node(id int) (*Node, bool) {
	if id < 0 || id >= len(f.nodes) {
		return nil, false
	}

	return f.nodes[id], true
}

// Nodes is every node of the file in pre-order.
func (f *File) Nodes() []*Node {
	return f.nodes
}

// Span is a byte range into the file and the line it starts on.
type Span struct {
	Start int
	End   int
	Line  int
}

func (s Span) MarshalJSON() ([]byte, error) {
	return json.Marshal([3]int{s.Start, s.End, s.Line})
}

func (s *Span) UnmarshalJSON(data []byte) error {
	var parts []int
	if err := json.Unmarshal(data, &parts); err != nil {
		return err
	}
	if len(parts) != 3 {
		return fmt.Errorf("a span is [start, end, line], got %d numbers", len(parts))
	}
	s.Start, s.End, s.Line = parts[0], parts[1], parts[2]

	return nil
}

// Node is one syntax node.
type Node struct {
	ID        int         `json:"id"`
	Kind      string      `json:"kind"`
	Role      string      `json:"role"`
	Is        []string    `json:"is,omitempty"`
	Span      Span        `json:"span"`
	Field     string      `json:"field,omitempty"`
	Children  []*Node     `json:"children,omitempty"`
	Name      string      `json:"name,omitempty"`
	Literal   string      `json:"literal,omitempty"`
	Value     *Value      `json:"value,omitempty"`
	Operator  string      `json:"operator,omitempty"`
	Modifiers []string    `json:"modifiers,omitempty"`
	Flags     []string    `json:"flags,omitempty"`
	Declared  *Type       `json:"declared,omitempty"`
	Returns   *Type       `json:"returns,omitempty"`
	Resolved  *Type       `json:"resolved,omitempty"`
	Symbol    string      `json:"symbol,omitempty"`
	Refers    string      `json:"refers,omitempty"`
	Target    *Target     `json:"target,omitempty"`
	Resolves  string      `json:"resolves,omitempty"`
	Constant  bool        `json:"constant,omitempty"`
	Inherited bool        `json:"inherited,omitempty"`
	Extras    *NodeExtras `json:"extras,omitempty"`

	parent *Node
}

// Parent is the node whose children hold this one; the root has none.
func (n *Node) Parent() (*Node, bool) {
	return n.parent, n.parent != nil
}

// Answers says whether the node answers a neutral kind, such as "loop".
func (n *Node) Answers(neutral string) bool {
	for _, is := range n.Is {
		if is == neutral {
			return true
		}
	}

	return false
}

// Value is a literal's decoded value: a string, a boolean or null. Numbers are decimal strings.
type Value struct {
	raw json.RawMessage
}

func (v Value) MarshalJSON() ([]byte, error) {
	return v.raw, nil
}

func (v *Value) UnmarshalJSON(data []byte) error {
	var probe any
	if err := json.Unmarshal(data, &probe); err != nil {
		return err
	}
	switch probe.(type) {
	case nil, string, bool:
		v.raw = append(json.RawMessage(nil), data...)
		return nil
	}

	return fmt.Errorf("a value is a string, a boolean or null, got %s", data)
}

// Equal says whether two values are the same value.
func (v Value) Equal(other Value) bool {
	return string(v.raw) == string(other.raw)
}

// IsNull says whether the value is the literal null.
func (v Value) IsNull() bool {
	return string(v.raw) == "null"
}

// Text is the value as a string, when it is one.
func (v Value) Text() (string, bool) {
	var text string
	err := json.Unmarshal(v.raw, &text)

	return text, err == nil
}

// Bool is the value as a boolean, when it is one.
func (v Value) Bool() (bool, bool) {
	var truth bool
	err := json.Unmarshal(v.raw, &truth)

	return truth, err == nil
}

// Type is a type, written or resolved.
type Type struct {
	Text       string      `json:"text"`
	Kind       string      `json:"kind"`
	Name       string      `json:"name,omitempty"`
	Args       []*Type     `json:"args,omitempty"`
	Members    []*Type     `json:"members,omitempty"`
	Fields     []TypeField `json:"fields,omitempty"`
	Parameters []*Type     `json:"parameters,omitempty"`
	Returns    *Type       `json:"returns,omitempty"`
	Value      *Value      `json:"value,omitempty"`
	Nullable   bool        `json:"nullable,omitempty"`
	ValueType  bool        `json:"valueType,omitempty"`
	Element    *Type       `json:"element,omitempty"`
	Constructs string      `json:"constructs,omitempty"`
	Origin     string      `json:"origin"`
}

// TypeField is one field of an object type.
type TypeField struct {
	Name     string `json:"name"`
	Type     *Type  `json:"type"`
	Optional bool   `json:"optional,omitempty"`
}

// Target is the declaration a call or construction reaches.
type Target struct {
	Symbol string `json:"symbol"`
	Type   string `json:"type,omitempty"`
	Name   string `json:"name"`
}

// Comment is one comment, attached to the node it belongs to.
type Comment struct {
	ID       int            `json:"id"`
	Kind     string         `json:"kind"`
	Text     string         `json:"text"`
	Span     Span           `json:"span"`
	Attached *int           `json:"attached,omitempty"`
	Trailing bool           `json:"trailing,omitempty"`
	Refs     []Ref          `json:"refs,omitempty"`
	Extras   *CommentExtras `json:"extras,omitempty"`
}

// Ref is a reference a doc comment makes to code.
type Ref struct {
	Text      string `json:"text"`
	Symbol    string `json:"symbol,omitempty"`
	Owner     string `json:"owner,omitempty"`
	OwnedHere *bool  `json:"ownedHere,omitempty"`
	Blind     bool   `json:"blind,omitempty"`
}

// NodeExtras holds the facts only one language has on a node.
type NodeExtras struct {
	CSharp     *CSharpNode     `json:"csharp,omitempty"`
	Python     *PythonNode     `json:"python,omitempty"`
	Vue        *VueNode        `json:"vue,omitempty"`
	TypeScript *TypeScriptNode `json:"typescript,omitempty"`
}

// CSharpNode is what only C# says about a node.
type CSharpNode struct {
	ForgivesNull bool `json:"forgivesNull,omitempty"`
}

// PythonNode is what only Python says about a node.
type PythonNode struct {
	Operators []string `json:"operators,omitempty"`
	Level     int      `json:"level,omitempty"`
}

// VueNode is what only Vue says about a node.
type VueNode struct {
	Directive *Directive `json:"directive,omitempty"`
}

// Directive is a Vue directive's name and modifiers; its argument and value are children.
type Directive struct {
	Name      string   `json:"name"`
	Modifiers []string `json:"modifiers"`
}

// TypeScriptNode is what only TypeScript says about a node.
type TypeScriptNode struct {
	TypeOnly bool `json:"typeOnly,omitempty"`
}

// CommentExtras holds the facts only one language has on a comment.
type CommentExtras struct {
	CSharp *CSharpComment `json:"csharp,omitempty"`
	PHP    *PHPComment    `json:"php,omitempty"`
}

// PHPComment is what only PHP says about a comment.
type PHPComment struct {
	Code bool `json:"code,omitempty"`
}

// CSharpComment is what only C# says about a comment.
type CSharpComment struct {
	Code bool `json:"code,omitempty"`
}

// Program holds the facts about the whole program that no file carries.
type Program struct {
	Symbols  []OutsideSymbol `json:"symbols,omitempty"`
	Packages []string        `json:"packages,omitempty"`
	Aliases  []Alias         `json:"aliases,omitempty"`
}

// OutsideSymbol is a declaration outside the scanned files.
type OutsideSymbol struct {
	Symbol     string          `json:"symbol"`
	Kind       string          `json:"kind"`
	Name       string          `json:"name"`
	Extends    []string        `json:"extends,omitempty"`
	Implements []string        `json:"implements,omitempty"`
	Uses       []string        `json:"uses,omitempty"`
	Modifiers  []string        `json:"modifiers,omitempty"`
	Members    []OutsideMember `json:"members,omitempty"`
}

// OutsideMember is a member of an outside declaration.
type OutsideMember struct {
	Symbol     string             `json:"symbol"`
	Name       string             `json:"name"`
	Kind       string             `json:"kind"`
	Modifiers  []string           `json:"modifiers,omitempty"`
	Declared   *Type              `json:"declared,omitempty"`
	Returns    *Type              `json:"returns,omitempty"`
	Documented *Type              `json:"documented,omitempty"`
	Parameters []OutsideParameter `json:"parameters,omitempty"`
}

// OutsideParameter is a parameter of an outside member.
type OutsideParameter struct {
	Name     string   `json:"name"`
	Declared *Type    `json:"declared,omitempty"`
	Flags    []string `json:"flags,omitempty"`
}

// Alias is a module path alias imports resolve through.
type Alias struct {
	Prefix string `json:"prefix"`
	Path   string `json:"path"`
}

// Trailer closes a stream.
type Trailer struct {
	Files      int         `json:"files"`
	Resolution *Resolution `json:"resolution,omitempty"`
}

// Resolution says how much the bridge's resolver saw and resolved.
type Resolution struct {
	Expressions *int `json:"expressions,omitempty"`
	Typed       *int `json:"typed,omitempty"`
	Calls       *int `json:"calls,omitempty"`
	Resolved    *int `json:"resolved,omitempty"`
	Unjoined    *int `json:"unjoined,omitempty"`
}
