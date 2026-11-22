// Package config reads what a project declares about how it is judged: its `.commandments/config.json`.
package config

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/binary"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

// Config is what a project declares about how it is judged.
type Config struct {
	Paths              []string
	Excluded           []string
	Base               string
	Disabled           []Rule
	DisabledLanguages  []source.Language
	Detectors          []string
	Packages           []string
	Hooks              []string
	Agents             []string
	Parallel           int
	ReadAtOnce         int
	CacheHours         float64
	PublicContracts    map[catalog.Engine][]string
	ContractRegistrars map[catalog.Engine][]string
	ContractReasons    map[catalog.Engine]map[string]string
	Configurators      []Configurator
}

func Load(dir string) (Config, error) {
	settings := workspace.SettingsRoot(dir)

	if served, held := journalCopy.of(settings); held {
		return served, nil
	}

	if walk.Exists(workspace.Config(settings)) {
		return Config{}, &cli.InvalidConfiguration{Reason: ".commandments/config.php is the PHP tool's config, and this version reads only .commandments/config.json: write its settings there (`" + binary.Here() + " config --help` lists every key), then delete config.php."}
	}

	if !walk.Exists(workspace.JSONConfig(settings)) {
		return Config{}, nil
	}

	read, err := ReadJSON(workspace.JSONConfig(settings))
	if err != nil {
		return Config{}, err
	}

	return read, read.resolved()
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
