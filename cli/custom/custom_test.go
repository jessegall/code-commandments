package custom

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/config"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/skill"
)

const rawSQL = `{"engine": "backend", "sin": {"name": "raw-sql", "description": "SQL written inline", "skill": "no-raw-sql"},
 "find": {"select": "call", "where": [{"name": "select"}]}}`

const ownSkill = `---
name: No raw SQL
description: Reach for this before you write a query.
summary: queries go through the repository, never raw SQL at a call site.
tier: mandatory
languages: [php]
---

# No raw SQL
`

func project(t *testing.T, files map[string]string) string {
	t.Helper()
	root := t.TempDir()

	for path, contents := range files {
		full := filepath.Join(root, ".commandments", "custom", path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}

		if err := os.WriteFile(full, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func TestAProjectsOwnSkillAndRuleAreReadFromItsCustomFolder(t *testing.T) {
	loaded := Load(project(t, map[string]string{"RawSqlDetector.json": rawSQL, "skills/no-raw-sql/SKILL.md": ownSkill}))

	if len(loaded.Skills) != 1 || len(loaded.Rules) != 1 || len(loaded.Unreadable) != 0 {
		t.Fatalf("%+v", loaded)
	}

	definition := loaded.Skills[0].Definition()
	if definition.Slug != "no-raw-sql" || definition.Tier != skill.Mandatory || definition.Summary != "queries go through the repository, never raw SQL at a call site." || strings.Join(definition.Languages, ",") != "php" {
		t.Errorf("%+v", definition)
	}

	own := loaded.Rules[0]
	if catalog.Name(own) != "RawSqlDetector" || own.Sin().Definition().Slug() != "no-raw-sql" || !Owns(own) {
		t.Errorf("%s %s", catalog.Name(own), own.Sin().Definition().Slug())
	}
}

func TestOnlyTheRulesTheConfigTurnsOnRun(t *testing.T) {
	loaded := Load(project(t, map[string]string{"RawSqlDetector.json": rawSQL, "skills/no-raw-sql/SKILL.md": ownSkill}))

	for name, want := range map[string]struct {
		config config.Config
		runs   int
	}{
		"not turned on": {config.Config{}, 0},
		"turned on":     {config.Config{Detectors: []string{"RawSqlDetector"}}, 1},
		"turned off":    {config.Config{Detectors: []string{"RawSqlDetector"}, Disabled: []config.Rule{{Kind: config.Detector, Name: "RawSqlDetector"}}}, 0},
	} {
		if runs := len(loaded.Enabled(want.config)); runs != want.runs {
			t.Errorf("%s: %d run", name, runs)
		}
	}
}

func TestEverythingTheBinaryCannotRunIsNamed(t *testing.T) {
	loaded := Load(project(t, map[string]string{
		"Broken.json":          `{"engine": "backend"}`,
		"NoRawSqlDetector.php": "<?php final class NoRawSqlDetector {}",
	}))

	warnings := strings.Join(loaded.Warnings(config.Config{Detectors: []string{"Gone"}}), "\n")

	for _, said := range []string{"could not be loaded", "  Gone", "Broken: a sin needs a name", "NoRawSqlDetector.php is a PHP class", "commandments make", "SKILL.md", "Migrating from the PHP tool"} {
		if !strings.Contains(warnings, said) {
			t.Errorf("%q not said in\n%s", said, warnings)
		}
	}
}
