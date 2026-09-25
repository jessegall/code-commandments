package typescript

import (
	"slices"
	"strings"
)

// A type as the PHP tool models and prints it: the types an extracted component declares must come out byte for
// byte as the PHP tool writes them. The model is built from the bridge's type nodes (see TypeOf), never from text.

// TypeNode is a type as the PHP tool's grammar models it.
type TypeNode interface {
	Render() string
	References() []string
	UnwrapRef() TypeNode
	AdmitsAbsence() bool
	FieldsWith(declared func(name string) Fields) Fields
}

// Fields is a type's members by name, each its type, in the order it declares them.
type Fields struct {
	Names []string
	Types map[string]TypeNode
}

// Set records a member's type; a member named again keeps its place.
func (f *Fields) Set(name string, typed TypeNode) {
	if f.Types == nil {
		f.Types = map[string]TypeNode{}
	}
	if _, seen := f.Types[name]; !seen {
		f.Names = append(f.Names, name)
	}
	f.Types[name] = typed
}

// Get is a member's type.
func (f Fields) Get(name string) (TypeNode, bool) {
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

// Fields is each member's type, by name.
func (t ObjectType) Fields() Fields {
	var fields Fields
	for _, member := range t.Members {
		fields.Set(member.Name, member.Type())
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

// Unknown is the type nothing tells.
var Unknown TypeNode = KeywordType{Name: "unknown"}

// inferredUnion is the union an expression infers, printed as the PHP tool first writes it, `string|null`, and
// read again as the union it is once the reactive wrapper comes off.
type inferredUnion struct {
	CompositeType
}

func (t inferredUnion) Render() string {
	rendered := make([]string, 0, len(t.Members))
	for _, member := range t.Members {
		rendered = append(rendered, member.Render())
	}

	return strings.Join(rendered, "|")
}

func (t inferredUnion) UnwrapRef() TypeNode {
	var flat []TypeNode
	for _, member := range t.Members {
		if nested, ok := member.(inferredUnion); ok {
			flat = append(flat, nested.Members...)
		} else {
			flat = append(flat, member)
		}
	}

	return CompositeType{Operator: "|", Members: flat}.UnwrapRef()
}
