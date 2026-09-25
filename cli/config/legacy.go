package config

import (
	"fmt"
	"os"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// root is the namespace every shipped rule is declared under.
const root = `JesseGall\CodeCommandments\`

// languageEnum is the enum a config names a language by, as in `disable(Language::Python)`.
const languageEnum = root + "Language"

// ReadPHP reads a `.commandments/config.php` without running it: the PHP bridge parses it, and every call
// the config makes on the tool is read off the tree, the way the PHP tool's own config editor reads it.
func ReadPHP(path string) (Config, error) {
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
		return appendNames(args, &c.Detectors)
	case "package":
		return appendNames(args, &c.Packages)
	case "hook":
		return appendNames(args, &c.Hooks)
	case "agent":
		return appendNames(args, &c.Agents)
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

// appendNames appends each argument's short name: a project's own detector or package, a hook and an agent
// are named by their class's last segment, as config.json names them.
func appendNames(args []engine.Match, into *[]string) error {
	var classes []string
	if err := appendTexts(args, &classes); err != nil {
		return err
	}

	for _, class := range classes {
		*into = append(*into, shortName(class))
	}

	return nil
}

func shortName(class string) string {
	segments := strings.Split(class, `\`)

	return segments[len(segments)-1]
}

func (c *Config) disable(args []engine.Match) error {
	for _, arg := range args {
		if language, named := languageCase(arg.Child("value")); named {
			c.DisabledLanguages = append(c.DisabledLanguages, language)

			continue
		}

		var classes []string
		if err := appendTexts([]engine.Match{arg}, &classes); err != nil {
			return err
		}

		for _, class := range classes {
			c.Disabled = append(c.Disabled, ruleOfClass(class))
		}
	}

	return nil
}

// ruleOfClass is the rule a config.php names by class: a shipped one by its kind, engine and name, else a
// project's own detector by its short name.
func ruleOfClass(class string) Rule {
	if rule, shipped := RuleOf(class); shipped {
		return rule
	}

	return Rule{Kind: Detector, Name: shortName(class)}
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

		c.Configurators = append(c.Configurators, Configurator{Target: ruleOfClass(declared.Name), Calls: calls})
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
