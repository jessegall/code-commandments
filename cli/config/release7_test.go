package config

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/detectors"
	_ "github.com/jessegall/code-commandments/registry"
)

const configTheRelease7ReadmeShows = `{
    "$schema": "./config.schema.json",
    "paths": ["app", "src"],
    "exclude": ["app/Generated"],
    "disable": {
        "sins": ["php/NonFinalData"],
        "detectors": ["php/FacadeCallDetector"],
        "skills": ["php/ValueObjects"],
        "languages": ["python"]
    },
    "configure": {
        "php/DataClumpDetector": [{"minClasses": [3]}],
        "php/NamespaceDependencyDetector": [
            {"layer": ["App\\Ui\\Tokens"]},
            {"layer": ["App\\Ui\\Elements", ["App\\Ui\\Tokens"]]}
        ]
    }
}`

func projectWith(t *testing.T, files map[string]string) string {
	t.Helper()
	root := t.TempDir()
	for path, text := range files {
		full := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(full, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func TestAConfigWrittenForRelease7RunsWithTheSameEffect(t *testing.T) {
	runs := strings.Replace(configTheRelease7ReadmeShows, `"php/DataClumpDetector": [{"minClasses": [3]}],`, "", 1)
	loaded, err := Load(projectWith(t, map[string]string{".commandments/config.json": runs}))
	if err != nil {
		t.Fatalf("the config is refused: %v", err)
	}

	enabled, err := loaded.Enabled(Everything)
	if err != nil {
		t.Fatalf("the config cannot be applied: %v", err)
	}

	var names []string
	for _, each := range enabled {
		names = append(names, each.Sin().Definition().Name)
		if each.Sin().Definition().Skill.Definition().Slug == "php/value-objects" {
			t.Errorf("%s runs, and its skill php/value-objects is turned off", catalog.Name(each))
		}
	}

	for _, off := range []string{"facade-call", "non-final-data"} {
		if slices.Contains(names, off) {
			t.Errorf("%s runs, and the config turns it off", off)
		}
	}

	if len(enabled) >= len(detectors.All()) {
		t.Errorf("%d of %d rules run", len(enabled), len(detectors.All()))
	}
}

func TestARelease7ParameterNoRuleDeclaresIsRefusedNamingItsLine(t *testing.T) {
	_, err := Load(projectWith(t, map[string]string{".commandments/config.json": configTheRelease7ReadmeShows}))

	refused, isRefusal := err.(*cli.InvalidConfiguration)
	refusedByItsLineAlone := isRefusal && strings.Contains(refused.Reason, "configure.php/DataClumpDetector.minClasses") && !strings.Contains(refused.Reason, "NamespaceDependency")
	if !refusedByItsLineAlone {
		t.Fatalf("the parameter data-clump no longer declares is not refused by its line: %v", err)
	}
}

func TestAConfigNamingWhatNoRuleAnswersToIsRefusedOnceListingEveryLine(t *testing.T) {
	_, err := Load(projectWith(t, map[string]string{".commandments/config.json": `{
    "disable": {"detectors": ["php/NoSuchDetector"], "sins": ["python/NeverASin"]},
    "configure": {"php/GoneDetector": [{"minClasses": [3]}]}
}`}))

	refused, isRefusal := err.(*cli.InvalidConfiguration)
	if !isRefusal {
		t.Fatalf("the config is not refused: %v", err)
	}

	for _, said := range []string{".commandments/config.json", "disable.detectors", "php/NoSuchDetector", "disable.sins", "python/NeverASin", "configure", "php/GoneDetector"} {
		if !strings.Contains(refused.Reason, said) {
			t.Errorf("%q is not said in %s", said, refused.Reason)
		}
	}
}

func TestAConfigPHPIsRefusedNamingIt(t *testing.T) {
	_, err := Load(projectWith(t, map[string]string{".commandments/config.php": "<?php\nreturn [];\n"}))

	refused, isRefusal := err.(*cli.InvalidConfiguration)
	namesBothFiles := isRefusal && strings.Contains(refused.Reason, ".commandments/config.php") && strings.Contains(refused.Reason, "config.json")
	if !namesBothFiles {
		t.Fatalf("a config.php is not refused by name: %v", err)
	}
}
