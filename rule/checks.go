package rule

import (
	"fmt"
	"strings"
)

// Check is one check a step can make: the key that names it, a step making it, and the node it keeps.
type Check struct {
	Key     string
	Example string
	Keeps   string
}

// Group is a run of checks about one thing.
type Group struct {
	Title  string
	Checks []Check
}

// Checks are every check a step can make, grouped: the one list the reference, the schema and the suggestions
// for a misspelt key are read from.
var Checks = []Group{
	{"What it is and what it is called", []Check{
		{"is", `{"is": "loop"}`, "it answers the neutral kind"},
		{"kind", `{"kind": "Expr_StaticCall"}`, "it is the language's own kind"},
		{"name", `{"name": "select"}`, "its name — a call's or an access's is its name child's — is it"},
		{"nameIn", `{"nameIn": ["select", "statement"]}`, "its name is one of them"},
		{"nameLike", `{"nameLike": "get*"}`, "its name matches the glob, `*` any run of characters and `?` one"},
		{"nameMatches", `{"nameMatches": "^(get|set)[A-Z]"}`, "its name matches the regular expression"},
		{"nameCase", `{"nameCase": "snake"}`, "its name, less a leading `$` or `_`, is written `camel`, `pascal`, `snake`, `upper` or `kebab` case"},
		{"hasModifier", `{"hasModifier": "static"}`, "it carries the modifier"},
		{"hasFlag", `{"hasFlag": "byRef"}`, "it carries the flag its language's tree sets"},
	}},
	{"What it says", []Check{
		{"text", `{"text": "select * from users"}`, "a literal's text is exactly it"},
		{"textLike", `{"textLike": "*where id*"}`, "a literal's text matches the glob"},
		{"textMatches", `{"textMatches": "(?i)^select"}`, "a literal's text matches the regular expression"},
		{"commentLike", `{"commentLike": "*TODO*"}`, "a comment on it, or in the run of comments directly above it, matches the glob"},
		{"commentMatches", `{"commentMatches": "(?i)\\b(TODO|FIXME)\\b"}`, "a comment on it, or in the run of comments directly above it, matches the regular expression"},
		{"docTag", `{"docTag": "deprecated"}`, "its documentation carries the tag: `@tag` in PHPDoc and JSDoc, an XML element in C#, a Sphinx field or directive or a Google/NumPy section in a Python docstring"},
		{"documented", `{"documented": false}`, "it carries a doc comment, or not with `false`"},
	}},
	{"What it refers to", []Check{
		{"resolves", `{"resolves": "App\\Models\\User"}`, "what its name refers to, resolved, is that"},
		{"resolvesLike", `{"resolvesLike": "App\\Models\\*"}`, "what its name refers to, resolved, matches the glob"},
		{"calls", `{"calls": {"name": "dd"}}`, "a call of its own — not one inside a function nested in it — passes the step"},
		{"argument", `{"argument": {"at": 0, "is": "literal"}}`, "the argument at that place passes the step; a negative `at` counts from the end, `-1` the last, and none there passes nothing"},
		{"constructs", `{"constructs": "Date*"}`, "a construction, or on anything else one of its own, creates an instance of a type the glob names — in Python, a call of a class"},
		{"unused", `{"unused": true}`, "nothing refers to it: a function or type from outside itself, through the names and calls the scan resolves and its language's call graph; a parameter, by its name read in its function"},
		{"calledFrom", `{"calledFrom": "app/Http/*"}`, "something referring to it — a call, most often — sits in a file the glob matches"},
	}},
	{"Where it sits", []Check{
		{"file", `{"file": "*Repository.php"}`, "its file, or any tail of the path, matches the glob"},
		{"namespaceLike", `{"namespaceLike": "App\\Http\\*"}`, "the namespace, package or module it is declared in matches the glob"},
		{"layer", `{"layer": "App\\Domain"}`, "it sits in that layer of the stack the project declares (backend, Python, C#)"},
		{"testCode", `{"testCode": true}`, "it is test code, as its bridge marks it or its language names test files"},
		{"topLevel", `{"topLevel": true}`, "it sits outside every function, closures too, and every type — code that runs when its file loads"},
		{"withinLoop", `{"withinLoop": true}`, "it sits inside a loop, or not with `false`"},
		{"position", `{"position": "first"}`, "it is the `first`, `last` or `only` one of its siblings (an only one is also first and last)"},
		{"descendant", `{"descendant": {"is": "return"}}`, "some node inside it passes the step"},
		{"inside", `{"inside": {"is": "catch"}}`, "some node above it, up to the file's root, passes the step"},
		{"next", `{"next": {"is": "return"}}`, "the sibling right after it passes the step"},
		{"previous", `{"previous": {"is": "branch"}}`, "the sibling right before it passes the step"},
		{"nestedAtLeast", `{"nestedAtLeast": {"is": "loop", "count": 3}}`, "it and the nodes above it that pass the step number at least `count`"},
	}},
	{"How big it is", []Check{
		{"parameters", `{"parameters": {"atLeast": 5}}`, "a function declares that many parameters — a variadic, a defaulted one and Python's `self` count once each"},
		{"arguments", `{"arguments": {"atLeast": 4}}`, "a call or construction is handed that many arguments — a named or a spread one counts once"},
		{"lines", `{"lines": {"atLeast": 40}}`, "it spans that many lines, its first and last among them"},
		{"members", `{"members": {"is": "function", "atLeast": 20}}`, "a type declares that many members directly that pass the step (every member, with no check)"},
		{"complexity", `{"complexity": {"atLeast": 10}}`, "one plus every branch, loop and catch in it, as its language marks them, is that many — a switch is one branch, `&&` and `||` are not counted, and a function inside it is its own count"},
		{"count", `{"count": {"descendant": {"is": "return"}, "atLeast": 4}}`, "that many of its descendants pass the step — or `\"child\"`, with `\"field\"` to count one field's children"},
		{"duplicated", `{"duplicated": {"atLeast": 2}}`, "that many functions of the codebase, it among them, have its body, read from the tree, blind to spacing and comments"},
	}},
	{"Its type", []Check{
		{"typeKind", `{"typeKind": "interface"}`, "a type declaration declares a `class`, `interface`, `enum`, `trait`, `record`, `struct` or `protocol`"},
		{"extends", `{"extends": "Controller"}`, "a type declaration names the type among the ones it extends directly"},
		{"extendsAny", `{"extendsAny": "Exception"}`, "the type is anywhere in its chain of parents, followed through the scan and the declarations outside it the language knows"},
		{"implements", `{"implements": "ShouldQueue"}`, "it honours the contract: its own, its parents', and the contracts those extend"},
		{"hasAnnotation", `{"hasAnnotation": "Route"}`, "a declaration carries the attribute or decorator"},
		{"returnType", `{"returnType": "?*"}`, "a function's written return type matches the pattern, as written or as the type it names resolves"},
		{"parameterType", `{"parameterType": "array"}`, "a parameter's written type matches the pattern, as written or as the type it names resolves"},
	}},
}

// Target is a node a step can judge in place of the one it is on, with "of".
type Target struct {
	Key   string
	Means string
}

// Targets are every node "of" can name.
var Targets = []Target{
	{"parent", "the node whose children hold it"},
	{"enclosingFunction", "the named function it sits in; a closure is passed over"},
	{"enclosingType", "the type declaration it sits in"},
	{"closest:<kind>", "the nearest node above it of the neutral kind, such as `closest:loop`"},
	{"root", "its file's root"},
	{"child:<field>", "its child filling the field, such as `child:class`"},
}

// Reference is the checks as the writing-detectors skill shows them: a table per group, then the targets of "of".
func Reference() string {
	var reference strings.Builder

	for _, group := range Checks {
		fmt.Fprintf(&reference, "**%s**\n\n| Check | Keeps the node when |\n|---|---|\n", group.Title)

		for _, check := range group.Checks {
			fmt.Fprintf(&reference, "| `%s` | %s |\n", cell(check.Example), cell(check.Keeps))
		}

		reference.WriteString("\n")
	}

	reference.WriteString("**What `\"of\"` can name**\n\n| Target | The step judges |\n|---|---|\n")

	for _, target := range Targets {
		fmt.Fprintf(&reference, "| `%s` | %s |\n", target.Key, cell(target.Means))
	}

	return reference.String()
}

// cell is text a table cell can hold: a pipe would end the cell, so it is escaped.
func cell(text string) string {
	return strings.ReplaceAll(text, "|", `\|`)
}

// keys are every check's key, in the catalog's order.
func keys() []string {
	var all []string
	for _, group := range Checks {
		for _, check := range group.Checks {
			all = append(all, check.Key)
		}
	}

	return all
}
