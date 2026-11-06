package csharp

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

var keywords = map[string]string{
	"bool": "global::System.Boolean", "byte": "global::System.Byte", "sbyte": "global::System.SByte", "char": "global::System.Char",
	"decimal": "global::System.Decimal", "double": "global::System.Double", "float": "global::System.Single", "int": "global::System.Int32",
	"uint": "global::System.UInt32", "long": "global::System.Int64", "ulong": "global::System.UInt64", "short": "global::System.Int16",
	"ushort": "global::System.UInt16", "object": "global::System.Object", "string": "global::System.String", "void": "global::System.Void",
	"nint": "global::System.IntPtr", "nuint": "global::System.UIntPtr",
}

// Type is a type as the compiler gives it: fully qualified, with `?` for a nullable, whether it is a value type, and the named types inside a generic or an array.
type Type struct {
	name        string
	nullable    bool
	isValueType bool
	inner       []string
}

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

func namedInside(written *contract.Type) []string {
	var named []string
	for _, part := range append(append([]*contract.Type{}, written.Args...), written.Members...) {
		if part.Kind == "named" || part.Kind == "tuple" {
			named = append(named, strings.TrimSuffix(part.Text, "?"))
		}
		named = append(named, namedInside(part)...)
	}

	return named
}

func (t Type) Exists() bool {
	return t.name != ""
}

func (t Type) Name() string {
	return t.name
}

func (t Type) IsNullable() bool {
	return t.nullable
}

func (t Type) IsValueType() bool {
	return t.isValueType
}

func (t Type) Inner() []string {
	return t.inner
}

func (t Type) NamedTypes() []string {
	return append([]string{strings.TrimSuffix(t.name, "?")}, t.inner...)
}

// CallTarget is the method a call or construction reaches, as its original definition.
type CallTarget struct {
	target *contract.Target
}

func (t CallTarget) Exists() bool {
	return t.target != nil
}

func (t CallTarget) Type() string {
	if t.target == nil {
		return ""
	}

	return t.target.Type
}

func (t CallTarget) Name() string {
	if t.target == nil {
		return ""
	}

	return t.target.Name
}

func (t CallTarget) Symbol() string {
	if t.target == nil {
		return ""
	}

	return t.target.Symbol
}

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
		return n.writtenType().asWritten(n)
	}

	return Type{}
}

func (t Type) asWritten(written Node) Type {
	if written.Is("IdentifierName") && written.Name() == "var" {
		return t
	}
	if !t.isValueType {
		t.name = strings.TrimSuffix(t.name, "?")
	}
	t.nullable = written.Is("NullableType")

	return t
}

func (n Node) DeclaredType() Type {
	for _, child := range n.All() {
		if child.IsTypeNode() {
			return child.Type()
		}
	}

	return Type{}
}

func (n Node) writtenType() Type {
	written := n.declaringType()
	var inner []string
	for _, part := range written.inner {
		if !slices.Contains(inner, part) {
			inner = append(inner, part)
		}
	}
	written.inner = inner

	return written
}

func (n Node) declaringType() Type {
	parent := n.Parent()
	switch {
	case parent.Is("PropertyDeclaration", "IndexerDeclaration", "EventDeclaration", "Parameter", "CatchDeclaration", "ForEachStatement") && parent.Node().Declared != nil:
		return typeOf(parent.Node().Declared)
	case parent.Is("MethodDeclaration", "LocalFunctionStatement") && parent.Node().Returns != nil && n.Node().Field == "ReturnType":
		return typeOf(parent.Node().Returns)
	case parent.Is("DeclarationExpression", "DeclarationPattern") && parent.Child("Designation").Node().Declared != nil:
		return typeOf(parent.Child("Designation").Node().Declared)
	case parent.Is("DeclarationExpression") && parent.Node().Resolved != nil:
		return typeOf(parent.Node().Resolved)
	case parent.Is("VariableDeclaration"):
		for _, declarator := range parent.ChildrenIn("Variables") {
			if declarator.Node().Declared != nil {
				return typeOf(declarator.Node().Declared)
			}
		}
	}

	return n.syntacticType()
}

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
	case n.Is("TupleType"):
		var elements, inner []string
		for _, element := range n.ChildrenIn("Elements") {
			written := element.At(0).syntacticType()
			spelled := written.name
			if element.Name() != "" {
				spelled += " " + element.Name()
			}
			elements = append(elements, spelled)
			inner = append(inner, written.NamedTypes()...)
		}

		return Type{name: "(" + strings.Join(elements, ", ") + ")", isValueType: true, inner: inner}
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

		return Type{name: name, isValueType: n.namesAValue(), inner: inner}
	case n.Is("IdentifierName") && n.isTypeParameter():
		return Type{name: n.Name()}
	}

	return Type{}
}

func (n Node) isTypeParameter() bool {
	for _, around := range n.Ancestors() {
		for _, parameter := range around.Child("TypeParameterList").All() {
			if parameter.Name() == n.Name() {
				return true
			}
		}
	}

	return false
}

func (n Node) namesAValue() bool {
	symbol := n.Node().Refers
	if kind, declared := kindsOf(n.Codebase())[symbol]; declared {
		return slices.Contains([]string{"StructDeclaration", "RecordStructDeclaration", "EnumDeclaration"}, kind)
	}
	program, ok := n.Codebase().Program(contract.CSharp)
	if !ok {
		return false
	}
	for _, outside := range program.Symbols {
		if outside.Symbol == symbol {
			return outside.Kind == "struct" || outside.Kind == "enum"
		}
	}

	return false
}

func (t CallTarget) Parameters() []string {
	if t.target == nil {
		return nil
	}

	return t.target.Parameters
}
