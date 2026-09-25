// Package config reads a project's `.commandments/config.php` without running it: the PHP bridge parses
// it, and every call the config makes on the tool (paths, exclude, disable, detector, package, configure)
// is read off the tree, the way the PHP tool's own config editor reads it.
package config

import (
	"fmt"
	"os"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// root is the namespace every shipped rule is declared under.
const root = `JesseGall\CodeCommandments\`

// languageEnum is the enum a config names a language by, as in `disable(Language::Python)`.
const languageEnum = root + "Language"

// Config is what a project declares about how it is judged.
type Config struct {
	// Paths are the source roots judge scans when it is given no path.
	Paths []string
	// Excluded are paths never reported on nor rewritten, though still parsed.
	Excluded []string
	// Disabled are the rules turned off, by class: a detector, a sin, or a whole skill.
	Disabled []string
	// DisabledLanguages are the languages the project does not write.
	DisabledLanguages []source.Language
	// Detectors are the project's own detectors, by class.
	Detectors []string
	// Packages are the packages whose exemptions the project registers, by class.
	Packages []string
	// Configurators tune shipped detectors.
	Configurators []Configurator
}

// Configurator is one `configure(fn (SomeDetector $d) => $d->a(...)->b(...))`: the detector class it tunes
// and the calls it makes on it, in order.
type Configurator struct {
	Target string
	Calls  []Call
}

// Call is one method called with its arguments.
type Call struct {
	Method string
	Args   []Arg
}

// Arg is one argument, named when it was passed by name. Its value is a string, an int, a bool, nil, or a
// []any of those.
type Arg struct {
	Name  string
	Value any
}

// Load reads the config of the project at dir; a project with no config file has the empty config.
func Load(dir string) (Config, error) {
	return Read(workspace.Config(dir))
}

// Read reads the config file at path.
func Read(path string) (Config, error) {
	if _, err := os.Stat(path); err != nil {
		return Config{}, nil
	}

	stream, err := php.Here().Stream(path)
	if err != nil {
		return Config{}, err
	}

	var config Config

	for _, call := range engine.Load(stream).WhereKind("Expr_MethodCall").Reject(insideConfigurator).Get() {
		if err := config.take(call); err != nil {
			return Config{}, err
		}
	}

	return config, nil
}

// insideConfigurator says whether a call is made inside a `configure(...)` closure: it tunes a detector,
// it does not configure the project.
func insideConfigurator(call engine.Match) bool {
	for parent := call.Parent(); parent.Exists(); parent = parent.Parent() {
		if isFunction(parent) && parent.Parent().Parent().Child("name").Name() == "configure" {
			return true
		}
	}

	return false
}

func (c *Config) take(call engine.Match) error {
	args := call.ChildrenIn("args")

	switch call.Child("name").Name() {
	case "paths":
		return appendTexts(args, &c.Paths)
	case "exclude":
		return appendTexts(args, &c.Excluded)
	case "detector":
		return appendTexts(args, &c.Detectors)
	case "package":
		return appendTexts(args, &c.Packages)
	case "disable":
		return c.disable(args)
	case "configure":
		return c.configure(args)
	default:
		return nil
	}
}

// appendTexts appends each argument, a string or a `::class`, to into.
func appendTexts(args []engine.Match, into *[]string) error {
	for _, arg := range args {
		value, err := valueOf(arg.Child("value"))
		if err != nil {
			return err
		}

		text, isText := value.(string)
		if !isText {
			return unreadable(arg, "a class or a path")
		}

		*into = append(*into, text)
	}

	return nil
}

func (c *Config) disable(args []engine.Match) error {
	for _, arg := range args {
		if language, named := languageCase(arg.Child("value")); named {
			c.DisabledLanguages = append(c.DisabledLanguages, language)

			continue
		}

		if err := appendTexts([]engine.Match{arg}, &c.Disabled); err != nil {
			return err
		}
	}

	return nil
}

// languageCase is the language a `Language::Case` argument names.
func languageCase(value engine.Match) (source.Language, bool) {
	if value.Kind() != "Expr_ClassConstFetch" || value.Child("class").Node().Refers != languageEnum {
		return "", false
	}

	for _, language := range source.Languages {
		if strings.EqualFold(caseOf(language), value.Child("name").Name()) {
			return language, true
		}
	}

	return "", false
}

// caseOf is the enum case a language is named by.
func caseOf(language source.Language) string {
	switch language {
	case source.TypeScript:
		return "TypeScript"
	case source.CSharp:
		return "CSharp"
	default:
		return language.Label()
	}
}

func (c *Config) configure(args []engine.Match) error {
	for _, arg := range args {
		function := arg.Child("value")

		if !isFunction(function) {
			return unreadable(arg, "a function that takes the detector it tunes")
		}

		parameter := function.Child("params")
		declared := parameter.Node().Declared

		if declared == nil || declared.Name == "" {
			return &cli.InvalidConfiguration{Reason: "a configure() closure must type its parameter with the detector it tunes."}
		}

		calls, err := chainOn(parameter.Name(), body(function))
		if err != nil {
			return err
		}

		c.Configurators = append(c.Configurators, Configurator{Target: declared.Name, Calls: calls})
	}

	return nil
}

// body are the expressions a configurator evaluates: an arrow function's one, or each statement and return
// of a closure.
func body(function engine.Match) []engine.Match {
	if function.Kind() == "Expr_ArrowFunction" {
		return []engine.Match{function.Child("expr")}
	}

	var expressions []engine.Match

	for _, statement := range function.ChildrenIn("stmts") {
		expressions = append(expressions, statement.Child("expr"))
	}

	return expressions
}

// chainOn is every call the expressions make on the variable, innermost first.
func chainOn(variable string, expressions []engine.Match) ([]Call, error) {
	var calls []Call

	for _, expression := range expressions {
		chain, err := chain(variable, expression)
		if err != nil {
			return nil, err
		}

		calls = append(calls, chain...)
	}

	return calls, nil
}

func chain(variable string, expression engine.Match) ([]Call, error) {
	if expression.Kind() == "Expr_Variable" && expression.Name() == variable {
		return nil, nil
	}

	if expression.Kind() != "Expr_MethodCall" {
		return nil, unreadable(expression, "calls on the detector")
	}

	inner, err := chain(variable, expression.Child("var"))
	if err != nil {
		return nil, err
	}

	call := Call{Method: expression.Child("name").Name()}

	for _, arg := range expression.ChildrenIn("args") {
		value, err := valueOf(arg.Child("value"))
		if err != nil {
			return nil, err
		}

		name := ""
		if slices.Contains(arg.Node().Flags, "named") {
			name = arg.Child("name").Name()
		}

		call.Args = append(call.Args, Arg{Name: name, Value: value})
	}

	return append(inner, call), nil
}

// valueOf reads a literal: a string, a number, true, false, null, an array of those, or a `::class`.
func valueOf(value engine.Match) (any, error) {
	switch value.Kind() {
	case "Scalar_String":
		text, _ := value.Text()

		return text, nil
	case "Scalar_Int", "Scalar_LNumber":
		text, _ := value.Text()

		if number, err := strconv.Atoi(strings.ReplaceAll(text, "_", "")); err == nil {
			return number, nil
		}
	case "Expr_UnaryMinus":
		number, err := valueOf(value.Child("expr"))
		if whole, isInt := number.(int); isInt && err == nil {
			return -whole, nil
		}
	case "Expr_ConstFetch":
		switch strings.ToLower(value.Child("name").Name()) {
		case "true":
			return true, nil
		case "false":
			return false, nil
		case "null":
			return nil, nil
		}
	case "Expr_ClassConstFetch":
		if value.Child("name").Name() == "class" {
			return value.Child("class").Node().Refers, nil
		}
	case "Expr_Array":
		items := []any{}

		for _, item := range value.ChildrenIn("items") {
			each, err := valueOf(item.Child("value"))
			if err != nil {
				return nil, err
			}

			items = append(items, each)
		}

		return items, nil
	}

	return nil, unreadable(value, "a literal")
}

func isFunction(match engine.Match) bool {
	return match.Kind() == "Expr_ArrowFunction" || match.Kind() == "Expr_Closure"
}

func unreadable(at engine.Match, wanted string) error {
	return &cli.InvalidConfiguration{Reason: fmt.Sprintf("line %d is not something the tool can read without running it — it wants %s here.", at.Line(), wanted)}
}

// Rule is a shipped rule as a config names it: its kind, the engine it belongs to, and its name.
type Rule struct {
	Kind   Kind
	Engine catalog.Engine
	Name   string
}

// Kind is which catalog a rule is in.
type Kind string

// The kinds of rule a config can name.
const (
	Skill    Kind = "Skills"
	Sin      Kind = "Sins"
	Detector Kind = "Detectors"
)

// RuleOf reads a shipped rule from its class; false when the class is none of the tool's rules.
func RuleOf(class string) (Rule, bool) {
	path, shipped := strings.CutPrefix(strings.TrimPrefix(class, `\`), root)
	segments := strings.Split(path, `\`)

	if !shipped || len(segments) < 3 {
		return Rule{}, false
	}

	kind := Kind(segments[0])
	if kind != Skill && kind != Sin && kind != Detector {
		return Rule{}, false
	}

	engine, known := engineOf(segments[1 : len(segments)-1])

	return Rule{kind, engine, segments[len(segments)-1]}, known
}

// engineOf reads the engine from a rule's namespace, past any package folder.
func engineOf(namespace []string) (catalog.Engine, bool) {
	switch {
	case namespace[0] == "Frontend" && len(namespace) > 1 && namespace[1] == "TypeScript":
		return catalog.TypeScript, true
	case namespace[0] == "Backend":
		return catalog.Backend, true
	case namespace[0] == "Frontend":
		return catalog.Frontend, true
	case namespace[0] == "TypeScript":
		return catalog.TypeScript, true
	case namespace[0] == "Python":
		return catalog.Python, true
	case namespace[0] == "CSharp":
		return catalog.CSharp, true
	default:
		return "", false
	}
}

// Disables says whether the config turns the rule off.
func (c Config) Disables(rule Rule) bool {
	for _, class := range c.Disabled {
		if named, shipped := RuleOf(class); shipped && named == rule {
			return true
		}
	}

	return false
}

// Writes says whether the project writes the language.
func (c Config) Writes(language source.Language) bool {
	return !slices.Contains(c.DisabledLanguages, language)
}
