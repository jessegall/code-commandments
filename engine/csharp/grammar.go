package csharp

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ReadAs(contract.CSharp, engine.Grammar{
		Arguments: arguments,
		Receiver:  receiver,
		Declares:  engine.KindIn("SingleVariableDesignation", "DeclarationPattern"),
		Binds:     engine.KindIn("LocalDeclarationStatement"),
		Members:   engine.InFields("Members"),
		DeclaredName: func(declaration engine.Match) string {
			return Node{Match: declaration}.DeclaredName()
		},
		Parameters:  parameters,
		Extends:     func(declaration engine.Match) []engine.Match { return basesWhere(declaration, false) },
		Implements:  func(declaration engine.Match) []engine.Match { return basesWhere(declaration, true) },
		Annotations: attributes,
		TypeOf:      func(node engine.Match) string { return Node{Match: node}.Type().Name() },
		TypeKind: engine.Kinds(map[string]string{"ClassDeclaration": "class", "InterfaceDeclaration": "interface",
			"EnumDeclaration": "enum", "RecordDeclaration": "record", "RecordStructDeclaration": "record", "StructDeclaration": "struct"}),
		ReturnType:    engine.InField("ReturnType"),
		ParameterType: engine.InField("Type"),
		Constructs:    engine.OfKind("ObjectCreationExpression", "Type"),
		DocTags:       xmlTags,
		BodyHash:      func(function engine.Match) string { return Node{Match: function}.BodyHash() },
		NodeHash: func(node engine.Match, normalize bool) string {
			return engine.SyntaxHash([]engine.Match{node}, hashing, normalize)
		},
		Implicit: engine.KindIn("ConstructorDeclaration", "DestructorDeclaration", "OperatorDeclaration",
			"ConversionOperatorDeclaration"),
		Inherited: func(member engine.Match) bool { return Node{Match: member}.IsInherited() },
		NamespaceOf: func(symbol string) string {
			member, _, _ := strings.Cut(strings.TrimPrefix(symbol, "global::"), "(")

			return engine.Before(member, ".")
		},
		AnnotationNames: func(name string) []string { return []string{name, name + "Attribute"} },
		Labels: func(name engine.Match) bool {
			return name.Parent().Kind() == "NameColon" || name.Parent().Kind() == "NameEquals"
		},
		Continues:         func(branch engine.Match) bool { return Node{Match: branch}.IsElseIf() },
		Body:              body,
		Otherwise:         otherwise,
		Condition:         condition,
		ComparisonSubject: func(held engine.Match) engine.Match { return Node{Match: held}.ComparisonSubject().Match },
		ElementName:       elementName,
		Computes:          computes,
		Documentable:      documentable,
		CodeWords:         func(node engine.Match) []string { return Node{Match: node}.CodeWords() },
	})

}

var sentTo = []string{"SimpleMemberAccessExpression", "PointerMemberAccessExpression", "ElementAccessExpression"}

func receiver(node engine.Match) engine.Match {
	if slices.Contains(sentTo, node.Kind()) {
		return node.Child("Expression")
	}

	if node.Kind() == "MemberBindingExpression" {
		return conditioned(node)
	}

	if node.Kind() != "InvocationExpression" && node.Kind() != "ObjectCreationExpression" {
		return engine.Match{}
	}

	return receiver(node.Child("Expression"))
}

func conditioned(binding engine.Match) engine.Match {
	for above := binding.Parent(); above.Exists(); above = above.Parent() {
		if above.Kind() == "ConditionalAccessExpression" {
			return above.Child("Expression")
		}
	}

	return engine.Match{}
}

func documentable(node engine.Match) bool {
	held := Node{Match: node}

	return !held.IsExpression() && held.hierarchyRole() != "other"
}

func xmlTags(declaration engine.Match) []string {
	var tags []string
	for _, comment := range declaration.DocComments() {
		for _, line := range strings.Split(comment, "\n") {
			line = strings.TrimSpace(strings.TrimLeft(strings.TrimSpace(line), "/"))
			if !strings.HasPrefix(line, "<") || strings.HasPrefix(line, "</") {
				continue
			}

			name := strings.FieldsFunc(line[1:], func(r rune) bool { return r == ' ' || r == '>' || r == '/' })
			if len(name) > 0 {
				tags = append(tags, name[0])
			}
		}
	}

	return tags
}

func arguments(match engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range (Node{Match: match}).Arguments() {
		handed = append(handed, argument.Match)
	}

	return handed
}

func basesWhere(declaration engine.Match, interfaces bool) []engine.Match {
	extendsAll := declaration.TypeKind() == "interface"
	if extendsAll && interfaces {
		return nil
	}

	var named []engine.Match
	for _, base := range declaration.Child("BaseList").ChildrenIn("Types") {
		if written := base.Child("Type"); extendsAll || namesInterface(written) == interfaces {
			named = append(named, written)
		}
	}

	return named
}

func namesInterface(written engine.Match) bool {
	symbol := written.Refers()
	if declarations := written.Codebase().Declarations(symbol); len(declarations) > 0 {
		return declarations[0].TypeKind() == "interface"
	}

	return written.OutsideKind(symbol) == "interface"
}

func attributes(declaration engine.Match) []engine.Match {
	var names []engine.Match
	for _, list := range declaration.ChildrenIn("AttributeLists") {
		for _, attribute := range list.ChildrenIn("Attributes") {
			names = append(names, attribute.Child("Name"))
		}
	}

	return names
}

func parameters(function engine.Match) []engine.Match {
	declared := function.ChildrenIn("Parameter")
	for _, parameter := range (Node{Match: function}).Parameters() {
		declared = append(declared, parameter.Match)
	}

	return declared
}

func body(held engine.Match) []engine.Match {
	for _, child := range (Node{Match: held}).Children() {
		if !child.IsStatement() {
			continue
		}
		if child.Is("Block") {
			return child.Match.Children()
		}

		return []engine.Match{child.Match}
	}

	return nil
}

func otherwise(branch engine.Match) []engine.Match {
	for _, child := range (Node{Match: branch}).Children() {
		if child.Is("ElseClause") {
			return body(child.Match)
		}
	}

	return nil
}

func condition(held engine.Match) engine.Match {
	node := Node{Match: held}
	if !node.Is("IfStatement", "WhileStatement", "DoStatement", "ConditionalExpression") {
		return engine.Match{}
	}
	for _, child := range node.All() {
		if child.IsExpression() {
			return child.Match
		}
	}

	return engine.Match{}
}

func elementName(loop engine.Match) string {
	if loop.Kind() != "ForEachStatement" {
		return ""
	}

	return loop.Name()
}

func computes(held engine.Match) bool {
	return Node{Match: held}.Is(
		"AddExpression", "SubtractExpression", "MultiplyExpression", "DivideExpression", "ModuloExpression",
		"LeftShiftExpression", "RightShiftExpression", "UnsignedRightShiftExpression",
		"BitwiseAndExpression", "BitwiseOrExpression", "ExclusiveOrExpression",
		"UnaryMinusExpression", "UnaryPlusExpression", "BitwiseNotExpression", "LogicalNotExpression",
		"PreIncrementExpression", "PreDecrementExpression", "PostIncrementExpression", "PostDecrementExpression",
		"SimpleAssignmentExpression", "AddAssignmentExpression", "SubtractAssignmentExpression",
		"MultiplyAssignmentExpression", "DivideAssignmentExpression", "ModuloAssignmentExpression",
		"AndAssignmentExpression", "OrAssignmentExpression", "ExclusiveOrAssignmentExpression",
		"LeftShiftAssignmentExpression", "RightShiftAssignmentExpression", "CoalesceAssignmentExpression",
	)
}
