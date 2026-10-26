package csharp

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ReadAs(contract.CSharp, engine.Grammar{
		Arguments:   arguments,
		Receiver:    engine.InField("Expression"),
		Declares:    engine.KindIn("SingleVariableDesignation", "DeclarationPattern"),
		Binds:       engine.KindIn("LocalDeclarationStatement"),
		Members:     engine.InFields("Members"),
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
	})

}

// xmlTags are the elements a declaration's XML doc comments open, each once where it opens: `summary`, `param`,
// `exception`. Deprecation in C# is the [Obsolete] attribute, which hasAnnotation finds.
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

// arguments are the expressions a call, object creation or indexer is handed.
func arguments(match engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range (Node{Match: match}).Arguments() {
		handed = append(handed, argument.Match)
	}

	return handed
}

// basesWhere are the types a declaration's base list names that are interfaces, or that are not. C# writes a
// base class and its interfaces in one list, so what each names says which it is; an interface's bases are the
// interfaces it extends.
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

// namesInterface says whether the type the node names is declared an interface, in the scan or outside it.
func namesInterface(written engine.Match) bool {
	symbol := written.Refers()
	if declarations := written.Codebase().Declarations(symbol); len(declarations) > 0 {
		return declarations[0].TypeKind() == "interface"
	}

	return written.OutsideKind(symbol) == "interface"
}

// attributes are the names of the attributes a declaration carries, in every list.
func attributes(declaration engine.Match) []engine.Match {
	var names []engine.Match
	for _, list := range declaration.ChildrenIn("AttributeLists") {
		for _, attribute := range list.ChildrenIn("Attributes") {
			names = append(names, attribute.Child("Name"))
		}
	}

	return names
}

// parameters are the parameters a member, local function or lambda declares: in its parameter list, or the one a
// lambda written `x => …` takes.
func parameters(function engine.Match) []engine.Match {
	declared := function.ChildrenIn("Parameter")
	for _, parameter := range (Node{Match: function}).Parameters() {
		declared = append(declared, parameter.Match)
	}

	return declared
}

// body are the statements a construct runs as its own: the statements of the block it holds, or the one statement
// it holds where C# lets a body go unbraced. A construct that writes its own parts before its body, as a foreach
// writes the type and the name it binds, is read past by taking the first STATEMENT it holds rather than the
// first child.
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

// otherwise are the statements a branch runs when its condition does not hold: the ones its else clause holds. An
// `else if` is an `if` directly under that clause, which stands as the one statement.
func otherwise(branch engine.Match) []engine.Match {
	for _, child := range (Node{Match: branch}).Children() {
		if child.Is("ElseClause") {
			return body(child.Match)
		}
	}

	return nil
}

// condition is what a branch or a loop decides by: the expression it holds. A `do` writes its condition after its
// body and a `switch` holds its subject exactly as an `if` holds its condition, so the construct is named by kind
// and its condition found by role rather than by position.
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

// elementName is the name a foreach binds for each item: the `line` of `foreach (var line in slip.Lines)`.
// Roslyn writes that name as a token on the statement itself, so the loop answers its own name. A
// ForEachVariableStatement deconstructs its item into several names, and none of them holds the item.
func elementName(loop engine.Match) string {
	if loop.Kind() != "ForEachStatement" {
		return ""
	}

	return loop.Name()
}
