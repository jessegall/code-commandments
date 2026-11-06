package python

import (
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func typeName(node engine.Match) string {
	if class, typed := (Node{Match: node}).ResolvedClass(); typed {
		return class
	}

	return node.Class()
}

func assignedName(statement engine.Match) string {
	targets := append(statement.ChildrenIn("target"), statement.ChildrenIn("targets")...)
	if len(targets) != 1 || targets[0].Kind() != "Name" {
		return ""
	}

	return targets[0].Name()
}

func receiver(node engine.Match) engine.Match {
	if node.Kind() == "Call" {
		return node.Child("func").Child("value")
	}

	return node.Child("value")
}

var protocols = []string{"typing.Protocol", "typing_extensions.Protocol"}

var abstractions = []string{"abc.ABC"}

func init() {
	engine.ReadAs(contract.Python, engine.Grammar{
		Arguments:     values,
		Receiver:      receiver,
		Declares:      engine.KindIn("NamedExpr"),
		Binds:         engine.KindIn("Assign", "AnnAssign", "NamedExpr"),
		Members:       members,
		Parameters:    parameters,
		Extends:       bases,
		Implements:    contracts,
		Annotations:   decorators,
		TypeKind:      classKind,
		TypeOf:        typeName,
		DeclaredName:  assignedName,
		ReturnType:    engine.InField("returns"),
		ParameterType: engine.InField("annotation"),
		Constructs:    constructed,
		Callers:       callers,
		DocTags:       docTags,
		BodyHash:      func(def engine.Match) string { return Node{Match: def}.BodyHash() },
		CodeWords:     func(node engine.Match) []string { return Node{Match: node}.CodeWords() },
		NodeHash: func(node engine.Match, normalize bool) string {
			return engine.SyntaxHash([]engine.Match{node}, hashing, normalize)
		},
		TestFile:    testFile,
		Implicit:    implicit,
		NamespaceOf: func(symbol string) string { return engine.Before(symbol, ".") },
		Continues:   func(branch engine.Match) bool { return Node{Match: branch}.IsElif() },
		Body:        engine.InFields("body"),
		Otherwise:   engine.InFields("orelse"),
		Condition:   condition,
		ElementName: elementName,
		Computes:    engine.KindIn("BinOp", "BoolOp", "Compare", "UnaryOp", "AugAssign"),
		ComparisonSubject: func(held engine.Match) engine.Match {
			subject, _ := Node{Match: held}.ComparisonSubject()

			return subject.Match
		},
	})

}

func testFile(file, path string) bool {
	name := filepath.Base(file)
	if name == "conftest.py" || engine.InFolderNamed(file, "tests", "test") {
		return true
	}

	return slices.ContainsFunc(pytestFilesFor(path), func(pattern string) bool {
		matched, _ := filepath.Match(pattern, name)

		return matched
	})
}

func docTags(definition engine.Match) []string {
	text, documented := Node{Match: definition}.Docstring()
	if !documented {
		return nil
	}

	var tags []string
	for _, line := range strings.Split(text, "\n") {
		line = strings.TrimSpace(line)

		switch {
		case strings.HasPrefix(line, ".. ") && strings.Contains(line, "::"):
			tags = append(tags, strings.TrimSpace(strings.TrimPrefix(line[:strings.Index(line, "::")], ".. ")))
		case strings.HasPrefix(line, ":") && strings.Count(line, ":") >= 2:
			name, _, _ := strings.Cut(strings.TrimPrefix(line, ":"), ":")
			tags = append(tags, strings.Fields(name + " x")[0])
		case section.MatchString(line):
			tags = append(tags, strings.ToLower(strings.TrimSuffix(line, ":")))
		}
	}

	return tags
}

func values(call engine.Match) []engine.Match {
	handed := call.ChildrenIn("args")
	for _, keyword := range call.ChildrenIn("keywords") {
		handed = append(handed, keyword.Child("value"))
	}

	return handed
}

func constructed(call engine.Match) engine.Match {
	if !call.Is(engine.Call) {
		return engine.Match{}
	}

	callee := call.Child("func")
	if resolved := callee.Node().Resolved; resolved != nil && resolved.Constructs != "" {
		return callee
	}

	symbol := callee.Refers()
	if symbol == "" {
		return engine.Match{}
	}

	if declarations := call.Codebase().Declarations(symbol); len(declarations) > 0 && declarations[0].Is(engine.TypeDeclaration) {
		return callee
	}

	if call.OutsideKind(symbol) == "class" {
		return callee
	}

	return engine.Match{}
}

func callers(def engine.Match) []engine.Match {
	var calls []engine.Match
	for _, call := range Of(def.Codebase()).CallersOf(Node{Match: def}) {
		calls = append(calls, call.Match)
	}

	return calls
}

func decorators(definition engine.Match) []engine.Match {
	var names []engine.Match
	for _, decorator := range definition.ChildrenIn("decorator_list") {
		if decorator.Is(engine.Call) {
			decorator = decorator.Child("func")
		}

		names = append(names, decorator)
	}

	return names
}

func classKind(class engine.Match) string {
	for _, base := range class.Extends() {
		if slices.ContainsFunc(protocols, base.Names) {
			return "protocol"
		}
	}

	if Of(class.Codebase()).IsEnumClass(Node{Match: class}) {
		return "enum"
	}

	return "class"
}

func parameters(function engine.Match) []engine.Match {
	var declared []engine.Match
	for _, parameter := range (Node{Match: function}).Parameters() {
		declared = append(declared, parameter.Match)
	}

	return declared
}

func members(class engine.Match) []engine.Match {
	var declared []engine.Match
	for at, statement := range class.ChildrenIn("body") {
		if _, docstring := statement.Child("value").Text(); statement.Kind() == "Pass" || at == 0 && statement.Kind() == "Expr" && docstring {
			continue
		}

		declared = append(declared, statement)
	}

	return declared
}

func bases(class engine.Match) []engine.Match {
	var named []engine.Match
	for _, base := range class.ChildrenIn("bases") {
		if base.Kind() == "Subscript" {
			base = base.Child("value")
		}

		named = append(named, base)
	}

	return named
}

func implicit(declaration engine.Match) bool {
	if declaration.Is(engine.Function) {
		return Node{Match: declaration}.IsDunder()
	}

	method := declaration.DeclaringFunction()
	if !method.Parent().Is(engine.TypeDeclaration) || method.IsAnnotated("staticmethod") {
		return false
	}

	declared := method.Parameters()

	return len(declared) > 0 && declared[0].Node() == declaration.Node()
}

func contracts(class engine.Match) []engine.Match {
	var honoured []engine.Match
	for _, base := range bases(class) {
		if isContract(class, base.Named()) {
			honoured = append(honoured, base)
		}
	}

	return honoured
}

func isContract(from engine.Match, symbol string) bool {
	for _, declaration := range from.Codebase().Declarations(symbol) {
		if declaration.Is(engine.TypeDeclaration) && (classKind(declaration) == "protocol" || isAbstract(declaration)) {
			return true
		}
	}

	return false
}

func isAbstract(class engine.Match) bool {
	for _, keyword := range class.ChildrenIn("keywords") {
		if keyword.Name() == "metaclass" && keyword.Child("value").Names("abc.ABCMeta") {
			return true
		}
	}

	return slices.ContainsFunc(class.Lineage(), func(ancestor string) bool { return slices.Contains(abstractions, ancestor) })
}

func condition(held engine.Match) engine.Match {
	if !slices.Contains([]string{"If", "IfExp", "While"}, held.Kind()) {
		return engine.Match{}
	}

	return held.Child("test")
}

func elementName(loop engine.Match) string {
	if loop.Kind() != "For" && loop.Kind() != "AsyncFor" {
		return ""
	}
	target := loop.Child("target")
	if target.Kind() != "Name" {
		return ""
	}

	return target.Name()
}
