package rule

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// The operators over types and declarations, read from the types the bridges resolved.
func init() {
	define(map[string]operator{
		"typeOf":        {operands: []valueType{node}, gives: anyValue, says: "a node's type: an expression's resolved type, a declaration's declared type, a function's return type; unknown where the compiler gave none"},
		"typeCategory":  {operands: []valueType{anyValue}, gives: text, says: "the neutral category of a type, the same in every language: text, integer, float, boolean, null, void, never, any, bytes, list, set, map, tuple, function, iterator, object or named; unknown for an unresolved or opaque type"},
		"isNullable":    {operands: []valueType{anyValue}, gives: truth, says: "whether a type admits absence: a nullable type, a union holding null, or one the compiler states is nullable; false only where the compiler says so"},
		"typeMembers":   {operands: []valueType{anyValue}, gives: list, says: "the alternatives of a union or intersection, nested ones flattened, a nullable type as its type and null; the type itself for any other"},
		"typeArguments": {operands: []valueType{anyValue}, gives: list, says: "the type arguments of a generic type, in order; empty for one with none"},
		"typeArgument":  {operands: []valueType{anyValue, number}, gives: anyValue, says: "the type argument at a position, counted from 0; no value past the end"},
		"elementType":   {operands: []valueType{anyValue}, gives: anyValue, says: "the type of what a list, set, array, iterator or map holds; no value for a type that holds nothing"},
		"keyType":       {operands: []valueType{anyValue}, gives: anyValue, says: "the type of a map's keys; no value for a type that is no map"},
		"isCollection":  {operands: []valueType{anyValue}, gives: truth, says: "whether a type's category is list, set, map, tuple or iterator"},
		"sameType":      {operands: []valueType{anyValue, anyValue}, gives: truth, says: "whether two types are the same by their shape, however each is spelled and wherever it came from"},
		"fieldsOf":      {operands: []valueType{anyValue}, gives: list, says: "the fields of an object type or a type declaration, each a record of name, type and optional"},
		"fieldType":     {operands: []valueType{anyValue, text}, gives: anyValue, says: "the type of the field named; no value when there is no such field"},
		"hasField":      {operands: []valueType{anyValue, text}, gives: truth, says: "whether a type has the field named"},
		"originRank":    {operands: []valueType{anyValue}, gives: number, says: "how much a type's origin is to be trusted: compiler 4, written 3, attribute 2, documented 1, inferred 0"},
		"typeKind":      {operands: []valueType{node}, gives: text, says: "what a type declaration declares: class, interface, enum, record, struct, trait, protocol, object or alias"},
		"memberGroup":   {operands: []valueType{node}, gives: text, says: "the group a member belongs to in a type's layout: enum-case, constant, static-field, field-public, field-protected, field-private, field-hooked, constructor or method"},
		"functionRole":  {operands: []valueType{node}, gives: list, says: "the roles a function plays: constructor, named-constructor, getter, setter, override, test, sole-statement"},
		"parameterOf":   {operands: []valueType{node}, gives: node, says: "the parameter of the called declaration an argument binds to, by its label or its position; unknown where the call's target is not resolved"},
		"outsideParameters": {operands: []valueType{node}, gives: list, says: "the parameters of the library method a call is bound to, in order, each a record of name, type, documented, returns (the documented return, else the declared one), optional, nullDefault and variadic; unknown where no library declares it"},
		"annotated":     {operands: []valueType{node, text}, gives: list, says: "the attributes, annotations or decorators on a declaration that name the symbol given, by what they resolve to"},
	}, map[string]computation{
		"typeOf": func(_ evaluation, operands []value) value { return onNode(operands, typeOf) },
		"typeCategory": typeComputation(func(e evaluation, resolved *contract.Type) value {
			category := typeCategory(resolved)
			if category == "" {
				return unknown
			}

			return known(category)
		}),
		"isNullable": typeComputation(func(_ evaluation, resolved *contract.Type) value { return truthValue(isNullable(resolved)) }),
		"typeMembers": typeComputation(func(_ evaluation, resolved *contract.Type) value {
			return known(typeValues(typeMembers(resolved)))
		}),
		"typeArguments": typeComputation(func(_ evaluation, resolved *contract.Type) value { return known(typeValues(resolved.Args)) }),
		"typeArgument": func(e evaluation, operands []value) value {
			resolved, isType := typeHeld(operands[0])
			position, isNumber := operands[1].held.(float64)
			if !isType || !operands[1].known || !isNumber {
				return unknown
			}
			if int(position) < 0 || int(position) >= len(resolved.Args) {
				return known(nil)
			}

			return known(resolved.Args[int(position)])
		},
		"elementType": typeComputation(func(_ evaluation, resolved *contract.Type) value {
			if resolved.Element == nil {
				return known(nil)
			}

			return known(resolved.Element)
		}),
		"keyType": typeComputation(func(_ evaluation, resolved *contract.Type) value {
			if resolved.Kind != "map" || len(resolved.Args) != 2 {
				return known(nil)
			}

			return known(resolved.Args[0])
		}),
		"isCollection": typeComputation(func(_ evaluation, resolved *contract.Type) value {
			category := typeCategory(resolved)
			if category == "" {
				return unknown
			}

			return truthValue(engine.TruthOf(slices.Contains([]string{"list", "set", "map", "tuple", "iterator"}, category)))
		}),
		"sameType": func(_ evaluation, operands []value) value {
			first, isFirst := typeHeld(operands[0])
			second, isSecond := typeHeld(operands[1])
			if !isFirst || !isSecond {
				return unknown
			}

			return truthValue(engine.TruthOf(sameType(first, second)))
		},
		"fieldsOf": func(e evaluation, operands []value) value { return fieldsOf(e, operands[0]) },
		"outsideParameters": func(_ evaluation, operands []value) value {
			return onNode(operands, func(call engine.Match) value { return outsideParameters(call) })
		},
		"fieldType": func(e evaluation, operands []value) value {
			_, field, read := fieldNamed(e, operands)
			if !read {
				return unknown
			}
			if field == nil {
				return known(nil)
			}

			return field["type"]
		},
		"hasField": func(e evaluation, operands []value) value {
			_, field, read := fieldNamed(e, operands)
			if !read {
				return unknown
			}

			return truthValue(engine.TruthOf(field != nil))
		},
		"originRank": typeComputation(func(_ evaluation, resolved *contract.Type) value {
			rank, ranked := map[string]float64{"compiler": 4, "written": 3, "attribute": 2, "documented": 1, "docblock": 1, "inferred": 0}[resolved.Origin]
			if !ranked {
				return unknown
			}

			return known(rank)
		}),
		"typeKind": func(_ evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value {
				if site.Node().Kind == "alias" {
					return known("alias")
				}
				if site.Node().Kind != "type" || site.Node().Form == "" {
					return unknown
				}

				return known(site.Node().Form)
			})
		},
		"memberGroup":  func(_ evaluation, operands []value) value { return onNode(operands, memberGroup) },
		"functionRole": func(_ evaluation, operands []value) value { return onNode(operands, functionRoles) },
		"parameterOf": func(e evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value { return parameterOf(e, site) })
		},
		"annotated": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			symbol, isText := operands[1].held.(string)
			if !operands[0].known || !isNode || !isText {
				return unknown
			}
			var found []value
			for _, annotation := range site.ChildrenIn("attributes") {
				if annotation.Node().Refers == symbol {
					found = append(found, known(annotation))
				} else if annotation.Node().Refers == "" && !annotation.Source().Knows("refers") {
					return unknown
				}
			}

			return known(found)
		},
	})
}

// typeHeld is a value read as a type: a type, or a node's type.
func typeHeld(v value) (*contract.Type, bool) {
	if !v.known {
		return nil, false
	}
	switch held := v.held.(type) {
	case *contract.Type:
		return held, held != nil
	case engine.Match:
		if !held.Exists() {
			return nil, false
		}
		resolved, isType := typeOf(held).held.(*contract.Type)

		return resolved, isType && resolved != nil
	}

	return nil, false
}

func typeComputation(answer func(evaluation, *contract.Type) value) computation {
	return func(e evaluation, operands []value) value {
		resolved, isType := typeHeld(operands[0])
		if !isType {
			return unknown
		}

		return answer(e, resolved)
	}
}

func typeValues(types []*contract.Type) []value {
	values := make([]value, 0, len(types))
	for _, each := range types {
		if each != nil {
			values = append(values, known(each))
		}
	}

	return values
}

// typeOf is a node's type: what an expression resolves to, or what a declaration or a function declares.
func typeOf(site engine.Match) value {
	declared := []string{"declaredType", "binding"}
	if site.Node().Kind == "function" {
		declared = []string{"returnType"}
	}
	if site.Node().Is("declaration") || site.Node().Kind == "parameter" {
		for _, role := range declared {
			if written := site.Child(role); written.Exists() && written.Typed() != nil {
				return known(written.Typed())
			}
		}
	}
	if resolved := site.Typed(); resolved != nil && site.Node().Kind != "function" {
		return known(resolved)
	}

	return unknown
}

// typeCategory is a type's neutral category: from its shape, else from the standard identity its name is listed under.
func typeCategory(resolved *contract.Type) string {
	switch resolved.Kind {
	case "array":
		return "list"
	case "map":
		return "map"
	case "tuple":
		return "tuple"
	case "function":
		return "function"
	case "object":
		return "object"
	case "opaque":
		return ""
	}
	for _, category := range []string{"text", "integer", "float", "boolean", "null", "void", "never", "any", "bytes", "list", "set", "map", "tuple", "function", "iterator"} {
		if slices.Contains(engine.PackageIdentitiesNamed("category-"+category), resolved.Name) {
			return category
		}
	}
	if resolved.Kind == "named" {
		return "named"
	}

	return ""
}

func isNullable(resolved *contract.Type) engine.Truth {
	if resolved.Nullable != nil && *resolved.Nullable {
		return engine.True
	}
	switch {
	case resolved.Kind == "nullable":
		return engine.True
	case resolved.Kind == "keyword" && slices.Contains([]string{"null", "mixed", "None", "undefined"}, resolved.Name):
		return engine.True
	case resolved.Kind == "union" && slices.ContainsFunc(resolved.Members, func(member *contract.Type) bool { return member != nil && isNullable(member) == engine.True }):
		return engine.True
	case resolved.Nullable != nil:
		return engine.False
	case resolved.Origin == "written" && resolved.Kind == "keyword":
		return engine.False
	case resolved.Kind == "union" && len(resolved.Members) > 0 && !slices.ContainsFunc(resolved.Members, func(member *contract.Type) bool { return member == nil || isNullable(member) != engine.False }):
		return engine.False
	}

	return engine.Unknown
}

func typeMembers(resolved *contract.Type) []*contract.Type {
	switch resolved.Kind {
	case "union", "intersection":
		var flat []*contract.Type
		for _, member := range resolved.Members {
			if member != nil && member.Kind == resolved.Kind {
				flat = append(flat, typeMembers(member)...)
			} else if member != nil && !slices.ContainsFunc(flat, func(held *contract.Type) bool { return sameType(held, member) }) {
				flat = append(flat, member)
			}
		}

		return flat
	case "nullable":
		return []*contract.Type{resolved.Element, {Kind: "keyword", Name: "null", Text: "null"}}
	case "tuple":
		return resolved.Members
	}

	return []*contract.Type{resolved}
}

// sameType compares two types by kind, name and the types they hold, leaving their spelling and origin aside.
func sameType(first, second *contract.Type) bool {
	if first == nil || second == nil {
		return first == second
	}
	if first.Kind != second.Kind || first.Name != second.Name || len(first.Args) != len(second.Args) || len(first.Members) != len(second.Members) {
		return false
	}
	for at := range first.Args {
		if !sameType(first.Args[at], second.Args[at]) {
			return false
		}
	}
	for _, member := range first.Members {
		if !slices.ContainsFunc(second.Members, func(other *contract.Type) bool { return sameType(member, other) }) {
			return false
		}
	}

	return (first.Element == nil) == (second.Element == nil) && (first.Element == nil || sameType(first.Element, second.Element))
}

// fieldsOf is the fields of an object type, or of the type a declaration declares.
func fieldsOf(e evaluation, v value) value {
	if site, isNode := nodeOf(v); isNode && site.Exists() && site.Node().Kind == "type" {
		var fields []value
		for _, member := range memberDeclarations(site) {
			holder := member.Parent()
			if holder.Node().Kind == "declarator" {
				holder = holder.Parent()
			}
			if kind := holder.Node().Kind; kind != "field" && kind != "parameter" {
				continue
			}
			fields = append(fields, known(record{"name": known(member.Node().Name), "type": typeOf(holder), "optional": known(holder.Child("default").Exists())}))
		}

		return known(fields)
	}
	resolved, isType := typeHeld(v)
	if !isType {
		return unknown
	}
	if resolved.Kind == "named" && e.codebase != nil {
		for _, declared := range e.codebase.Declarations(resolved.Name) {
			if declared.Node().Kind == "type" {
				return fieldsOf(e, known(declared))
			}
		}
	}
	if resolved.Kind != "object" {
		return unknown
	}
	fields := make([]value, 0, len(resolved.Fields))
	for _, each := range resolved.Fields {
		fields = append(fields, known(record{"name": known(each.Name), "type": known(each.Type), "optional": known(false)}))
	}

	return known(fields)
}

func fieldNamed(e evaluation, operands []value) (string, record, bool) {
	name, isText := operands[1].held.(string)
	fields, isList := listOf(fieldsOf(e, operands[0]))
	if !operands[1].known || !isText || !isList {
		return "", nil, false
	}
	for _, each := range fields {
		if held, isRecord := each.held.(record); isRecord && stringOf(held["name"]) == name {
			return name, held, true
		}
	}

	return name, nil, true
}

// memberGroup is the group a member's kind, modifiers and hooks put it in.
func memberGroup(site engine.Match) value {
	node := site.Node()
	has := func(modifier string) bool { return slices.Contains(node.Modifiers, modifier) }
	switch node.Kind {
	case "enum-case":
		return known("enum-case")
	case "constant":
		return known("constant")
	case "field", "parameter":
		switch {
		case has("static"):
			return known("static-field")
		case len(node.Linked("hooks")) > 0:
			return known("field-hooked")
		case has("private"):
			return known("field-private")
		case has("protected"):
			return known("field-protected")
		}

		return known("field-public")
	case "function":
		if node.Form == "constructor" {
			return known("constructor")
		}

		return known("method")
	}

	return unknown
}

// functionRoles is each role a function plays, read from its form, what it overrides and its body.
func functionRoles(site engine.Match) value {
	node := site.Node()
	var roles []value
	add := func(role string, holds bool) {
		if holds {
			roles = append(roles, known(role))
		}
	}
	owner := site.EnclosingType()
	returned := site.Child("returnType").Typed()
	add("constructor", node.Form == "constructor")
	add("named-constructor", node.Form != "constructor" && slices.Contains(node.Modifiers, "static") && owner.Exists() && returned != nil && returned.Name == owner.Node().Symbol)
	add("serialization-hook", slices.Contains(engine.PackageIdentitiesNamed("serialization-hook"), node.Name))
	add("getter", node.Form == "get")
	add("setter", node.Form == "set")
	add("override", site.IsOverride())
	add("test", site.Source().Purpose() != engine.Ordinary)
	add("sole-statement", len(site.Child("body").ChildrenIn("statements")) == 1)

	return known(roles)
}

// outsideParameters is the parameters of the library method a call is bound to, each a record of what the library
// declares about it, with what the method returns.
func outsideParameters(call engine.Match) value {
	member, declared := call.CalledOutsideMember()
	if !declared {
		return unknown
	}
	returned := member.Documented
	if returned == nil || returned.Kind == "opaque" {
		returned = member.Returns
	}
	records := make([]value, 0, len(member.Parameters))
	for _, parameter := range member.Parameters {
		records = append(records, known(record{
			"name":        known(parameter.Name),
			"type":        typeOrNone(parameter.Declared),
			"documented":  typeOrNone(parameter.Documented),
			"returns":     typeOrNone(returned),
			"optional":    known(slices.Contains(parameter.Flags, "optional")),
			"nullDefault": known(slices.Contains(parameter.Flags, "null-default")),
			"variadic":    known(slices.Contains(parameter.Flags, "variadic")),
		}))
	}

	return known(records)
}

// typeOrNone is a type as a value, no value where there is none.
func typeOrNone(held *contract.Type) value {
	if held == nil {
		return known(nil)
	}

	return known(held)
}

// parameterOf is the parameter of the called declaration an argument binds to.
func parameterOf(e evaluation, argument engine.Match) value {
	if argument.Node().Kind != "argument" {
		return known(nil)
	}
	call := argument.Parent()
	target := call.Node().Target
	if target == nil || e.codebase == nil {
		return unknown
	}
	var function engine.Match
	for _, declared := range e.codebase.Declarations(target.Symbol) {
		if declared.Node().Kind == "function" {
			function = declared
		}
	}
	if !function.Exists() {
		return unknown
	}
	parameters := function.ChildrenIn("parameters")
	if label := argument.Node().Label; label != "" {
		for _, parameter := range parameters {
			if parameter.Child("binding").Node().Name == label {
				return known(parameter)
			}
		}

		return known(nil)
	}
	if argument.Node().Spread != "" && argument.Node().Spread != "none" {
		return unknown
	}
	position := slices.IndexFunc(call.ChildrenIn("arguments"), func(each engine.Match) bool { return each.Node() == argument.Node() })
	for at, parameter := range parameters {
		if at == position || (parameter.Node().Collects != "" && parameter.Node().Collects != "none" && at <= position) {
			return known(parameter)
		}
	}

	return known(nil)
}
