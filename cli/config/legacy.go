package config

import (
	"errors"
	"fmt"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/walk"
)

const root = `JesseGall\CodeCommandments\`

const languageEnum = root + "Language"

func ReadPHP(path string) (Config, error) {
	if !walk.Exists(path) {
		return Config{}, nil
	}

	stream, err := php.Here().Stream(path)
	var missing bridge.ToolchainMissing
	if errors.As(err, &missing) {
		return Config{}, fmt.Errorf("this project's settings are in %s, which only php reads, and php is not on the PATH here: run `"+binary.Here()+" sync` where php is installed to turn it into config.json", path)
	}
	if err != nil {
		return Config{}, err
	}

	var config Config

	for _, call := range engine.Load(stream).WhereKind("call").Where(isMethodCall).Reject(insideConfigurator).Get() {
		if err := config.take(call); err != nil {
			return Config{}, err
		}
	}

	return config, nil
}

func insideConfigurator(call engine.Match) bool {
	for parent := call.Parent(); parent.Exists(); parent = parent.Parent() {
		if isFunction(parent) && methodOf(parent.Parent().Parent()) == "configure" {
			return true
		}
	}

	return false
}

// isMethodCall says whether a call calls a method on a value: `$config->paths(...)`.
func isMethodCall(call engine.Match) bool {
	callee := call.Child("callee")

	return callee.Kind() == "access" && callee.Node().Form == "instance"
}

// methodOf is the method a call calls, empty for any other call.
func methodOf(call engine.Match) string {
	if call.Kind() != "call" || !isMethodCall(call) {
		return ""
	}

	return call.Child("callee").Child("member").Name()
}

// isClassConstant says whether a value reads a constant of a class: `Language::Php`, `Thing::class`.
func isClassConstant(value engine.Match) bool {
	return value.Kind() == "access" && value.Node().Form == "static"
}

func (c *Config) take(call engine.Match) error {
	args := call.ChildrenIn("arguments")

	switch methodOf(call) {
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

func ruleOfClass(class string) Rule {
	if rule, shipped := RuleOf(class); shipped {
		return rule
	}

	return Rule{Kind: Detector, Name: shortName(class)}
}

func languageCase(value engine.Match) (source.Language, bool) {
	if !isClassConstant(value) || value.Child("subject").Node().Refers != languageEnum {
		return "", false
	}

	for _, language := range source.Languages {
		if strings.EqualFold(language.LegacyCase(), value.Child("member").Name()) {
			return language, true
		}
	}

	return "", false
}

func (c *Config) configure(args []engine.Match) error {
	for _, arg := range args {
		function := arg.Child("value")

		if !isFunction(function) {
			return unreadable(arg, "a function that takes the detector it tunes")
		}

		parameter := function.Child("parameters")
		declared := parameter.Child("declaredType").Node().Refers

		if declared == "" {
			return &cli.InvalidConfiguration{Reason: "a configure() closure must type its parameter with the detector it tunes."}
		}

		calls, err := chainOn(parameter.Child("binding").Name(), body(function))
		if err != nil {
			return err
		}

		c.Configurators = append(c.Configurators, Configurator{Target: ruleOfClass(declared), Calls: calls})
	}

	return nil
}

func body(function engine.Match) []engine.Match {
	if function.Node().Form == "arrow" {
		return []engine.Match{function.Child("body")}
	}

	var expressions []engine.Match

	for _, statement := range function.Child("body").ChildrenIn("statements") {
		if statement.Kind() == "return" {
			expressions = append(expressions, statement.ChildrenIn("values")...)
			continue
		}
		expressions = append(expressions, statement.Child("expression"))
	}

	return expressions
}

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
	if expression.Kind() == "reference" && expression.Name() == variable {
		return nil, nil
	}

	if methodOf(expression) == "" {
		return nil, unreadable(expression, "calls on the detector")
	}

	inner, err := chain(variable, expression.Child("callee").Child("subject"))
	if err != nil {
		return nil, err
	}

	call := Call{Method: methodOf(expression)}

	for _, arg := range expression.ChildrenIn("arguments") {
		value, err := valueOf(arg.Child("value"))
		if err != nil {
			return nil, err
		}

		call.Args = append(call.Args, Arg{Name: arg.Node().Label, Value: value})
	}

	return append(inner, call), nil
}

func valueOf(value engine.Match) (any, error) {
	switch value.Kind() {
	case "literal":
		switch value.Node().LiteralKind {
		case "string":
			text, _ := value.Text()

			return text, nil
		case "integer":
			text, _ := value.Text()
			if number, err := strconv.Atoi(text); err == nil {
				return number, nil
			}
		case "boolean":
			yes, _ := value.Node().Value.Bool()

			return yes, nil
		case "null":
			return nil, nil
		}
	case "unary":
		number, err := valueOf(value.Child("operand"))
		if whole, isInt := number.(int); isInt && err == nil && value.Node().Operator == "negate" {
			return -whole, nil
		}
	case "access":
		if isClassConstant(value) && value.Child("member").Name() == "class" {
			return value.Child("subject").Node().Refers, nil
		}
	case "collection":
		items := []any{}

		for _, entry := range value.ChildrenIn("entries") {
			each, err := valueOf(entry.Child("value"))
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
	return match.Kind() == "function" && (match.Node().Form == "arrow" || match.Node().Form == "closure")
}

func unreadable(at engine.Match, wanted string) error {
	return &cli.InvalidConfiguration{Reason: fmt.Sprintf("line %d is not something the tool can read without running it — it wants %s here.", at.Line(), wanted)}
}
