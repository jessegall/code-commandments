package config

import (
	"bytes"
	"encoding/json"
	"fmt"
	"os"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/source"
)

// SchemaFile is the JSON Schema a config.json names, written beside it so an editor completes and explains
// every key.
const SchemaFile = "config.schema.json"

// document is config.json as it is written: every key optional, in the order a reader meets them.
type document struct {
	Schema    string                        `json:"$schema,omitempty"`
	Paths     []string                      `json:"paths,omitempty"`
	Exclude   []string                      `json:"exclude,omitempty"`
	Disable   *disabled                     `json:"disable,omitempty"`
	Detectors []string                      `json:"detectors,omitempty"`
	Packages  []string                      `json:"packages,omitempty"`
	Hooks     []string                      `json:"hooks,omitempty"`
	Agents    []string                      `json:"agents,omitempty"`
	Configure map[string][]map[string][]any `json:"configure,omitempty"`
}

// disabled is what config.json turns off, by kind.
type disabled struct {
	Languages []string `json:"languages,omitempty"`
	Skills    []string `json:"skills,omitempty"`
	Sins      []string `json:"sins,omitempty"`
	Detectors []string `json:"detectors,omitempty"`
}

// ReadJSON reads the config.json at path. A key the format does not have is an invalid configuration, so a
// typo never passes as a setting nothing reads.
func ReadJSON(path string) (Config, error) {
	text, err := os.ReadFile(path)
	if err != nil {
		return Config{}, err
	}

	decoder := json.NewDecoder(bytes.NewReader(text))
	decoder.DisallowUnknownFields()
	decoder.UseNumber()

	var read document
	if err := decoder.Decode(&read); err != nil {
		return Config{}, &cli.InvalidConfiguration{Reason: "config.json: " + err.Error()}
	}

	return read.config()
}

func (d document) config() (Config, error) {
	config := Config{Paths: d.Paths, Excluded: d.Exclude, Detectors: d.Detectors, Packages: d.Packages, Hooks: d.Hooks, Agents: d.Agents}

	if d.Disable != nil {
		languages, err := languagesNamed(d.Disable.Languages)
		if err != nil {
			return Config{}, err
		}

		config.DisabledLanguages = languages
		config.Disabled = append(config.Disabled, rulesNamed(Skill, d.Disable.Skills)...)
		config.Disabled = append(config.Disabled, rulesNamed(Sin, d.Disable.Sins)...)
		config.Disabled = append(config.Disabled, rulesNamed(Detector, d.Disable.Detectors)...)
	}

	for _, target := range sortedKeys(d.Configure) {
		configurator, err := configuratorOf(target, d.Configure[target])
		if err != nil {
			return Config{}, err
		}

		config.Configurators = append(config.Configurators, configurator)
	}

	return config, nil
}

func rulesNamed(kind Kind, ids []string) []Rule {
	var rules []Rule

	for _, id := range ids {
		rules = append(rules, RuleNamed(kind, id))
	}

	return rules
}

// languagesNamed reads the languages config.json names: php, vue, typescript, python or csharp.
func languagesNamed(names []string) ([]source.Language, error) {
	var languages []source.Language

	for _, name := range names {
		language, known := languageNamed(name)
		if !known {
			return nil, &cli.InvalidConfiguration{Reason: fmt.Sprintf("config.json: disable.languages names %q, which is not a language the tool reads.", name)}
		}

		languages = append(languages, language)
	}

	return languages, nil
}

func languageNamed(name string) (source.Language, bool) {
	for _, language := range source.Languages {
		if strings.EqualFold(LanguageName(language), name) {
			return language, true
		}
	}

	return "", false
}

// LanguageName is how config.json names a language.
func LanguageName(language source.Language) string {
	return strings.ToLower(caseOf(language))
}

// configuratorOf reads one detector's calls: each a one-key object, the method it calls on the detector and
// its arguments in order.
func configuratorOf(target string, steps []map[string][]any) (Configurator, error) {
	configurator := Configurator{Target: RuleNamed(Detector, target)}

	for _, step := range steps {
		if len(step) != 1 {
			return Configurator{}, &cli.InvalidConfiguration{Reason: fmt.Sprintf("config.json: configure.%s holds a step naming %d methods; each step names one, as {\"layer\": [...]}.", target, len(step))}
		}

		for method, args := range step {
			call := Call{Method: method}

			for _, arg := range args {
				value, err := literal(arg)
				if err != nil {
					return Configurator{}, &cli.InvalidConfiguration{Reason: fmt.Sprintf("config.json: configure.%s.%s: %s", target, method, err)}
				}

				call.Args = append(call.Args, Arg{Value: value})
			}

			configurator.Calls = append(configurator.Calls, call)
		}
	}

	return configurator, nil
}

// literal is a JSON value as a configurator argument: a string, an int, a bool, nil, or a []any of those.
func literal(value any) (any, error) {
	switch typed := value.(type) {
	case json.Number:
		number, err := typed.Int64()
		if err != nil {
			return nil, fmt.Errorf("%s is not a whole number", typed)
		}

		return int(number), nil
	case []any:
		items := []any{}

		for _, item := range typed {
			each, err := literal(item)
			if err != nil {
				return nil, err
			}

			items = append(items, each)
		}

		return items, nil
	case map[string]any:
		return nil, fmt.Errorf("an argument is a string, a number, true, false, null or a list of those, never an object")
	default:
		return typed, nil
	}
}

// JSON is the config as config.json writes it, naming the schema beside it.
func (c Config) JSON() []byte {
	written := document{Schema: "./" + SchemaFile, Paths: c.Paths, Exclude: c.Excluded, Detectors: c.Detectors, Packages: c.Packages, Hooks: c.Hooks, Agents: c.Agents}

	if len(c.Disabled) > 0 || len(c.DisabledLanguages) > 0 {
		written.Disable = &disabled{}

		for _, language := range c.DisabledLanguages {
			written.Disable.Languages = append(written.Disable.Languages, LanguageName(language))
		}

		for _, rule := range c.Disabled {
			switch rule.Kind {
			case Skill:
				written.Disable.Skills = append(written.Disable.Skills, rule.ID())
			case Sin:
				written.Disable.Sins = append(written.Disable.Sins, rule.ID())
			default:
				written.Disable.Detectors = append(written.Disable.Detectors, rule.ID())
			}
		}
	}

	for _, configurator := range c.Configurators {
		if written.Configure == nil {
			written.Configure = map[string][]map[string][]any{}
		}

		target := configurator.Target.ID()

		for _, call := range configurator.Calls {
			args := []any{}
			for _, arg := range call.Args {
				args = append(args, arg.Value)
			}

			written.Configure[target] = append(written.Configure[target], map[string][]any{call.Method: args})
		}
	}

	var out bytes.Buffer

	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)
	encoder.SetIndent("", "    ")
	_ = encoder.Encode(written)

	return out.Bytes()
}

func sortedKeys[V any](values map[string]V) []string {
	keys := make([]string, 0, len(values))
	for key := range values {
		keys = append(keys, key)
	}

	sort.Strings(keys)

	return keys
}
