package config

import (
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

// Editor changes a project's config.json, keeping every key it does not touch.
type Editor interface {
	Name() string
	Disable(rule Rule) (bool, error)
	Enable(rule Rule) (bool, error)
	Scaffold(roots []string) (bool, error)
	EnsurePaths(roots []string) error
	RewritePaths(roots []string) error
	Layers() ([]Layer, error)
	RewriteLayers(layers []Layer) (bool, error)
	EnsureLayers(layers []Layer) (bool, error)
	RegisterDetector(name string) (bool, error)
	AllowContract(engine catalog.Engine, identity string) (bool, error)
	Declaration(layers []Layer) string
}

func EditorIn(dir string) Editor {
	return jsonConfig{workspace.JSONConfig(workspace.SettingsRoot(dir))}
}

func SwitchesIn(dir string) Switches {
	return jsonConfig{workspace.JSONConfig(workspace.SettingsRoot(dir))}
}

// jsonConfig edits a config.json: it reads the file, changes the config and writes it back whole.
type jsonConfig struct {
	path string
}

func layered(engine catalog.Engine) Rule {
	return Rule{Detector, engine, "NamespaceDependencyDetector"}
}

func (jsonConfig) Name() string {
	return ".commandments/config.json"
}

func (jsonConfig) Declaration(layers []Layer) string {
	declared := Config{Configurators: withLayers(nil, layers)}.JSON()
	_, configure, _ := strings.Cut(string(declared), "\n    \"$schema\": \"./"+SchemaFile+"\",\n")

	return strings.TrimSuffix(strings.TrimSuffix(configure, "\n"), "\n}")
}

func (j jsonConfig) Disable(rule Rule) (bool, error) {
	return j.editScaffolded(DetectRoots(j.root()), func(config *Config) bool {
		return added(&config.Disabled, rule, func(each Rule) bool { return each.ID() == rule.ID() })
	})
}

func (j jsonConfig) Enable(rule Rule) (bool, error) {
	if !j.exists() {
		return false, nil
	}

	return j.edit(func(config *Config) bool {
		kept := slices.DeleteFunc(slices.Clone(config.Disabled), func(each Rule) bool { return each.ID() == rule.ID() })
		changed := len(kept) != len(config.Disabled)
		config.Disabled = kept

		return changed
	})
}

func (j jsonConfig) editScaffolded(roots []string, change func(config *Config) bool) (bool, error) {
	if _, err := j.Scaffold(roots); err != nil {
		return false, err
	}

	return j.edit(change)
}

func added[T any](list *[]T, item T, same func(T) bool) bool {
	if slices.ContainsFunc(*list, same) {
		return false
	}
	*list = append(*list, item)

	return true
}

func (j jsonConfig) Scaffold(roots []string) (bool, error) {
	if j.exists() {
		return false, nil
	}

	return true, j.write(Config{Paths: roots})
}

func (j jsonConfig) EnsurePaths(roots []string) error {
	_, err := j.edit(func(config *Config) bool {
		if len(config.Paths) > 0 {
			return false
		}

		config.Paths = roots

		return true
	})

	return err
}

func (j jsonConfig) RewritePaths(roots []string) error {
	_, err := j.editScaffolded(roots, func(config *Config) bool {
		config.Paths = roots

		return true
	})

	return err
}

func (j jsonConfig) Layers() ([]Layer, error) {
	if !j.exists() {
		return nil, nil
	}

	config, err := ReadJSON(j.path)
	if err != nil {
		return nil, err
	}

	return layersOf(config, catalog.PHP), nil
}

func (j jsonConfig) RewriteLayers(layers []Layer) (bool, error) {
	if declared, err := j.Layers(); err != nil || len(declared) == 0 {
		return false, err
	}

	return j.edit(func(config *Config) bool {
		config.Configurators = withLayers(config.Configurators, layers)

		return true
	})
}

func (j jsonConfig) EnsureLayers(layers []Layer) (bool, error) {
	if declared, err := j.Layers(); !j.exists() || err != nil || len(declared) > 0 {
		return false, err
	}

	return j.edit(func(config *Config) bool {
		config.Configurators = withLayers(config.Configurators, layers)

		return true
	})
}

func (j jsonConfig) RegisterDetector(name string) (bool, error) {
	return j.editScaffolded(DetectRoots(j.root()), func(config *Config) bool {
		return added(&config.Detectors, name, func(each string) bool { return each == name })
	})
}

func (j jsonConfig) AllowContract(engine catalog.Engine, identity string) (bool, error) {
	release := atomic.Hold(j.path + ".lock")
	defer release()

	if _, err := j.Scaffold(DetectRoots(j.root())); err != nil {
		return false, err
	}
	return j.edit(func(config *Config) bool {
		if slices.Contains(config.PublicContracts[engine], identity) {
			return false
		}
		if config.PublicContracts == nil {
			config.PublicContracts = map[catalog.Engine][]string{}
		}
		config.PublicContracts[engine] = append(config.PublicContracts[engine], identity)
		return true
	})
}

func (j jsonConfig) edit(change func(*Config) bool) (bool, error) {
	config, err := ReadJSON(j.path)
	if err != nil || !change(&config) {
		return false, err
	}

	return true, j.write(config)
}

func (j jsonConfig) write(config Config) error {
	if err := os.MkdirAll(filepath.Dir(j.path), 0o777); err != nil {
		return err
	}

	return atomic.Write(j.path, string(config.JSON()))
}

func (j jsonConfig) exists() bool {
	return walk.Exists(j.path)
}

func (j jsonConfig) root() string {
	return filepath.Dir(filepath.Dir(j.path))
}

// Layer is one layer a project declares: its namespace and the namespaces it may use.
type Layer struct {
	Namespace string
	MayUse    []string
}

func layersOf(config Config, engine catalog.Engine) []Layer {
	var layers []Layer

	for _, configurator := range config.Configurators {
		if configurator.Target != layered(engine) {
			continue
		}

		for _, call := range configurator.Calls {
			if call.Method != "layer" || len(call.Args) == 0 {
				continue
			}

			namespace, named := call.Args[0].Value.(string)
			if !named {
				continue
			}

			layers = append(layers, Layer{Namespace: namespace, MayUse: mayUse(call)})
		}
	}

	return layers
}

func mayUse(call Call) []string {
	if len(call.Args) < 2 {
		return nil
	}
	var names []string
	for _, used := range asList(call.Args[1].Value) {
		name, isText := used.(string)
		if !isText {
			continue
		}
		names = append(names, name)
	}

	return names
}

func asList(value any) []any {
	if name, named := value.(string); named {
		return []any{name}
	}

	items, _ := value.([]any)

	return items
}

func withLayers(configurators []Configurator, layers []Layer) []Configurator {
	var calls []Call

	for _, layer := range layers {
		args := []Arg{{Value: layer.Namespace}}

		if len(layer.MayUse) > 0 {
			mayUse := []any{}
			for _, used := range layer.MayUse {
				mayUse = append(mayUse, used)
			}

			args = append(args, Arg{Value: mayUse})
		}

		calls = append(calls, Call{Method: "layer", Args: args})
	}

	for i, configurator := range configurators {
		if configurator.Target != layered(catalog.PHP) {
			continue
		}

		kept := slices.DeleteFunc(slices.Clone(configurator.Calls), func(call Call) bool { return call.Method == "layer" })
		configurators[i].Calls = append(kept, calls...)

		return configurators
	}

	return append(configurators, Configurator{Target: layered(catalog.PHP), Calls: calls})
}

// Switches are the edits the agent journal's settings make: a language on or off, and the folders judged and left out.
type Switches interface {
	Editor
	DisableLanguage(language source.Language) (bool, error)
	EnableLanguage(language source.Language) (bool, error)
	JudgeFolders(folders []string) error
	SkipFolders(folders []string) error
	RunWith(parallel int) (bool, error)
	ReadWith(atOnce int) (bool, error)
	KeepCachesFor(hours float64) (bool, error)
}

func (j jsonConfig) DisableLanguage(language source.Language) (bool, error) {
	if _, err := j.Scaffold(DetectRoots(j.root())); err != nil {
		return false, err
	}

	return j.edit(func(config *Config) bool {
		if slices.Contains(config.DisabledLanguages, language) {
			return false
		}

		config.DisabledLanguages = append(config.DisabledLanguages, language)

		return true
	})
}

func (j jsonConfig) EnableLanguage(language source.Language) (bool, error) {
	if !j.exists() {
		return false, nil
	}

	return j.edit(func(config *Config) bool {
		kept := slices.DeleteFunc(slices.Clone(config.DisabledLanguages), func(each source.Language) bool { return each == language })
		changed := len(kept) != len(config.DisabledLanguages)
		config.DisabledLanguages = kept

		return changed
	})
}

func (j jsonConfig) JudgeFolders(folders []string) error {
	return j.setFolders(func(config *Config) *[]string { return &config.Paths }, folders)
}

func (j jsonConfig) SkipFolders(folders []string) error {
	return j.setFolders(func(config *Config) *[]string { return &config.Excluded }, folders)
}

func (j jsonConfig) RunWith(parallel int) (bool, error) {
	if _, err := j.Scaffold(DetectRoots(j.root())); err != nil {
		return false, err
	}

	return j.edit(func(config *Config) bool {
		changed := config.Parallel != parallel
		config.Parallel = parallel

		return changed
	})
}

// ReadWith sets how many bridge processes read one language's files side by side.
func (j jsonConfig) ReadWith(atOnce int) (bool, error) {
	if _, err := j.Scaffold(DetectRoots(j.root())); err != nil {
		return false, err
	}

	return j.edit(func(config *Config) bool {
		changed := config.ReadAtOnce != atOnce
		config.ReadAtOnce = atOnce

		return changed
	})
}

func (j jsonConfig) KeepCachesFor(hours float64) (bool, error) {
	if _, err := j.Scaffold(DetectRoots(j.root())); err != nil {
		return false, err
	}

	return j.edit(func(config *Config) bool {
		changed := config.CacheHours != hours
		config.CacheHours = hours

		return changed
	})
}

func (j jsonConfig) setFolders(list func(*Config) *[]string, folders []string) error {
	if _, err := j.Scaffold(DetectRoots(j.root())); err != nil {
		return err
	}

	_, err := j.edit(func(config *Config) bool {
		*list(config) = folders

		return true
	})

	return err
}
