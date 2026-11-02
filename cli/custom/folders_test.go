package custom

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

// A project with rules for several parts of its code groups them by what they protect: core/ for its domain,
// viewer/ for its interface text. One flat folder of twenty rules shows nothing about which part each one guards,
// and a rule moved into a folder used to stop judging without a word. These hold the folders, the one name a rule
// has wherever it sits, and the lists it checks against wherever those sit.

// aRule is a rule file of the engine and sin named, finding every call of that name.
func aRule(sin, called string) string {
	return `{"engine": "python", "sin": {"name": "` + sin + `", "description": "a rule of the project's own", "skill": "python/flow"},
	 "find": {"select": "call", "where": [{"name": "` + called + `"}]}}`
}

// TestARuleIsReadFromAnyFolderUnderTheCustomOne holds the ask itself: a rule judges from wherever the project
// files it, at any depth, and the skills folder is not read for rules, since whatever JSON a skill keeps beside its
// SKILL.md is its own business.
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

// TestTwoRulesOfOneNameInTwoFoldersAreBothRefused holds what a rule's name is: its file's, whatever folder holds it,
// so moving one leaves every mention of it in the config standing. Two files of one name would be one rule with two
// bodies, and which of them judged would depend on the order a folder is read in, so neither is read and the reason
// names both.
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

// TestTheListsAreReadWhereverTheyAreKept holds the other half of grouping: the lists a rule checks against sit in a
// folder of their own, and a rule in another folder names one of them, since a rule names a LIST and never the file
// it is in.
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

// TestOneListNameInTwoFilesIsRefused holds the cost of reading them all as one set: a name two files declare is a
// rule checking against whichever file was read last, so neither is taken and the reason names both files.
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

// TestADetectorDeclaredNowhereIsToldApartFromOneThatCannotRun holds the two things a reader has to tell apart. A
// configured rule nobody wrote is missing, and the advice to check it is present and committed is the right advice.
// A rule whose file IS there and cannot be read has its own reason said, and calling that one missing sends the
// reader looking for a file they are looking at.
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

// readNames are the rules read, by name, for a failure to say what it did read.
func readNames(read Project) []string {
	var names []string
	for _, own := range read.Rules {
		names = append(names, own.Name())
	}

	return names
}
