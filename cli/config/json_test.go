package config

import (
	"errors"
	"os"
	"path/filepath"
	"reflect"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestEveryKeyOfConfigJSONIsRead(t *testing.T) {
	config, err := ReadJSON(writeJSON(t, `{
    "$schema": "./config.schema.json",
    "paths": ["src", "resources/js"],
    "exclude": ["src/Generated"],
    "disable": {
        "languages": ["csharp", "TypeScript"],
        "skills": ["python/Absence"],
        "sins": ["python/DictBag"],
        "detectors": ["frontend/DeepNestedDetector", "Old"]
    },
    "detectors": ["NoRawSql"],
    "packages": ["Framework"],
    "hooks": ["SkillReminder"],
    "agents": ["Aider"],
    "configure": {
        "frontend/DeepNestedDetector": [
            {"maxDepth": [10]},
            {"named": ["deep", true, ["a", -2], null]}
        ]
    }
}`))
	if err != nil {
		t.Fatal(err)
	}

	want := Config{
		Paths:             []string{"src", "resources/js"},
		Excluded:          []string{"src/Generated"},
		Disabled:          []Rule{{Skill, catalog.Python, "Absence"}, {Sin, catalog.Python, "DictBag"}, deepNested, {Detector, "", "Old"}},
		DisabledLanguages: []source.Language{source.CSharp, source.TypeScript},
		Detectors:         []string{"NoRawSql"},
		Packages:          []string{"Framework"},
		Hooks:             []string{"SkillReminder"},
		Agents:            []string{"Aider"},
		Configurators: []Configurator{{deepNested, []Call{
			{"maxDepth", []Arg{{"", 10}}},
			{"named", []Arg{{"", "deep"}, {"", true}, {"", []any{"a", -2}}, {"", nil}}},
		}}},
	}

	if !reflect.DeepEqual(config, want) {
		t.Errorf("read\n%#v\nwant\n%#v", config, want)
	}
}

func TestWhatConfigJSONWritesItReadsBack(t *testing.T) {
	config := Config{
		Paths:             []string{"app"},
		Disabled:          []Rule{{Skill, catalog.Backend, "Absence"}, {Detector, catalog.Backend, "ArrayBagDetector"}},
		DisabledLanguages: []source.Language{source.Python},
		Configurators: []Configurator{{Rule{Detector, catalog.Backend, "NamespaceDependencyDetector"}, []Call{
			{"layer", []Arg{{"", `App\Domain`}}},
			{"layer", []Arg{{"", `App\Http`}, {"", []any{`App\Domain`}}}},
		}}},
	}

	written := string(config.JSON())
	if !strings.HasPrefix(written, "{\n    \"$schema\": \"./config.schema.json\",\n    \"paths\": [\n        \"app\"\n    ],") || !strings.Contains(written, `"App\\Http"`) {
		t.Errorf("wrote\n%s", written)
	}

	read, err := ReadJSON(writeJSON(t, written))
	if err != nil || !reflect.DeepEqual(read, config) {
		t.Errorf("read back\n%#v\n%v", read, err)
	}
}

func TestConfigJSONIsReadBeforeConfigPHP(t *testing.T) {
	dir := t.TempDir()
	must(t, os.MkdirAll(filepath.Join(dir, ".commandments"), 0o755))
	must(t, os.WriteFile(workspace.Config(dir), []byte("<?php\nreturn fn ($c) => $c->paths('php');\n"), 0o644))
	must(t, os.WriteFile(workspace.JSONConfig(dir), []byte(`{"paths": ["json"]}`), 0o644))

	if config, err := Load(dir); err != nil || !reflect.DeepEqual(config.Paths, []string{"json"}) {
		t.Errorf("%+v %v", config, err)
	}
}

func TestAConfigJSONTheToolCannotReadIsAnInvalidConfiguration(t *testing.T) {
	for text, reason := range map[string]string{
		`{"path": ["src"]}`:                                  `config.json: json: unknown field "path"`,
		`{"disable": {"languages": ["cobol"]}}`:              `config.json: disable.languages names "cobol", which is not a language the tool reads.`,
		`{"configure": {"backend/X": [{"a": [], "b": []}]}}`: `config.json: configure.backend/X holds a step naming 2 methods; each step names one, as {"layer": [...]}.`,
		`{"configure": {"backend/X": [{"a": [1.5]}]}}`:       `config.json: configure.backend/X.a: 1.5 is not a whole number`,
	} {
		_, err := ReadJSON(writeJSON(t, text))

		var invalid *cli.InvalidConfiguration
		if !errors.As(err, &invalid) || invalid.Reason != reason {
			t.Errorf("%s: %v", text, err)
		}
	}
}

func writeJSON(t *testing.T, contents string) string {
	t.Helper()
	path := filepath.Join(t.TempDir(), "config.json")
	must(t, os.WriteFile(path, []byte(contents), 0o644))

	return path
}

func must(t *testing.T, err error) {
	t.Helper()

	if err != nil {
		t.Fatal(err)
	}
}
