// Package config reads what a project declares about how it is judged: its `.commandments/config.json`, or,
// until sync migrates it, the `.commandments/config.php` the PHP tool executed.
package config

import (
	"errors"
	"os"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Config is what a project declares about how it is judged.
type Config struct {
	// Paths are the source roots judge scans when it is given no path.
	Paths []string
	// Excluded are paths never reported on nor rewritten, though still parsed.
	Excluded []string
	// Disabled are the rules turned off: a detector, a sin, or a whole skill.
	Disabled []Rule
	// DisabledLanguages are the languages the project does not write.
	DisabledLanguages []source.Language
	// Detectors are the project's own detectors, by name.
	Detectors []string
	// Packages are the packages whose exemptions the project registers, by name.
	Packages []string
	// Hooks are the hooks the project turns on beyond the ones every project runs.
	Hooks []string
	// Agents are the agents a config from the PHP tool turned on by name; the tool ships none beyond the ones every
	// project runs, so sync skips and names each.
	Agents []string
	// Configurators tune shipped detectors.
	Configurators []Configurator
}

// Load reads the config of the project at dir: its config.json, else its config.php until sync migrates
// it; a project with neither has the empty config.
func Load(dir string) (Config, error) {
	if _, err := os.Stat(workspace.JSONConfig(dir)); !errors.Is(err, os.ErrNotExist) {
		return ReadJSON(workspace.JSONConfig(dir))
	}

	return ReadPHP(workspace.Config(dir))
}

// Configurator tunes one detector: the detector and the calls it makes on it, in order.
type Configurator struct {
	Target Rule
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

// Rule is a rule as a config names it: its kind, the engine it belongs to, and its name. A project's own
// rule belongs to no engine.
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
	Hook     Kind = "Hooks"
	Agent    Kind = "Agents"
)

// RuleOf reads a shipped rule from its class; false when the class is none of the tool's rules. A hook or an
// agent the tool ships is named by its short name alone.
func RuleOf(class string) (Rule, bool) {
	path, shipped := strings.CutPrefix(strings.TrimPrefix(class, `\`), root)
	segments := strings.Split(path, `\`)

	if shipped && (segments[0] == string(Hook) || segments[0] == string(Agent)) && len(segments) > 1 {
		return Rule{Kind(segments[0]), "", segments[len(segments)-1]}, true
	}

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

// ID is how config.json names the rule: `engine/Name`, or the bare name of a project's own.
func (r Rule) ID() string {
	if r.Engine == "" {
		return r.Name
	}

	return string(r.Engine) + "/" + r.Name
}

// Shipped says whether the rule is one of the tool's own.
func (r Rule) Shipped() bool {
	return r.Engine != ""
}

// RuleNamed reads a rule of the kind from its config.json id.
func RuleNamed(kind Kind, id string) Rule {
	engine, name, shipped := strings.Cut(id, "/")
	if !shipped {
		return Rule{Kind: kind, Name: id}
	}

	return Rule{Kind: kind, Engine: catalog.Engine(engine), Name: name}
}

// Disables says whether the config turns off any of the rules: a detector is off when it, its sin or its
// skill is named.
func (c Config) Disables(rules ...Rule) bool {
	for _, disabled := range c.Disabled {
		if slices.Contains(rules, disabled) {
			return true
		}
	}

	return false
}

// Positional is the config with every argument passed in order, as config.json passes them: a config.php
// may name an argument, config.json cannot.
func (c Config) Positional() Config {
	configurators := make([]Configurator, len(c.Configurators))

	for i, configurator := range c.Configurators {
		calls := make([]Call, len(configurator.Calls))

		for j, call := range configurator.Calls {
			args := make([]Arg, len(call.Args))

			for k, arg := range call.Args {
				args[k] = Arg{Value: arg.Value}
			}

			calls[j] = Call{call.Method, args}
		}

		configurators[i] = Configurator{configurator.Target, calls}
	}

	if c.Configurators != nil {
		c.Configurators = configurators
	}

	return c
}

// IsJudged says whether the file, absolute or under root, lies in one of the declared source roots.
func (c Config) IsJudged(root, file string) bool {
	home := strings.TrimRight(root, "/")
	absolute := file

	if !strings.HasPrefix(file, "/") {
		absolute = home + "/" + strings.TrimLeft(file, "/")
	}

	for _, relative := range c.Paths {
		dir := home + "/" + strings.Trim(relative, "/")
		if relative == "." {
			dir = home
		}

		if absolute == dir || strings.HasPrefix(absolute, dir+"/") {
			return true
		}
	}

	return false
}

// Writes says whether the project writes the language.
func (c Config) Writes(language source.Language) bool {
	return !slices.Contains(c.DisabledLanguages, language)
}

// Holds says whether the project writes the language and has a file of it under root, outside what it leaves out.
func (c Config) Holds(root string, language source.Language) bool {
	return c.Writes(language) && len(source.FilesIn(root, string(language), source.Under(root, c.Excluded))) > 0
}
