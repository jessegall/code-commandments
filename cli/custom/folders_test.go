package custom

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

func aRule(sin, called string) string {
	return `{"engine": "python", "sin": {"name": "` + sin + `", "description": "a rule of the project's own", "skill": "python/flow"},
	 "find": {"select": "call", "where": [{"name": "` + called + `"}]}}`
}

func TestARuleIsReadFromAnyFolderUnderTheCustomOne(t *testing.T) {
	root := project(t, map[string]string{
		"AtTheRootDetector.json":              aRule("at-the-root", "print"),
		"core/InAFolderDetector.json":         aRule("in-a-folder", "open"),
		"core/domain/DeeperDownDetector.json": aRule("deeper-down", "eval"),
		"skills/no-raw-sql/SKILL.md":          ownSkill,
		"skills/no-raw-sql/words.json":        `{"not": "a rule"}`,
	})

	read := Load(root)

	for _, name := range []string{"AtTheRootDetector", "InAFolderDetector", "DeeperDownDetector"} {
		if _, found := read.Named(name); !found {
			t.Errorf("%s was not read: the rules read are %v, and it cannot run: %v", name, readNames(read), read.Unreadable)
		}
	}

	if len(read.Rules) != 3 {
		t.Errorf("%d rules were read, and the folder holds three: %v", len(read.Rules), readNames(read))
	}
}

func TestTwoRulesOfOneNameInTwoFoldersAreBothRefused(t *testing.T) {
	root := project(t, map[string]string{
		"core/SameNameDetector.json":   aRule("same-name", "print"),
		"viewer/SameNameDetector.json": aRule("same-name", "open"),
	})

	read := Load(root)

	said := strings.Join(read.Unreadable, "\n")
	if !strings.Contains(said, "SameNameDetector") || !strings.Contains(said, "core/SameNameDetector.json") {
		t.Errorf("the reason names neither the rule nor the file it clashes with: %q", said)
	}

	if len(read.Rules) != 1 {
		t.Errorf("%d rules were read, where two files of one name leave one: %v", len(read.Rules), readNames(read))
	}
}

func TestTheListsAreReadWhereverTheyAreKept(t *testing.T) {
	root := project(t, map[string]string{
		"assets/lists.json": `{"poetic": ["ephemeral", "sublime"]}`,
		"viewer/NamesTheListDetector.json": `{"engine": "python", "sin": {"name": "names-the-list", "description": "a rule naming a list from another folder", "skill": "python/flow"},
		 "find": {"select": "function", "where": [{"nameIn": ["$poetic"]}]}}`,
	})

	read := Load(root)

	if _, found := read.Named("NamesTheListDetector"); !found {
		t.Errorf("the rule naming a list from another folder was not read: %v", read.Unreadable)
	}
}

func TestOneListNameInTwoFilesIsRefused(t *testing.T) {
	root := project(t, map[string]string{
		"assets/lists.json": `{"poetic": ["ephemeral"]}`,
		"core/lists.json":   `{"poetic": ["sublime"]}`,
		"NamesTheListDetector.json": `{"engine": "python", "sin": {"name": "names-the-list", "description": "a rule naming a list declared twice", "skill": "python/flow"},
		 "find": {"select": "function", "where": [{"nameIn": ["$poetic"]}]}}`,
	})

	read := Load(root)

	said := strings.Join(read.Unreadable, "\n")
	if !strings.Contains(said, "poetic") || !strings.Contains(said, "lists.json") {
		t.Errorf("the reason names neither the list nor the files declaring it: %q", said)
	}
}

func TestADetectorDeclaredNowhereIsToldApartFromOneThatCannotRun(t *testing.T) {
	root := project(t, map[string]string{
		"BrokenDetector.json": `{"engine": "python", "sin": {"name": "broken", "description": "a rule naming a list nothing declares", "skill": "python/flow"},
		 "find": {"select": "function", "where": [{"nameIn": ["$nowhere"]}]}}`,
	})

	read := Load(root)
	said := strings.Join(read.Warnings(config.Config{Detectors: []string{"BrokenDetector", "NeverWrittenDetector"}}), "\n")

	if !strings.Contains(said, "NeverWrittenDetector") {
		t.Errorf("the rule nobody wrote is not named as declared nowhere: %q", said)
	}

	for _, line := range strings.Split(said, "\n") {
		if strings.Contains(line, "declared nowhere") && strings.Contains(line, "BrokenDetector") {
			t.Errorf("the rule whose file is there is called missing: %q", line)
		}
	}

	if !strings.Contains(said, `names the list "nowhere"`) {
		t.Errorf("the reason the rule cannot run is not said: %q", said)
	}
}

func readNames(read Project) []string {
	var names []string
	for _, own := range read.Rules {
		names = append(names, own.Name())
	}

	return names
}
