package library

import (
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

var commandNames = []string{"`judge`", "`info`", "`repent`", "`report`", "`make`", "info <sin>", "repent --", "scaffold --", "report --", "feature-request", "freeze", "the commands"}

func namesNoCommand(t *testing.T, what, text string) {
	t.Helper()

	for _, named := range commandNames {
		if strings.Contains(text, named) {
			t.Errorf("%s names the command %q:\n%s", what, named, text)
		}
	}

	if strings.Contains(text, "{{") || strings.Contains(text, "<!--") || strings.Contains(text, "\n\n\n") {
		t.Errorf("%s leaves a placeholder, a marker or a doubled blank line in it:\n%s", what, text)
	}
}

func TestTheBriefForWorkersNamesNoCommand(t *testing.T) {
	brief := BriefForWorkers()

	namesNoCommand(t, "the brief for workers", brief)

	for _, kept := range []string{"TRACE TO THE SOURCE", "Load `commandments` for the index", "A finding names"} {
		if !strings.Contains(brief, kept) {
			t.Errorf("the brief for workers leaves out %q:\n%s", kept, brief)
		}
	}
}

func TestTheBriefForEveryoneElseStillNamesTheCommands(t *testing.T) {
	for what, brief := range map[string]string{"the brief for the journal": BriefForJournal(), "the brief of a checkout": Brief(t.TempDir(), config.Config{})} {
		for _, kept := range []string{"and the rest of\nthe commands", "A `judge` finding names", "info <sin>"} {
			if !strings.Contains(brief, kept) {
				t.Errorf("%s leaves out %q:\n%s", what, kept, brief)
			}
		}

		if strings.Contains(brief, "{{") {
			t.Errorf("%s leaves a placeholder in it:\n%s", what, brief)
		}
	}
}

func TestTheIndexWithoutCommandsKeepsTheDisciplinesAndNamesNoCommand(t *testing.T) {
	project := t.TempDir()
	without := MapWithoutCommands(project, config.Config{})

	namesNoCommand(t, "the index without commands", without)

	for _, kept := range []string{"TRACE TO THE SOURCE", "commandments-php-fix-at-the-source", "commandments-php-absence", "When in doubt, load", "Leave it cleaner"} {
		if !strings.Contains(without, kept) {
			t.Errorf("the index without commands leaves out %q:\n%s", kept, without)
		}
	}

	with := Map(project, config.Config{})

	for _, kept := range []string{"report --detector=NAME", "scaffold --sin=NAME", "feature-request --title", "freeze <path>", "info <sin>", "`judge`"} {
		if !strings.Contains(with, kept) {
			t.Errorf("the index of a checkout leaves out %q:\n%s", kept, with)
		}
	}

	if strings.Contains(with, "<!--") || strings.Contains(with, "{{") || strings.Contains(with, "\n\n\n") {
		t.Errorf("the index of a checkout leaves a marker, a placeholder or a doubled blank line in it:\n%s", with)
	}
}

func TestALibraryWithoutCommandsPublishesNoCommandsSection(t *testing.T) {
	published := func(without bool) map[string]string {
		project := t.TempDir()
		library := At(project, config.Config{})
		if without {
			library = library.WithoutCommands()
		}

		if _, err := library.Publish(); err != nil {
			t.Fatal(err)
		}

		return tree(t, project)
	}

	withCommands := 0

	for path, text := range published(false) {
		if strings.Contains(text, commandsHeading+"\n") && filepath.Base(path) == "SKILL.md" {
			withCommands++
		}
	}

	if withCommands == 0 {
		t.Fatal("no published skill has a Commands section, so this test proves nothing")
	}

	skills := 0

	for path, text := range published(true) {
		if filepath.Base(path) != "SKILL.md" {
			continue
		}

		skills++

		if strings.Contains(text, commandsHeading+"\n") {
			t.Errorf("%s still has its Commands section:\n%s", path, text)
		}

		if filepath.Base(filepath.Dir(path)) == MapID {
			namesNoCommand(t, path, text)
		}
	}

	if skills < withCommands {
		t.Errorf("%d skills were published without commands, where %d were published with them", skills, withCommands)
	}
}
