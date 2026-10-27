package rule

import (
	"fmt"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
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
		{"operator", `{"operator": "*"}`, "its operator is exactly it: `*` and `??` tell two binary expressions apart, `+=` an assignment, `!` a unary one, `extends` a heritage clause"},
		{"operatorIn", `{"operatorIn": ["*", "/"]}`, "its operator is one of them"},
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
		{"resolves", `{"resolves": "App\\Models\\User"}`, "what its name refers to, resolved, is that — or, for a value that names nothing (a variable, a property), the class it holds: that exact class, not one extending it"},
		{"resolvesLike", `{"resolvesLike": "App\\Models\\*"}`, "what its name refers to, resolved, matches the glob, or the class a value holds does: `{\"resolvesLike\": \"*Service\", \"of\": \"child:var\"}` is a receiver of any *Service"},
		{"declaredBy", `{"declaredBy": "App\\Models\\*"}`, "the member it reads is declared by a class the glob names: the class its receiver holds, followed up its own parents until one declares that member. It is whose field this is, which no name can say, and it is empty — so the step keeps nothing — where the receiver has no type in the scan or no declaration of it declares the member"},
		{"answeredBy", `{"answeredBy": false}`, "the type that declares what it reads already names the question it asks — a method of that type whose whole body returns the same thing, read either way round — or does NOT with `false`. It is what tells `use the name that exists` from `give it a name`, and a question asked by the very method that names it is never one of the places asking it"},
		{"declaredElsewhere", `{"declaredElsewhere": true}`, "what it names is declared in another FILE than the one it sits in, or is not with `false`. A field set from outside the file that declares it has gone around the methods its class wrote for changing itself, which is the question a rule about a type's own business asks; it holds nothing where no declaration in the scan declares what it names, so a rule says the owner too"},
		{"type", `{"type": "number"}`, "the type the expression has, as its language reads it: what a checker gave it, what its shape says, or the type its declaration writes — a primitive among them, where `resolves` answers a class alone"},
		{"typeLike", `{"typeLike": "Money*"}`, "the type the expression has matches the glob"},
		{"calls", `{"calls": {"name": "dd"}}`, "a call of its own — not one inside a function nested in it — passes the step"},
		{"argument", `{"argument": {"at": 0, "is": "literal"}}`, "the argument at that place passes the step; a negative `at` counts from the end, `-1` the last, and none there passes nothing. With no `at` at all, ANY argument passing the step is enough, which is how a rule asks about one passed by name, whose place is not what identifies it"},
		{"constructs", `{"constructs": "Date*"}`, "a construction, or on anything else one of its own, creates an instance of a type the glob names — in Python, a call of a class"},
		{"unused", `{"unused": true}`, "nothing refers to it: a function or type from outside itself, through the names and calls the scan resolves and its language's call graph; a parameter, by its name read in its function"},
		{"calledFrom", `{"calledFrom": "app/Http/**"}`, "something referring to it — a call, most often — sits in a file the glob matches, read as `file` reads one"},
	}},
	{"Where it sits", []Check{
		{"file", `{"file": "*Repository.php"}`, "its path from the project (the repository it lies in, else the folder judged), or any tail of it, matches the glob: `*` stays within a folder and `**` crosses them, as in .gitignore"},
		{"fileMatches", `{"fileMatches": "(^|/)pages/.*(Create|Edit)\\.vue$"}`, "its path from the project (the repository it lies in, else the folder judged) matches the regular expression, for paths a glob cannot say, such as alternatives"},
		{"namespaceLike", `{"namespaceLike": "App\\Http\\*"}`, "the namespace, package or module it is declared in matches the glob"},
		{"layer", `{"layer": "App\\Domain"}`, "it sits in that layer of the stack the project declares (backend, Python, C#)"},
		{"testCode", `{"testCode": true}`, "it is test code, as its bridge marks it or its language names test files"},
		{"topLevel", `{"topLevel": true}`, "it sits outside every function, closures too, and every type — code that runs when its file loads"},
		{"withinLoop", `{"withinLoop": true}`, "it sits inside a loop, or not with `false`"},
		{"position", `{"position": "first"}`, "it is the `first`, `last` or `only` one of its siblings (an only one is also first and last)"},
		{"descendant", `{"descendant": {"is": "return"}}`, "some node inside it passes the step"},
		{"inside", `{"inside": {"is": "catch"}}`, "some node above it, up to the file's root, passes the step"},
		{"all", `{"all": [{"name": "store"}, {"hasModifier": "public"}]}`, "it passes every step listed, as one node: a public `store`, where a step alone makes one check"},
		{"any", `{"any": [{"is": "loop"}, {"is": "branch"}]}`, "it passes one of the steps listed, so a rule says `either of these` without a second rule"},
		{"not", `{"not": {"hasModifier": "final"}}`, "it does NOT pass the step, so a `where` can hold what only a `reject` could say"},
		{"rule", `{"rule": "ActionClass"}`, "the rule of that name finds it too, so one rule is built from another instead of repeating its checks"},
		{"next", `{"next": {"is": "return"}}`, "the sibling right after it passes the step"},
		{"previous", `{"previous": {"is": "branch"}}`, "the sibling right before it passes the step"},
		{"nestedAtLeast", `{"nestedAtLeast": {"is": "loop", "count": 3}}`, "it and the nodes above it that pass the step number at least `count`"},
	}},
	{"How big it is", []Check{
		{"parameters", `{"parameters": {"atLeast": 5}}`, "a function declares that many parameters — a variadic, a defaulted one and Python's `self` count once each"},
		{"arguments", `{"arguments": {"atLeast": 4}}`, "a call or construction is handed that many arguments — a named or a spread one counts once"},
		{"lines", `{"lines": {"atLeast": 40}}`, "it spans that many lines, its first and last among them"},
		{"members", `{"members": {"is": "function", "atLeast": 20}}`, "a type declares that many members directly that pass the step (every member, with no check); with `\"inherited\": true`, the members its parents and traits declare in the scan count too"},
		{"complexity", `{"complexity": {"atLeast": 10}}`, "one plus every branch, loop and catch in it, as its language marks them, is that many — a switch is one branch, `&&` and `||` are not counted, and a function inside it is its own count"},
		{"count", `{"count": {"descendant": {"is": "return"}, "atLeast": 4}}`, "that many of its descendants pass the step — or `\"child\"`, with `\"field\"` to count one field's children; with `\"distinct\": \"child:var\"` (any target `of` takes), the counted nodes whose target is written the same count once: `atMost: 1` is one receiver"},
		{"ancestors", `{"ancestors": {"atMost": 2, "within": "App\\*"}}`, "its class's own line of parents runs that long, followed through the scan and the declarations outside it; `within` counts only the parents a glob names, so a project bounds the levels of its OWN and says nothing about the framework class underneath them"},
		{"duplicated", `{"duplicated": {"atLeast": 2}}`, "that many functions of the codebase, it among them, have its body, read from the tree, blind to spacing and comments"},
		{"callers", `{"callers": {"atLeast": 2}}`, "that many calls of the codebase reach the declaration, each resolved through the tree: `atMost: 0` is a declaration nothing calls"},
		{"repeated", `{"repeated": {"atLeast": 3, "files": 2}}`, "that many functions of the codebase hold a node reading as this one does, spread over at least `files` of them (one when it says none), read from the tree as `duplicated` reads a body: blind to what a local is called and which datum a literal holds. `\"exact\": true` keeps both, which a repeated string literal needs; `\"eitherWay\": true` reads a question and its negation as one (`==` with `!=`, `is` with `is not`, `in` with `not in`); `\"by\": \"owner\"` groups by the type that declares what the left side reads, so one question about the fields of two classes that share a name is two. Anything is counted, not only a comparison: a member access of a chain counts the reach `event.data.part(X).tasks` written again and again"},
	}},
	{"Its type", []Check{
		{"typeKind", `{"typeKind": "interface"}`, "a type declaration declares a `class`, `interface`, `enum`, `trait`, `record`, `struct` or `protocol`"},
		{"extends", `{"extends": "Controller"}`, "its class extends the type directly. Its class is the one a type declaration declares, else the one the node names (`X`, `X::class`), constructs (`new X`) or holds a value of (`$this->service`, a typed parameter)"},
		{"extendsAny", `{"extendsAny": "Exception"}`, "the type is anywhere in its class's chain of parents, followed through the scan and the declarations outside it the language knows; a glob (`*Service`, `[A-Z]*Repository`) matches a parent's whole name or its last part"},
		{"implements", `{"implements": "ShouldQueue"}`, "its class honours the contract: its own, its parents', and the contracts those extend"},
		{"uses", `{"uses": "AsAction"}`, "its class uses the trait: itself, through a parent, or through a trait it uses"},
		{"isA", `{"isA": "Illuminate\\Http\\Request"}`, "its class is the type, extends it or honours it, as PHP's `instanceof` reads it; a glob matches any of them"},
		{"hasAnnotation", `{"hasAnnotation": "Route"}`, "a declaration carries the attribute or decorator"},
		{"hasAttribute", `{"hasAttribute": "data-dusk"}`, "a template element writes the attribute, plain or bound: `data-dusk` or `:data-dusk`"},
		{"sibling", `{"sibling": "{folder}.php"}`, "a file the scan read matches the pattern, read from its file's folder: `{folder}.php` beside it, `../{folder}.php` one folder up, `{folder}` standing for the name of that folder"},
		{"returnType", `{"returnType": "?*"}`, "a function's written return type matches the pattern, spaces dropped, or the type it names resolves to; it reads the text, so `?*` finds PHP's `?int` and `*|null` finds `int|null`"},
		{"parameterType", `{"parameterType": "array"}`, "a parameter's written type matches the pattern, read as returnType reads one"},
	}},
	{"Its language's own", []Check{
		{"php", `{"php": "facadeCall"}`, "PHP's own check of the name holds — one of the checks listed below"},
		{"python", `{"python": "constructor"}`, "Python's own check of the name holds"},
		{"csharp", `{"csharp": "inherited"}`, "C#'s own check of the name holds"},
		{"typescript", `{"typescript": "optional"}`, "TypeScript's own check of the name holds"},
		{"vue", `{"vue": "component"}`, "a Vue component's own check of the name holds — its template's, and its script's TypeScript ones"},
	}},
}

// ownLanguages are the languages whose own checks a step may name, in the order the reference lists them.
var ownLanguages = []contract.Language{contract.PHP, contract.Python, contract.CSharp, contract.TypeScript, contract.Vue}

// Target is a node a step can judge in place of the one it is on, with "of".
type Target struct {
	Key   string
	Means string
}

// Takes says whether the target is the one an "of" value names: the key itself, or its prefix and a word after.
func (t Target) Takes(of string) bool {
	if prefix, pattern := strings.CutSuffix(t.Key, "<kind>"); pattern {
		return strings.HasPrefix(of, prefix) && len(of) > len(prefix)
	}

	if prefix, pattern := strings.CutSuffix(t.Key, "<field>"); pattern {
		return strings.HasPrefix(of, prefix) && len(of) > len(prefix)
	}

	return of == t.Key
}

// targetKeys are the keys of every target, as a rule may write them.
func targetKeys() []string {
	var keys []string
	for _, target := range Targets {
		keys = append(keys, target.Key)
	}

	return keys
}

// Targets are every node "of" can name.
var Targets = []Target{
	{"parent", "the node whose children hold it"},
	{"enclosingFunction", "the named function it sits in; a closure is passed over"},
	{"enclosingType", "the type declaration it sits in"},
	{"closest:<kind>", "the nearest node above it of the neutral kind, such as `closest:loop`"},
	{"root", "its file's root"},
	{"child:<field>", "its child filling the field, such as `child:class`"},
	{"declaredBy", "the declaration of the type that declares the member it reads, so every check composes on whose field it is: `{\"of\": \"declaredBy\", \"file\": \"app/Models/**\"}` is a field of a model, read from anywhere"},
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

	reference.WriteString("**Each language's own checks**, named under its key — a small set that grows on request, each name kept when the code behind it changes:\n\n| Check | Keeps the node when |\n|---|---|\n")

	for _, language := range ownLanguages {
		for _, predicate := range engine.PredicatesOf(language) {
			fmt.Fprintf(&reference, "| `{\"%s\": \"%s\"}` | %s |\n", language, predicate.Name, cell(predicate.Says))
		}
	}

	reference.WriteString("\n**What `\"of\"` can name**\n\n| Target | The step judges |\n|---|---|\n")

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
