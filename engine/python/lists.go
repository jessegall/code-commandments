package python

import (
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// protocols are the classes a class lists among its bases to declare itself a protocol.
var protocols = []string{"typing.Protocol", "typing_extensions.Protocol"}

// abstractions are the classes a class inherits from to be an abstract base: its contract, for whoever subclasses it.
var abstractions = []string{"abc.ABC"}

// enumerations are the classes an enumeration inherits from, however far up.
var enumerations = []string{"enum.Enum", "enum.IntEnum", "enum.StrEnum", "enum.Flag", "enum.IntFlag"}

func init() {
	engine.ListAs(contract.Python, engine.Lists{
		Arguments:     values,
		Members:       members,
		Parameters:    parameters,
		Extends:       bases,
		Implements:    contracts,
		Annotations:   decorators,
		TypeKind:      classKind,
		ReturnType:    engine.InField("returns"),
		ParameterType: engine.InField("annotation"),
		Constructs:    constructed,
		Callers:       callers,
		DocTags:       docTags,
		BodyHash:      func(def engine.Match) string { return Node{Match: def}.BodyHash() },
		TestFile:      testFile,
		Implicit:      implicit,
		Continues:     func(branch engine.Match) bool { return branch.HasFlag("elif") },
	})

	engine.Predicates(contract.Python,
		engine.Predicate{Name: "constructor", Says: "it is a class's __init__", Holds: func(m engine.Match) bool { return Node{Match: m}.IsConstructorDeclaration() }},
		engine.Predicate{Name: "evaluated", Says: "it is an expression the code evaluates, outside every type annotation", Holds: func(m engine.Match) bool { return Node{Match: m}.IsEvaluated() }},
		engine.Predicate{Name: "returnedValue", Says: "it is what a return statement returns", Holds: func(m engine.Match) bool { return Node{Match: m}.IsReturnedValue() }},
		engine.Predicate{Name: "typeNarrowingGuard", Says: "it is an outermost `and` of two or more isinstance checks", Holds: func(m engine.Match) bool { return Node{Match: m}.IsTypeNarrowingGuard() }},
		engine.Predicate{Name: "inNamedConstructor", Says: "it sits in a named constructor, where loose data becomes the class", Holds: func(m engine.Match) bool { return Node{Match: m}.IsWithinNamedConstructor() }},
	)
}

// testFile says whether a Python file is a test's, as pytest collects them: `test_*.py`, `*_test.py`, a
// conftest.py, or any file under a tests folder.
func testFile(file string) bool {
	name := filepath.Base(file)

	return strings.HasPrefix(name, "test_") || strings.HasSuffix(name, "_test.py") || name == "conftest.py" ||
		engine.InFolderNamed(file, "tests", "test")
}

// docTags are the tags a docstring carries: each Sphinx field (`:param x:`, `:deprecated:`) and directive
// (`.. deprecated::`) by its name, and each Google or NumPy section (`Args:`, `Raises:`) by its heading in lower case.
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

// values are the values a call is handed: its positional arguments, starred ones included, then its keyword
// arguments' values.
func values(call engine.Match) []engine.Match {
	handed := call.ChildrenIn("args")
	for _, keyword := range call.ChildrenIn("keywords") {
		handed = append(handed, keyword.Child("value"))
	}

	return handed
}

// constructed is the class a call creates an instance of: its callee, when mypy says calling it builds one, or
// when it resolves to a class.
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

// callers are the calls the program's call graph finds reaching a def.
func callers(def engine.Match) []engine.Match {
	var calls []engine.Match
	for _, call := range Of(def.Codebase()).CallersOf(Node{Match: def}) {
		calls = append(calls, call.Match)
	}

	return calls
}

// decorators are the names of the decorators a definition carries: a called one by what it calls.
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

// classKind is a protocol for a class listing Protocol among its bases, an enum for one inheriting from an
// enumeration, and a class otherwise.
func classKind(class engine.Match) string {
	for _, base := range class.Extends() {
		if slices.ContainsFunc(protocols, base.Names) {
			return "protocol"
		}
	}

	for _, ancestor := range class.Lineage() {
		if slices.Contains(enumerations, ancestor) {
			return "enum"
		}
	}

	return "class"
}

// parameters are the parameters a def or lambda declares.
func parameters(function engine.Match) []engine.Match {
	var declared []engine.Match
	for _, parameter := range (Node{Match: function}).Parameters() {
		declared = append(declared, parameter.Match)
	}

	return declared
}

// members are what a class body declares: its statements less a docstring and a bare pass, which declare nothing.
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

// bases are the classes a class inherits from, each by what it names: a generic base, Repo[User], names Repo.
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

// implicit says whether Python itself calls the def or binds the parameter: a dunder method such as __init__, or
// the first parameter of a method, self or cls, that a static method does not take.
func implicit(declaration engine.Match) bool {
	if declaration.Is(engine.Function) {
		name := declaration.Name()

		return len(name) > 4 && strings.HasPrefix(name, "__") && strings.HasSuffix(name, "__")
	}

	method := declaration.DeclaringFunction()
	if !method.Parent().Is(engine.TypeDeclaration) || method.IsAnnotated("staticmethod") {
		return false
	}

	declared := method.Parameters()

	return len(declared) > 0 && declared[0].Node() == declaration.Node()
}

// contracts are the bases a class honours as contracts rather than inherits from: a Protocol, or an abstract base
// class, one inheriting abc.ABC or made by the ABCMeta metaclass.
func contracts(class engine.Match) []engine.Match {
	var honoured []engine.Match
	for _, base := range bases(class) {
		if isContract(class, base.Named()) {
			honoured = append(honoured, base)
		}
	}

	return honoured
}

// isContract says whether the class the symbol names is a Protocol or an abstract base class.
func isContract(from engine.Match, symbol string) bool {
	for _, declaration := range from.Codebase().Declarations(symbol) {
		if declaration.Is(engine.TypeDeclaration) && (classKind(declaration) == "protocol" || isAbstract(declaration)) {
			return true
		}
	}

	return false
}

// isAbstract says whether the class is an abstract base: it inherits abc.ABC or its metaclass is ABCMeta.
func isAbstract(class engine.Match) bool {
	for _, keyword := range class.ChildrenIn("keywords") {
		if keyword.Name() == "metaclass" && keyword.Child("value").Names("abc.ABCMeta") {
			return true
		}
	}

	return slices.ContainsFunc(class.Lineage(), func(ancestor string) bool { return slices.Contains(abstractions, ancestor) })
}
