// Package rules is the installed rule catalogue: every rule the tool ships, each a commandments:generic-rule/v2 file
// in the folder of the skill that teaches its fix, read through the same loader as a project's own and registered
// beside the detectors.
package rules

import (
	"embed"
	"encoding/json"
	"fmt"
	"io/fs"
	"os"
	"path"
	"path/filepath"
	"slices"
	"testing/fstest"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
	// Every skill a rule can name is registered before the rules are read.
	_ "github.com/jessegall/code-commandments/skill/backend"
	_ "github.com/jessegall/code-commandments/skill/backend/concurrent"
	_ "github.com/jessegall/code-commandments/skill/backend/laravel"
	_ "github.com/jessegall/code-commandments/skill/backend/spatie"
	_ "github.com/jessegall/code-commandments/skill/csharp"
	_ "github.com/jessegall/code-commandments/skill/frontend"
	_ "github.com/jessegall/code-commandments/skill/go"
	_ "github.com/jessegall/code-commandments/skill/python"
	_ "github.com/jessegall/code-commandments/skill/shared"
	_ "github.com/jessegall/code-commandments/skill/typescript"
)

//go:embed */*.json packages/*/*.json exemption-tags.json
var embedded embed.FS

// overrideFolder is where a user's edits of the installed rules live: the folder COMMANDMENTS_RULES names, else a
// rules folder beside the running binary.
func overrideFolder() string {
	if named := os.Getenv("COMMANDMENTS_RULES"); named != "" {
		return named
	}
	running, err := os.Executable()
	if err != nil {
		return ""
	}

	return filepath.Join(filepath.Dir(running), "rules")
}

// shipped is the catalogue as it is read: the embedded files, each replaced by a file at the same path in the override
// folder, and every further file there added.
var shipped = catalogue(overrideFolder())

func catalogue(override string) fs.FS {
	files := fstest.MapFS{}
	_ = fs.WalkDir(embedded, ".", func(name string, entry fs.DirEntry, err error) error {
		if err == nil && !entry.IsDir() {
			text, _ := fs.ReadFile(embedded, name)
			files[name] = &fstest.MapFile{Data: text}
		}

		return nil
	})
	if info, err := os.Stat(override); override == "" || err != nil || !info.IsDir() {
		return files
	}
	_ = filepath.WalkDir(override, func(name string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() || filepath.Ext(name) != ".json" {
			return nil
		}
		relative, _ := filepath.Rel(override, name)
		if text, read := os.ReadFile(name); read == nil {
			files[filepath.ToSlash(relative)] = &fstest.MapFile{Data: text}
		}

		return nil
	})

	return files
}

// Export writes every embedded file of the catalogue under the folder, to edit as an override.
func Export(folder string) (int, error) {
	written := 0
	err := fs.WalkDir(embedded, ".", func(name string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() {
			return err
		}
		text, err := fs.ReadFile(embedded, name)
		if err != nil {
			return err
		}
		target := filepath.Join(folder, filepath.FromSlash(name))
		if err := os.MkdirAll(filepath.Dir(target), 0o755); err != nil {
			return err
		}
		written++

		return os.WriteFile(target, text, 0o644)
	})

	return written, err
}

// LoadFrom reads the catalogue as an override folder makes it, for a test of an edited rule.
func LoadFrom(override string) rule.Library {
	return rule.Load(sourcesIn(catalogue(override)), skill.Slugged)
}

// Sources is every installed rule file, by its path under rules/.
func Sources() []rule.Source {
	return sourcesIn(shipped)
}

func sourcesIn(files fs.FS) []rule.Source {
	var sources []rule.Source
	paths, _ := fs.Glob(files, "*/*.json")
	for _, each := range paths {
		text, err := fs.ReadFile(files, each)
		if err == nil {
			sources = append(sources, rule.Source{Origin: rule.Installed, Path: path.Join("rules", each), Text: text})
		}
	}

	return sources
}

var installed = rule.Load(Sources(), skill.Slugged)

// Problems is each installed rule file the loader could not read, naming the file.
func Problems() []error {
	return installed.Problems
}

// Packages is what each library the catalogue knows states about its own symbols, one file per package under
// rules/packages/<ecosystem>/.
func Packages() ([]engine.PackageFacts, error) {
	var tags []struct{ Tag, Why string }
	text, err := fs.ReadFile(shipped, "exemption-tags.json")
	if err != nil || json.Unmarshal(text, &tags) != nil {
		return nil, fmt.Errorf("rules/exemption-tags.json: the exemption tags cannot be read")
	}
	var facts []engine.PackageFacts
	paths, _ := fs.Glob(shipped, "packages/*/*.json")
	for _, each := range paths {
		text, err := fs.ReadFile(shipped, each)
		if err != nil {
			return nil, err
		}
		var read engine.PackageFacts
		if err := json.Unmarshal(text, &read); err != nil {
			return nil, fmt.Errorf("rules/%s: %w", each, err)
		}
		for _, exemption := range read.Exemptions {
			if !slices.ContainsFunc(tags, func(declared struct{ Tag, Why string }) bool { return declared.Tag == exemption.Tag }) {
				return nil, fmt.Errorf("rules/%s: the exemption tag %q is none of rules/exemption-tags.json", each, exemption.Tag)
			}
		}
		facts = append(facts, read)
	}

	return facts, nil
}

func init() {
	for _, each := range installed.Rules {
		if !each.IsUnpublished() {
			rule.Register(each.Engine(), each)
		}
	}
	facts, err := Packages()
	if err != nil {
		installed.Problems = append(installed.Problems, err)
	}
	for _, each := range facts {
		engine.RegisterPackageFacts(each)
	}
}

// All is every rule the tool ships: the installed rule files.
func All() []rule.Detector {
	return rule.All()
}

// Of is every shipped rule that judges the engine.
func Of(engine catalog.Engine) []rule.Detector {
	return rule.Of(engine)
}

// EnginesOf is every engine a shipped rule judges.
func EnginesOf(asked rule.Detector) []catalog.Engine {
	return rule.EnginesOf(asked)
}

var described = rule.Describe(Sources(), skill.Slugged)

// Sins is the sin of every rule file the tool ships, its find written or not yet, in file order: what every list,
// document and command naming a rule reads.
func Sins() []sins.Sin {
	var named []sins.Sin
	for _, each := range described {
		named = append(named, each.Sin)
	}

	return named
}

// SinsOf is the sin of every shipped rule file that judges the engine.
func SinsOf(engine catalog.Engine) []sins.Sin {
	var named []sins.Sin
	for _, each := range described {
		if slices.Contains(each.Engines, engine) {
			named = append(named, each.Sin)
		}
	}

	return named
}
