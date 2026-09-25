package typescript

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// keywordKinds are the type keywords, by the kind the compiler gives them.
var keywordKinds = map[string]string{
	"StringKeyword": "string", "NumberKeyword": "number", "BooleanKeyword": "boolean", "VoidKeyword": "void",
	"UnknownKeyword": "unknown", "NeverKeyword": "never", "AnyKeyword": "any", "UndefinedKeyword": "undefined",
	"ObjectKeyword": "object", "SymbolKeyword": "symbol", "BigIntKeyword": "bigint",
}

// TypeOf is the type a type node writes, modelled as the PHP tool models it: a shape its grammar does not model is
// kept as written, and so is the nearest enclosing type position a part it cannot model sits in.
func TypeOf(n Node) TypeNode {
	if typed, ok := modelled(n); ok {
		return typed
	}

	return VerbatimType{Raw: strings.TrimSpace(n.Source())}
}

// modelled is the type the node writes, false when a part of it, up to the nearest type position, is not modelled.
func modelled(n Node) (TypeNode, bool) {
	if name, keyword := keywordKinds[n.Kind()]; keyword {
		return KeywordType{Name: name}, true
	}
	switch n.Kind() {
	case "LiteralType":
		return literalType(Node{n.Child("literal")})
	case "ThisType":
		return NamedType{Name: "this"}, true
	case "TypeReference":
		return NamedType{Name: n.Child("typeName").Written(), Arguments: typesOf(n.ChildrenIn("typeArguments"))}, true
	case "ArrayType":
		element, ok := modelled(Node{n.Child("elementType")})

		return ArrayType{Element: element}, ok
	case "IndexedAccessType":
		object, ok := modelled(Node{n.Child("objectType")})

		return IndexedAccessType{Object: object, Index: TypeOf(Node{n.Child("indexType")})}, ok
	case "ParenthesizedType":
		return ParenType{Inner: TypeOf(Node{n.Child("type")})}, true
	case "TupleType":
		return TupleType{Elements: typesOf(n.ChildrenIn("elements"))}, true
	case "UnionType", "IntersectionType":
		return composite(n)
	case "FunctionType":
		params, ok := paramsOf(n.ChildrenIn("parameters"))

		return FunctionType{Params: params, Returns: TypeOf(Node{n.Child("type")})}, ok
	case "TypeLiteral":
		return objectType(n)
	case "TypeQuery":
		return TypeofType{Target: n.Child("exprName").Written()}, true
	}

	return nil, false
}

func literalType(literal Node) (TypeNode, bool) {
	switch literal.Kind() {
	case "NullKeyword":
		return KeywordType{Name: "null"}, true
	case "TrueKeyword", "FalseKeyword", "StringLiteral", "NumericLiteral", "PrefixUnaryExpression":
		return LiteralType{Raw: literal.Source()}, true
	}

	return nil, false
}

func typesOf(nodes []engine.Match) []TypeNode {
	types := make([]TypeNode, 0, len(nodes))
	for _, node := range nodes {
		types = append(types, TypeOf(Node{node}))
	}

	return types
}

func composite(n Node) (TypeNode, bool) {
	operator := "|"
	if n.Kind() == "IntersectionType" {
		operator = "&"
	}
	var members []TypeNode
	for _, member := range n.ChildrenIn("types") {
		typed, ok := modelled(Node{member})
		if !ok {
			return nil, false
		}
		members = append(members, typed)
	}

	return CompositeType{Operator: operator, Members: members}, true
}

// objectType is an object type whose every member is a named property or method; any other member leaves it
// unmodelled.
func objectType(n Node) (TypeNode, bool) {
	var members []Member
	for _, member := range n.ChildrenIn("members") {
		read, ok := memberOf(Node{member})
		if !ok {
			return nil, false
		}
		members = append(members, read)
	}

	return ObjectType{Members: members}, true
}

// memberOf is a property or method signature as the PHP tool's grammar reads one; false for any other member.
func memberOf(member Node) (Member, bool) {
	name := member.Child("name")
	if name.Kind() != "Identifier" && name.Kind() != "StringLiteral" {
		return Member{}, false
	}
	optional := member.HasFlag("optional")
	switch member.Kind() {
	case "PropertySignature":
		if !member.Child("type").Exists() {
			return Member{}, false
		}

		return Member{Name: name.Written(), Optional: optional, Property: TypeOf(Node{member.Child("type")})}, true
	case "MethodSignature":
		params, ok := paramsOf(member.ChildrenIn("parameters"))
		if !ok {
			return Member{}, false
		}
		var returns TypeNode = KeywordType{Name: "void"}
		if member.Child("type").Exists() {
			returns = TypeOf(Node{member.Child("type")})
		}

		return Member{Name: name.Written(), Optional: optional, Params: params, Returns: returns}, true
	}

	return Member{}, false
}

// paramsOf is a parameter list as the PHP tool's grammar prints it: modifiers and defaults dropped, a destructured
// parameter printed as its pattern.
func paramsOf(parameters []engine.Match) ([]Param, bool) {
	params := make([]Param, 0, len(parameters))
	for _, parameter := range parameters {
		param := Node{parameter}
		name := param.Child("name")
		printed := name.Written()
		switch name.Kind() {
		case "Identifier":
		case "ObjectBindingPattern", "ArrayBindingPattern":
			printed, _ = Pattern(Node{name})
		default:
			return nil, false
		}
		read := Param{Name: printed, Optional: param.HasFlag("optional"), Rest: param.HasFlag("variadic")}
		if param.Child("type").Exists() {
			read.Type = TypeOf(Node{param.Child("type")})
		}
		params = append(params, read)
	}

	return params, true
}

// Pattern is a destructuring pattern printed as the PHP tool prints it, `{ a, b: c, ...rest }` or `[a, , b]`, and
// the names it binds, each local then the rest.
func Pattern(n Node) (string, []string) {
	switch n.Kind() {
	case "ObjectBindingPattern":
		var locals, parts []string
		keys := map[string]string{}
		rest := ""
		for _, element := range n.ChildrenIn("elements") {
			binding := Node{element}
			local := binding.Name()
			if binding.HasFlag("spread") {
				rest = local

				continue
			}
			key := local
			if binding.Child("propertyName").Exists() {
				key = binding.Child("propertyName").Written()
			}
			if _, seen := keys[local]; !seen {
				locals = append(locals, local)
			}
			keys[local] = key
		}
		for _, local := range locals {
			if keys[local] == local {
				parts = append(parts, local)
			} else {
				parts = append(parts, keys[local]+": "+local)
			}
		}
		names := append([]string{}, locals...)
		if rest != "" {
			parts = append(parts, "..."+rest)
			names = append(names, rest)
		}

		return "{ " + strings.Join(parts, ", ") + " }", names
	case "ArrayBindingPattern":
		var elements, names []string
		for _, element := range n.ChildrenIn("elements") {
			binding := Node{element}
			if binding.Kind() == "OmittedExpression" {
				elements = append(elements, "")

				continue
			}
			name := binding.Name()
			elements = append(elements, name)
			names = append(names, name)
		}

		return "[" + strings.Join(elements, ", ") + "]", names
	}
	name := n.Written()

	return name, []string{name}
}
