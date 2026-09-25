package csharp

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// keywords are the types C# spells with a keyword, by the name the compiler gives them.
var keywords = map[string]string{
	"bool": "global::System.Boolean", "byte": "global::System.Byte", "sbyte": "global::System.SByte", "char": "global::System.Char",
	"decimal": "global::System.Decimal", "double": "global::System.Double", "float": "global::System.Single", "int": "global::System.Int32",
	"uint": "global::System.UInt32", "long": "global::System.Int64", "ulong": "global::System.UInt64", "short": "global::System.Int16",
	"ushort": "global::System.UInt16", "object": "global::System.Object", "string": "global::System.String", "void": "global::System.Void",
	"nint": "global::System.IntPtr", "nuint": "global::System.UIntPtr",
}

// Type is a type as the compiler gives it: fully qualified, with `?` for a nullable, whether it is a value type,
// and the named types inside a generic or an array. The zero Type is no type.
type Type struct {
	name        string
	nullable    bool
	isValueType bool
	inner       []string
}

// typeOf is the contract's type read as a Type; no type for none.
func typeOf(written *contract.Type) Type {
	if written == nil {
		return Type{}
	}
	var inner []string
	for _, part := range namedInside(written) {
		if !slices.Contains(inner, part) {
			inner = append(inner, part)
		}
	}

	return Type{name: written.Text, nullable: written.Nullable, isValueType: written.ValueType, inner: inner}
}

// namedInside is every named type inside the type's arguments and elements, at any depth, nullability aside.
func namedInside(written *contract.Type) []string {
	var named []string
	for _, part := range append(append([]*contract.Type{}, written.Args...), written.Members...) {
		if part.Kind == "named" {
			named = append(named, strings.TrimSuffix(part.Text, "?"))
		}
		named = append(named, namedInside(part)...)
	}

	return named
}

// Exists says whether there is a type.
func (t Type) Exists() bool {
	return t.name != ""
}

// Name is the type fully qualified, `?` kept: `global::System.String?`.
func (t Type) Name() string {
	return t.name
}

// IsNullable says whether the type is annotated nullable.
func (t Type) IsNullable() bool {
	return t.nullable
}

// IsValueType says whether the type is a struct, an enum or a primitive: a value copied, not an object referred to.
func (t Type) IsValueType() bool {
	return t.isValueType
}

// Inner is the named types inside a generic or an array, however deep.
func (t Type) Inner() []string {
	return t.inner
}

// NamedTypes is every named type this type is made of: itself, nullability aside, and the types inside it.
func (t Type) NamedTypes() []string {
	return append([]string{strings.TrimSuffix(t.name, "?")}, t.inner...)
}

// CallTarget is the method a call or construction reaches, as its original definition.
type CallTarget struct {
	target *contract.Target
}

// Exists says whether the compiler bound the call.
func (t CallTarget) Exists() bool {
	return t.target != nil
}

// Type is the id of the type that declares the method.
func (t CallTarget) Type() string {
	if t.target == nil {
		return ""
	}

	return t.target.Type
}

// Name is the method's name; `.ctor` for a constructor.
func (t CallTarget) Name() string {
	if t.target == nil {
		return ""
	}

	return t.target.Name
}

// Symbol is the method named as its declaration's symbol names it, so a call finds its declaration.
func (t CallTarget) Symbol() string {
	if t.target == nil {
		return ""
	}

	return t.target.Symbol
}

// Type is the type the node stands for: an expression's, as the compiler gives it; a parameter's or a caught
// exception's, as declared; a written type's, read from the declaration it types or from its own syntax.
func (n Node) Type() Type {
	if !n.Exists() {
		return Type{}
	}
	node := n.Node()
	switch {
	case n.IsExpression():
		return typeOf(node.Resolved)
	case n.Is("Parameter", "CatchDeclaration"):
		return typeOf(node.Declared)
	case n.IsTypeNode() && !n.Parent().IsTypeNode():
		return n.writtenType()
	}

	return Type{}
}

// DeclaredType is the type a declaration names through its written type: a property's, a field's, a method's
// return; no type for one that names none.
func (n Node) DeclaredType() Type {
	for _, child := range n.All() {
		if child.IsTypeNode() {
			return child.Type()
		}
	}

	return Type{}
}

// writtenType is the type a written type stands for: the one the declaration it types declares, else the one
// its own syntax names.
func (n Node) writtenType() Type {
	parent := n.Parent()
	switch {
	case parent.Is("PropertyDeclaration", "IndexerDeclaration", "EventDeclaration", "Parameter", "CatchDeclaration") && parent.Node().Declared != nil:
		return typeOf(parent.Node().Declared)
	case parent.Is("MethodDeclaration", "LocalFunctionStatement") && parent.Node().Returns != nil && n.Node().Field == "ReturnType":
		return typeOf(parent.Node().Returns)
	case parent.Is("VariableDeclaration"):
		for _, declarator := range parent.ChildrenIn("Variables") {
			if declarator.Node().Declared != nil {
				return typeOf(declarator.Node().Declared)
			}
		}
	}

	return n.syntacticType()
}

// syntacticType is the type a written type names by its syntax alone: the class its name refers to, a keyword's
// type, an array or nullable of one.
func (n Node) syntacticType() Type {
	switch {
	case n.Is("NullableType"):
		inner := n.At(0).syntacticType()
		if !inner.Exists() {
			return Type{}
		}
		inner.name += "?"
		inner.nullable = true

		return inner
	case n.Is("PredefinedType"):
		name, ok := keywords[n.Name()]
		if !ok {
			return Type{}
		}

		return Type{name: name, isValueType: name != "global::System.String" && name != "global::System.Object"}
	case n.Is("ArrayType"):
		element := n.At(0).syntacticType()
		if !element.Exists() {
			return Type{}
		}
		inner := []string{strings.TrimSuffix(element.name, "?")}

		return Type{name: element.name + "[]", inner: append(inner, element.inner...)}
	case n.Node().Refers != "":
		name := n.Node().Refers
		var inner []string
		if n.Is("GenericName") {
			var args []string
			for _, argument := range n.Child("TypeArgumentList").All() {
				written := argument.syntacticType()
				args = append(args, written.name)
				inner = append(inner, written.NamedTypes()...)
			}
			if open := strings.Index(name, "<"); open >= 0 {
				name = name[:open] + "<" + strings.Join(args, ", ") + ">"
			}
		}

		return Type{name: name, inner: inner}
	}

	return Type{}
}

// Parameters is the types of the method's parameters, in order, as its symbol spells them.
func (t CallTarget) Parameters() []string {
	symbol := t.Symbol()
	open := strings.LastIndex(symbol, "(")
	if open < 0 || !strings.HasSuffix(symbol, ")") || open+1 == len(symbol)-1 {
		return nil
	}
	var parameters []string
	depth, start := 0, open+1
	for at := open + 1; at < len(symbol)-1; at++ {
		switch symbol[at] {
		case '<', '(', '[':
			depth++
		case '>', ')', ']':
			depth--
		case ',':
			if depth == 0 {
				parameters = append(parameters, strings.TrimSpace(symbol[start:at]))
				start = at + 1
			}
		}
	}

	return append(parameters, strings.TrimSpace(symbol[start:len(symbol)-1]))
}
