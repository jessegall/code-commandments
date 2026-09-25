package config

import (
	"errors"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Editor changes a project's config in whichever format the project keeps it: config.json, or the
// config.php sync has not migrated yet. A rule is named by its class, which says the same in both.
type Editor interface {
	// Name is the config file as a message names it, under the project.
	Name() string
	// Disable turns the rule off, scaffolding the config first when there is none; false when it already was.
	Disable(class string) (bool, error)
	// Enable turns the rule back on; false when it was not off.
	Enable(class string) (bool, error)
	// Scaffold writes the starting config when there is none; false when there is one.
	Scaffold(roots []string) (bool, error)
	// EnsurePaths fills empty paths with the roots.
	EnsurePaths(roots []string) error
	// RewritePaths writes the roots as the paths, scaffolding the config when there is none.
	RewritePaths(roots []string) error
	// Layers are the layers the config declares, in the order it declares them.
	Layers() ([]Layer, error)
	// RewriteLayers replaces the declared layers; false when none are declared.
	RewriteLayers(layers []Layer) (bool, error)
	// EnsureLayers declares the layers; false when layers are declared already, or there is no config.
	EnsureLayers(layers []Layer) (bool, error)
	// RegisterDetector turns on the project's own detector; false when it already is.
	RegisterDetector(class string) (bool, error)
	// Declaration is the layers as the config declares them, to show before they are written.
	Declaration(layers []Layer) string
}

// EditorIn is the editor of the config of the project at dir: its config.php while that is the only config
// it has, else its config.json, which a project with no config at all starts with.
func EditorIn(dir string) Editor {
	_, noJSON := os.Stat(workspace.JSONConfig(dir))
	_, noPHP := os.Stat(workspace.Config(dir))

	if errors.Is(noJSON, os.ErrNotExist) && noPHP == nil {
		return phpConfig{FileIn(dir), ScribeIn(dir)}
	}

	return jsonConfig{workspace.JSONConfig(dir)}
}

// phpConfig edits a config.php through its tree.
type phpConfig struct {
	File
	Scribe
}

func (phpConfig) Name() string {
	return ".commandments/config.php"
}

func (phpConfig) Declaration(layers []Layer) string {
	return RenderDeclaration(layers)
}

func (p phpConfig) EnsureLayers(layers []Layer) (bool, error) {
	return p.Scribe.EnsureLayers(RenderDeclaration(layers))
}

// RenderDeclaration is the layers as the configure() statement a config.php declares them with.
func RenderDeclaration(layers []Layer) string {
	return "    $config->configure(fn (NamespaceDependencyDetector $detector) => $detector" +
		RenderChain(layers, "        ") + ");"
}

// jsonConfig edits a config.json: it reads the file, changes the config and writes it back whole.
type jsonConfig struct {
	path string
}

// layered is the detector a project declares its layers on.
var layered = Rule{Detector, catalog.Backend, "NamespaceDependencyDetector"}

func (jsonConfig) Name() string {
	return ".commandments/config.json"
}

func (jsonConfig) Declaration(layers []Layer) string {
	declared := Config{Configurators: withLayers(nil, layers)}.JSON()
	_, configure, _ := strings.Cut(string(declared), "\n    \"$schema\": \"./"+SchemaFile+"\",\n")

	return strings.TrimSuffix(strings.TrimSuffix(configure, "\n"), "\n}")
}

func (j jsonConfig) Disable(class string) (bool, error) {
	if _, err := j.Scaffold(DetectRoots(j.root())); err != nil {
		return false, err
	}

	return j.edit(func(config *Config) bool {
		rule := ruleOfClass(class)
		if slices.Contains(config.Disabled, rule) {
			return false
		}

		config.Disabled = append(config.Disabled, rule)

		return true
	})
}

func (j jsonConfig) Enable(class string) (bool, error) {
	if !j.exists() {
		return false, nil
	}

	return j.edit(func(config *Config) bool {
		kept := slices.DeleteFunc(slices.Clone(config.Disabled), func(each Rule) bool { return each == ruleOfClass(class) })
		changed := len(kept) != len(config.Disabled)
		config.Disabled = kept

		return changed
	})
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
	if scaffolded, err := j.Scaffold(roots); scaffolded || err != nil {
		return err
	}

	_, err := j.edit(func(config *Config) bool {
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

	return layersOf(config), nil
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

func (j jsonConfig) RegisterDetector(class string) (bool, error) {
	if _, err := j.Scaffold(DetectRoots(j.root())); err != nil {
		return false, err
	}

	return j.edit(func(config *Config) bool {
		name := shortName(class)
		if slices.Contains(config.Detectors, name) {
			return false
		}

		config.Detectors = append(config.Detectors, name)

		return true
	})
}

// edit applies the change to the config and writes it when the change says it changed something.
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

	return os.WriteFile(j.path, config.JSON(), 0o644)
}

func (j jsonConfig) exists() bool {
	_, err := os.Stat(j.path)

	return err == nil
}

func (j jsonConfig) root() string {
	return filepath.Dir(filepath.Dir(j.path))
}

// layersOf are the layers the config's layer() calls on the dependency detector declare.
func layersOf(config Config) []Layer {
	var layers []Layer

	for _, configurator := range config.Configurators {
		if configurator.Target != layered {
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

			layer := Layer{Namespace: namespace}

			if len(call.Args) > 1 {
				for _, used := range asList(call.Args[1].Value) {
					if each, isText := used.(string); isText {
						layer.MayUse = append(layer.MayUse, each)
					}
				}
			}

			layers = append(layers, layer)
		}
	}

	return layers
}

func asList(value any) []any {
	items, _ := value.([]any)

	return items
}

// withLayers are the configurators with the dependency detector's layer() calls replaced by the layers,
// its other calls kept.
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
		if configurator.Target != layered {
			continue
		}

		kept := slices.DeleteFunc(slices.Clone(configurator.Calls), func(call Call) bool { return call.Method == "layer" })
		configurators[i].Calls = append(kept, calls...)

		return configurators
	}

	return append(configurators, Configurator{Target: layered, Calls: calls})
}

// Switches are the edits the agent journal's settings make: a language on or off, and the folders judged
// and left out. Only config.json takes them; a config.php is migrated first.
type Switches interface {
	Editor
	// DisableLanguage turns a language off; false when it already was.
	DisableLanguage(language source.Language) (bool, error)
	// EnableLanguage turns a language back on; false when it was not off.
	EnableLanguage(language source.Language) (bool, error)
	// JudgeFolders makes the folders the paths judged.
	JudgeFolders(folders []string) error
	// SkipFolders makes the folders the paths left out.
	SkipFolders(folders []string) error
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
