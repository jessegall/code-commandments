// Package config reads what a project declares about how it is judged: its `.commandments/config.json`, or, until sync migrates it, the `.commandments/config.php` the PHP tool executed.
package config

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

// Config is what a project declares about how it is judged.
type Config struct {
	Paths             []string
	Excluded          []string
	Base              string
	Disabled          []Rule
	DisabledLanguages []source.Language
	Detectors         []string
	Packages          []string
	Hooks             []string
	Agents            []string
	Parallel          int
	ReadAtOnce        int
	CacheHours        float64
	Configurators     []Configurator
}

func Load(dir string) (Config, error) {
	settings := workspace.SettingsRoot(dir)

	if served, held := journalCopy.of(settings); held {
		return served, nil
	}

	if walk.Exists(workspace.JSONConfig(settings)) {
		return ReadJSON(workspace.JSONConfig(settings))
	}

	return ReadPHP(workspace.Config(settings))
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

// Arg is one argument, named when it was passed by name.
type Arg struct {
	Name  string
	Value any
}

// Rule is a rule as a config names it: its kind, the engine it belongs to, and its name.
type Rule struct {
	Kind   Kind
	Engine catalog.Engine
	Name   string
}

// Kind is which catalog a rule is in.
type Kind string

const (
	Skill    Kind = "Skills"
	Sin      Kind = "Sins"
	Detector Kind = "Detectors"
	Hook     Kind = "Hooks"
	Agent    Kind = "Agents"
)

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

func engineOf(namespace []string) (catalog.Engine, bool) {
	if namespace[0] == "Frontend" && len(namespace) > 1 && namespace[1] == "TypeScript" {
		return catalog.TypeScript, true
	}

	return catalog.EngineNamed(namespace[0])
}

func (r Rule) ID() string {
	if r.Engine == "" {
		return r.Name
	}

	return string(r.Engine) + "/" + r.Name
}

func (r Rule) Shipped() bool {
	return r.Engine != ""
}

func RuleNamed(kind Kind, id string) Rule {
	engine, name, shipped := strings.Cut(id, "/")
	if !shipped {
		return Rule{Kind: kind, Name: id}
	}

	named, _ := catalog.EngineNamed(engine)

	return Rule{Kind: kind, Engine: named, Name: name}
}

func (c Config) Disables(rules ...Rule) bool {
	for _, disabled := range c.Disabled {
		if slices.Contains(rules, disabled) {
			return true
		}
	}

	return false
}

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

func (c Config) IsJudged(root, file string) bool {
	home := strings.TrimRight(root, "/")
	absolute := file

	if !strings.HasPrefix(file, "/") {
		absolute = home + "/" + strings.TrimLeft(file, "/")
	}

	if !source.Judges(absolute) || !c.Writes(source.OfFile(absolute)) {
		return false
	}

	if c.Exclusions(home).Covers(absolute) {
		return false
	}

	for _, relative := range append(slices.Clone(c.Paths), c.JudgedDespiteExclusion()...) {
		dir := home + "/" + strings.Trim(relative, "/")
		if relative == "." {
			dir = home
		}

		if walk.Within(dir, absolute) {
			return true
		}
	}

	return false
}

func (c Config) Writes(language source.Language) bool {
	return !slices.Contains(c.DisabledLanguages, language)
}

func (c Config) Holds(root string, language source.Language) bool {
	return c.Reads(root, language) && len(source.FilesIn(root, language, source.Under(root, c.Excluded))) > 0
}

func (c Config) Reads(root string, language source.Language) bool {
	return c.Writes(language) && language.DeclaredIn(root)
}
