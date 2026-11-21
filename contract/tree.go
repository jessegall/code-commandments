// Package contract reads the generic tree every language bridge writes, as contract/CONTRACT.md defines it.
package contract

import (
	"bytes"
	"encoding/json"
	"fmt"
	"reflect"
	"slices"
	"strings"
)

// Language is the language a stream or a file is written in.
type Language string

const (
	PHP        Language = "php"
	Vue        Language = "vue"
	TypeScript Language = "typescript"
	Python     Language = "python"
	CSharp     Language = "csharp"
	Go         Language = "go"
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
	// Reads says every file line names the files its tree read, which is what lets a run keep the line between runs.
	Reads bool `json:"reads,omitempty"`
}

// AskedToWrite is the one rule of which lines a bridge writes as judged: a file asked to be written, or under a
// folder asked to be, or every file when nothing was named. Any other file a bridge writes is context, read only to
// resolve into, whatever the bridge marked it.
func AskedToWrite(path string, written []string) bool {
	if len(written) == 0 {
		return true
	}
	for _, asked := range written {
		if path == asked || strings.HasPrefix(path, strings.TrimSuffix(asked, "/")+"/") {
			return true
		}
	}

	return false
}

// File is one source file's tree and comments.
type File struct {
	Path     string   `json:"path"`
	Language Language `json:"language"`
	Errors   int      `json:"errors"`
	Context  bool     `json:"context,omitempty"`
	Test     bool     `json:"test,omitempty"`
	Module   string   `json:"module,omitempty"`
	// PublishedBy is the dependency that ships this file, when the project holds a copy of a file a package publishes,
	// such as a Laravel config file published from vendor: the package reads it, so nothing in the project need.
	PublishedBy string `json:"publishedBy,omitempty"`
	// Reads are the other files whose contents shaped this tree, configuration and lockfiles included: a tree kept
	// between runs stands while its own file and every one of these are unchanged.
	Reads    []string  `json:"reads,omitempty"`
	Resolver *Resolver `json:"resolver,omitempty"`
	Root     *Node     `json:"root"`
	Comments []Comment `json:"comments"`
	Types    []*Type   `json:"types,omitempty"`

	nodes   []*Node
	reaches int
}

// Resolver says which resolver a file had and whether it ran.
type Resolver struct {
	Tool string `json:"tool"`
	Ran  bool   `json:"ran"`
}

func (f *File) Node(id int) (*Node, bool) {
	if id < 0 || id >= len(f.nodes) {
		return nil, false
	}

	return f.nodes[id], true
}

func (f *File) Nodes() []*Node {
	return f.nodes
}

// Knows says whether the file's evidence for a fact is complete enough to read it: a compiler fact is unknown on every
// node of a file whose resolver did not run.
func (f *File) Knows(fact string) bool {
	if Provenance(fact) != "compiler" {
		return true
	}

	return f.Resolver != nil && f.Resolver.Ran
}

func (f *File) Reaches() int {
	return f.reaches
}

func (f *File) CopiedTo(path string, moved func(string) string) *File {
	copied := *f
	copied.Path = path
	copied.nodes = make([]*Node, 0, len(f.nodes))

	var walk func(node, parent *Node) *Node
	walk = func(node, parent *Node) *Node {
		own := *node
		own.parent = parent
		if node.Facts != none && node.Resolves != "" {
			facts := *node.Facts
			facts.Resolves = moved(facts.Resolves)
			own.Facts = &facts
		}
		copied.nodes = append(copied.nodes, &own)
		own.Parts = make(Parts, len(node.Parts))
		for at, part := range node.Parts {
			own.Parts[at] = Part{Role: part.Role, List: part.List, Nodes: make([]*Node, len(part.Nodes))}
			for index, child := range part.Nodes {
				own.Parts[at].Nodes[index] = walk(child, &own)
			}
		}

		return &own
	}
	if f.Root != nil {
		copied.Root = walk(f.Root, nil)
	}
	slices.SortFunc(copied.nodes, func(a, b *Node) int { return a.ID - b.ID })

	return &copied
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

// Node is one operation of the language, translated: its kind, its span, the nodes it owns under named roles, the
// nodes it points at without owning, and the facts its kind declares (contract/CONTRACT.md, A node).
type Node struct {
	ID    int    `json:"id"`
	Kind  string `json:"kind"`
	Span  Span   `json:"span"`
	Parts Parts  `json:"parts,omitempty"`
	Links Links  `json:"links,omitempty"`
	*Facts
	Resolved *Type `json:"-"`

	parent *Node
	role   string
}

// Facts are a node's facts, every one optional; a compiler fact that is absent is unknown, never known-empty.
type Facts struct {
	Name          string         `json:"name,omitempty"`
	Symbol        string         `json:"symbol,omitempty"`
	Refers        string         `json:"refers,omitempty"`
	Target        *Target        `json:"target,omitempty"`
	ResolvedType  *int           `json:"resolvedType,omitempty"`
	ConstantValue *ConstantValue `json:"constantValue,omitempty"`
	Modifiers     []string       `json:"modifiers,omitempty"`
	Effective     *Effective     `json:"effective,omitempty"`
	Obligations   []Obligation   `json:"obligations,omitempty"`
	Incomplete    bool           `json:"incomplete,omitempty"`

	Source          string   `json:"source,omitempty"`
	Form            string   `json:"form,omitempty"`
	TypeOnly        bool     `json:"typeOnly,omitempty"`
	RelativeLevel   int      `json:"relativeLevel,omitempty"`
	Resolves        string   `json:"resolves,omitempty"`
	Written         *Type    `json:"written,omitempty"`
	Async           bool     `json:"async,omitempty"`
	Generator       bool     `json:"generator,omitempty"`
	Passing         string   `json:"passing,omitempty"`
	Collects        string   `json:"collects,omitempty"`
	Position        string   `json:"position,omitempty"`
	Optional        bool     `json:"optional,omitempty"`
	Evaluated       string   `json:"evaluated,omitempty"`
	Exhaustive      *bool    `json:"exhaustive,omitempty"`
	Default         bool     `json:"default,omitempty"`
	FallsThrough    bool     `json:"fallsThrough,omitempty"`
	Depth           int      `json:"depth,omitempty"`
	CatchForm       string   `json:"catchForm,omitempty"`
	Group           bool     `json:"group,omitempty"`
	NamedResults    *bool    `json:"namedResults,omitempty"`
	NullConditional bool     `json:"nullConditional,omitempty"`
	Label           string   `json:"label,omitempty"`
	Spread          string   `json:"spread,omitempty"`
	Mode            string   `json:"mode,omitempty"`
	Operator        string   `json:"operator,omitempty"`
	Operators       []string `json:"operators,omitempty"`
	Prefix          bool     `json:"prefix,omitempty"`
	Trigger         string   `json:"trigger,omitempty"`
	Negated         bool     `json:"negated,omitempty"`
	Delegates       bool     `json:"delegates,omitempty"`
	LiteralKind     string   `json:"literalKind,omitempty"`
	Value           *Value   `json:"value,omitempty"`
	Expansion       string   `json:"expansion,omitempty"`
	Conversion      string   `json:"conversion,omitempty"`
	Tag             string   `json:"tag,omitempty"`
	Shorthand       bool     `json:"shorthand,omitempty"`
	Construct       string   `json:"construct,omitempty"`
}

// ConstantValue is the compile-time value of an expression the compiler folds; a null constant has type "null" and no value.
type ConstantValue struct {
	Type  string `json:"type"`
	Value *Value `json:"value,omitempty"`
}

// Effective is what the language makes of a declaration's modifiers, its implicit defaults included.
type Effective struct {
	Visibility string `json:"visibility,omitempty"`
	Static     bool   `json:"static,omitempty"`
	Abstract   bool   `json:"abstract,omitempty"`
	Final      bool   `json:"final,omitempty"`
	Readonly   bool   `json:"readonly,omitempty"`
}

// Obligation is a declaration a member overrides or implements, and whether its signature is fixed outside the project.
type Obligation struct {
	Symbol string `json:"symbol"`
	Origin string `json:"origin"`
}

// Part is the nodes one role of a node holds: one node, or an ordered list when its role is a list.
type Part struct {
	Role  string
	Nodes []*Node
	List  bool
}

// Parts are a node's owning roles, in the order the stream wrote them.
type Parts []Part

func (p Parts) MarshalJSON() ([]byte, error) {
	var out bytes.Buffer
	out.WriteByte('{')
	for at, part := range p {
		if at > 0 {
			out.WriteByte(',')
		}
		role, _ := json.Marshal(part.Role)
		out.Write(role)
		out.WriteByte(':')
		var held any = part.Nodes
		if !part.List {
			held = part.Nodes[0]
		}
		written, err := json.Marshal(held)
		if err != nil {
			return nil, err
		}
		out.Write(written)
	}
	out.WriteByte('}')

	return out.Bytes(), nil
}

func (p *Parts) UnmarshalJSON(data []byte) error {
	return orderedObject(data, func(role string, value json.RawMessage) error {
		part := Part{Role: role}
		if trimmed := bytes.TrimSpace(value); len(trimmed) > 0 && trimmed[0] == '[' {
			part.List = true
			if err := strictly(value, &part.Nodes); err != nil {
				return fmt.Errorf("role %s: %w", role, err)
			}
		} else {
			var node Node
			if err := strictly(value, &node); err != nil {
				return fmt.Errorf("role %s: %w", role, err)
			}
			part.Nodes = []*Node{&node}
		}
		*p = append(*p, part)

		return nil
	})
}

// Link is the node ids one link of a node points at: one id, or an ordered list.
type Link struct {
	Name string
	IDs  []int
	List bool
}

// Links are a node's non-owning edges, in the order the stream wrote them.
type Links []Link

func (l Links) MarshalJSON() ([]byte, error) {
	held := map[string]any{}
	for _, link := range l {
		if link.List {
			held[link.Name] = link.IDs
		} else {
			held[link.Name] = link.IDs[0]
		}
	}

	return json.Marshal(held)
}

func (l *Links) UnmarshalJSON(data []byte) error {
	return orderedObject(data, func(name string, value json.RawMessage) error {
		link := Link{Name: name}
		if trimmed := bytes.TrimSpace(value); len(trimmed) > 0 && trimmed[0] == '[' {
			link.List = true
			if err := json.Unmarshal(value, &link.IDs); err != nil {
				return fmt.Errorf("link %s: %w", name, err)
			}
		} else {
			var id int
			if err := json.Unmarshal(value, &id); err != nil {
				return fmt.Errorf("link %s: %w", name, err)
			}
			link.IDs = []int{id}
		}
		*l = append(*l, link)

		return nil
	})
}

func orderedObject(data []byte, each func(key string, value json.RawMessage) error) error {
	decoder := json.NewDecoder(bytes.NewReader(data))
	opened, err := decoder.Token()
	if err != nil {
		return err
	}
	if opened != json.Delim('{') {
		return fmt.Errorf("an object, not %v", opened)
	}
	for decoder.More() {
		key, err := decoder.Token()
		if err != nil {
			return err
		}
		var value json.RawMessage
		if err := decoder.Decode(&value); err != nil {
			return err
		}
		if err := each(key.(string), value); err != nil {
			return err
		}
	}

	return nil
}

func strictly(data []byte, into any) error {
	decoder := json.NewDecoder(bytes.NewReader(data))
	decoder.DisallowUnknownFields()

	return decoder.Decode(into)
}

// Answer is what a node says about one of its roles: present, known to be empty, unknown because the evidence is
// incomplete, or inapplicable because its kind has no such role.
type Answer int

const (
	Present Answer = iota
	KnownEmpty
	Unknown
	Inapplicable
)

// Role is the nodes a role holds and the answer they make. An incomplete node, one the parser recovered from an error,
// answers unknown for every role it does not hold.
func (n *Node) Role(role string) ([]*Node, Answer) {
	if _, err := RoleArity(n.Kind, role); err != nil {
		return nil, Inapplicable
	}
	for _, part := range n.Parts {
		if part.Role == role {
			if len(part.Nodes) == 0 && n.Incomplete {
				return nil, Unknown
			}
			if len(part.Nodes) == 0 {
				return nil, KnownEmpty
			}

			return part.Nodes, Present
		}
	}
	if n.Incomplete {
		return nil, Unknown
	}

	return nil, KnownEmpty
}

// Child is the one node a role holds, when it holds one.
func (n *Node) Child(role string) (*Node, bool) {
	held, answer := n.Role(role)

	return firstOf(held), answer == Present
}

func firstOf(nodes []*Node) *Node {
	if len(nodes) == 0 {
		return nil
	}

	return nodes[0]
}

// Children are every node a node owns, in source order.
func (n *Node) Children() []*Node {
	var children []*Node
	for _, part := range n.Parts {
		children = append(children, part.Nodes...)
	}
	slices.SortStableFunc(children, func(a, b *Node) int { return a.ID - b.ID })

	return children
}

// Linked is the ids a link of a node points at.
func (n *Node) Linked(name string) []int {
	for _, link := range n.Links {
		if link.Name == name {
			return link.IDs
		}
	}

	return nil
}

var none = &Facts{}

func NewNode(node Node) *Node {
	if node.Facts == nil {
		node.Facts = none
	}

	return &node
}

func Untouched() bool {
	return reflect.ValueOf(*none).IsZero()
}

func (n *Node) Own() *Facts {
	if n.Facts == none {
		n.Facts = &Facts{}
	}

	return n.Facts
}

func (n *Node) Parent() (*Node, bool) {
	return n.parent, n.parent != nil
}

// InRole is the name of the role this node fills in its parent: "" for a file's root.
func (n *Node) InRole() string {
	return n.role
}

// Is says whether the node belongs to a category (contract/CONTRACT.md, Categories) or is of the kind named.
func (n *Node) Is(kindOrCategory string) bool {
	return n.Kind == kindOrCategory || InCategory(n.Kind, kindOrCategory)
}

// Value is a literal's decoded value: a string, a boolean or null.
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

func (v Value) Equal(other Value) bool {
	return string(v.raw) == string(other.raw)
}

func (v Value) IsNull() bool {
	return string(v.raw) == "null"
}

func (v Value) Text() (string, bool) {
	var text string
	err := json.Unmarshal(v.raw, &text)

	return text, err == nil
}

func (v Value) Bool() (bool, bool) {
	var truth bool
	err := json.Unmarshal(v.raw, &truth)

	return truth, err == nil
}

// Stated is a yes or no the compiler stated, for a fact whose absence means the compiler could not say.
func Stated(answer bool) *bool {
	return &answer
}

// IsNullable says whether the compiler stated the type admits absence.
func (t *Type) IsNullable() bool {
	return t != nil && t.Nullable != nil && *t.Nullable
}

// IsValueType says whether the compiler stated the type is copied by value.
func (t *Type) IsValueType() bool {
	return t != nil && t.ValueType != nil && *t.ValueType
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
	Nullable   *bool       `json:"nullable,omitempty"`
	ValueType  *bool       `json:"valueType,omitempty"`
	Element    *Type       `json:"element,omitempty"`
	Constructs string      `json:"constructs,omitempty"`
	Origin     string      `json:"origin"`
}

func (t Type) MarshalJSON() ([]byte, error) {
	type plain Type

	written := struct {
		plain
		Members *[]*Type `json:"members,omitempty"`
	}{plain: plain(t)}

	if t.Members != nil {
		written.Members = &t.Members
	}

	return json.Marshal(written)
}

var Roles = []string{"statement", "expression", "member", "type", "pattern", "markup", "other"}

var TypeShapes = []string{"named", "keyword", "parameter", "union", "intersection", "array", "tuple", "object",
	"function", "literal", "pointer", "map", "opaque"}

var TypeOrigins = []string{"written", "docblock", "attribute", "inferred", "compiler"}

// TypeField is one field of an object type.
type TypeField struct {
	Name     string `json:"name"`
	Type     *Type  `json:"type,omitempty"`
	Optional bool   `json:"optional,omitempty"`
}

// Target is the declaration a call or construction reaches.
type Target struct {
	Symbol     string   `json:"symbol"`
	Type       string   `json:"type,omitempty"`
	Name       string   `json:"name"`
	Parameters []string `json:"parameters,omitempty"`
}

// Comment is one comment, attached to the node it belongs to.
type Comment struct {
	ID       int       `json:"id"`
	Form     string    `json:"form"`
	Text     string    `json:"text"`
	Span     Span      `json:"span"`
	Attached *int      `json:"attached,omitempty"`
	Trailing bool      `json:"trailing,omitempty"`
	Refs     []Ref     `json:"refs,omitempty"`
	Fragment *Fragment `json:"fragment,omitempty"`
	// Description is what a doc comment says in prose, line by line, its markers and tags left out.
	Description []string `json:"description,omitempty"`
	// Tags are a doc comment's tagged parts, in the language's own syntax read by its own parser.
	Tags []DocTag `json:"tags,omitempty"`
}

// DocTag is one tagged part of a doc comment: an @param of PHPDoc or JSDoc, an XML element of a C# doc comment, a
// section of a Python docstring. Type is the type it writes and Subject the name it is about, when it has them.
type DocTag struct {
	Name    string `json:"name"`
	Text    string `json:"text,omitempty"`
	Type    string `json:"type,omitempty"`
	Subject string `json:"subject,omitempty"`
}

// Fragment is what the bridge's parser made of a comment's words read as code; whether that is commented-out code is
// the shared rule's decision.
type Fragment struct {
	Context        string `json:"context"`
	Diagnostics    int    `json:"diagnostics"`
	Statements     int    `json:"statements"`
	ExpressionKind string `json:"expressionKind,omitempty"`
}

// Ref is a reference a doc comment makes to code.
type Ref struct {
	Text      string `json:"text"`
	Symbol    string `json:"symbol,omitempty"`
	Owner     string `json:"owner,omitempty"`
	OwnedHere *bool  `json:"ownedHere,omitempty"`
	Blind     bool   `json:"blind,omitempty"`
}

// Program holds the facts about the whole program that no file carries.
type Program struct {
	Symbols        []OutsideSymbol    `json:"symbols,omitempty"`
	Unreadable     []UnreadableSymbol `json:"unreadable,omitempty"`
	Packages       []Package          `json:"packages,omitempty"`
	PackageFolders []string           `json:"packageFolders,omitempty"`
	Aliases        []Alias            `json:"aliases,omitempty"`
	Loader         string             `json:"loader,omitempty"`
}

// Package is one installed package and its version, which package facts are keyed by.
type Package struct {
	Name      string `json:"name"`
	Version   string `json:"version"`
	Ecosystem string `json:"ecosystem"`
}

// UnreadableSymbol is a class the scan names that the scanned project's own loader failed on, and why: it stays outside the scan, said rather than dropped.
type UnreadableSymbol struct {
	Symbol string `json:"symbol"`
	Reason string `json:"reason"`
}

// OutsideSymbol is a declaration outside the scanned files.
type OutsideSymbol struct {
	Symbol     string          `json:"symbol"`
	Kind       string          `json:"kind"`
	Name       string          `json:"name"`
	File       string          `json:"file,omitempty"`
	Internal   bool            `json:"internal,omitempty"`
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
