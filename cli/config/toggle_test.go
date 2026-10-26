package config

import (
	"bytes"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/detectors"
	_ "github.com/jessegall/code-commandments/registry"
)

// What `disable` and `enable` answer was proved only by the recorded parity cases, which run the built binary. The
// command reads the registered rules and writes the project's own config, both of which this package already owns,
// so its answers are held here: a name nobody has, a name that could mean several, the config it writes, and the
// quiet answer when there is nothing to do.

// inAProject runs the rest of the test inside an empty project of its own.
func inAProject(t *testing.T) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	was, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	if err := os.Chdir(root); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { os.Chdir(was) })

	return root
}

// toggled is what the verb answered for the name, and what it said.
func toggled(verb, name string) (code int, said string, warned string) {
	var out, errs bytes.Buffer
	code, _ = Toggle{}.Run(cli.InputOf(verb, name), cli.Console{Out: &out, Err: &errs})

	return code, out.String(), errs.String()
}

// TestANameNobodyHasIsRefusedAndSaysWhereToLook holds the answer to a mistyped rule: a reader who typed it wrong is
// told so and pointed at the list, rather than left with a config that silently changed nothing.
func TestANameNobodyHasIsRefusedAndSaysWhereToLook(t *testing.T) {
	root := inAProject(t)

	code, _, warned := toggled("disable", "zz-nothing-like-it")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "zz-nothing-like-it") || !strings.Contains(warned, "--list") {
		t.Errorf("it says %q, which does not name what was typed and where to look", warned)
	}
	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err == nil {
		t.Error("a name nobody has wrote a config anyway")
	}
}

// TestANameThatCouldMeanSeveralNamesThemAll holds the ambiguous case: the command never chooses one of them for
// the reader, since turning off the wrong rule is silent until something is missed.
func TestANameThatCouldMeanSeveralNamesThemAll(t *testing.T) {
	inAProject(t)

	code, _, warned := toggled("disable", "python-dict")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "more than one") {
		t.Errorf("it says %q, which does not say the name is ambiguous", warned)
	}
}

// TestDisablingASinWritesItIntoTheProjectsOwnConfig holds the whole point of the verb: the rule is off, and the
// project's config says so in the shape the schema describes.
func TestDisablingASinWritesItIntoTheProjectsOwnConfig(t *testing.T) {
	root := inAProject(t)

	code, said, warned := toggled("disable", "swallow-catch")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, warned)
	}
	if !strings.Contains(said, "swallow-catch") {
		t.Errorf("it does not say what it turned off: %q", said)
	}

	if _, err := os.ReadFile(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Fatalf("nothing was written: %v", err)
	}
	if finds(t, root, "swallow-catch") {
		t.Error("the config was written and the rule still runs")
	}
}

// finds says whether a rule for the sin is still among the ones this project runs, which is what turning one off
// has to change. It is asked that way rather than by reading the config's own spelling, because what the reader
// cares about is whether the rule still judges them.
func finds(t *testing.T, root, sin string) bool {
	t.Helper()
	project, err := Load(root)
	if err != nil {
		t.Fatal(err)
	}
	enabled, err := project.Enabled(Everything)
	if err != nil {
		t.Fatal(err)
	}

	return slices.ContainsFunc(enabled, func(rule detectors.Detector) bool {
		return rule.Sin().Definition().Name == sin
	})
}

// TestTurningOffWhatIsAlreadyOffChangesNothingAndSaysSo holds the second run: a reader who repeats the command gets
// told there was nothing to do, and the file is left exactly as it was.
func TestTurningOffWhatIsAlreadyOffChangesNothingAndSaysSo(t *testing.T) {
	root := inAProject(t)
	path := filepath.Join(root, ".commandments", "config.json")

	if code, _, warned := toggled("disable", "swallow-catch"); code != 0 {
		t.Fatalf("the first run exits %d, saying %q", code, warned)
	}
	before, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}

	code, said, _ := toggled("disable", "swallow-catch")

	if code != 0 {
		t.Errorf("exits %d, want 0: it is already as the reader asked for", code)
	}
	if !strings.Contains(said, "nothing to do") {
		t.Errorf("it says %q, which does not say there was nothing to do", said)
	}

	after, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	if string(after) != string(before) {
		t.Errorf("the config was rewritten for a change that was not one:\n%s", after)
	}
}

// TestTurningOnWhatWasNeverOffSaysSo holds the other direction, which is how a reader finds out that the rule they
// thought they had silenced was never silenced at all.
func TestTurningOnWhatWasNeverOffSaysSo(t *testing.T) {
	inAProject(t)

	code, said, warned := toggled("enable", "swallow-catch")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, warned)
	}
	if !strings.Contains(said, "was not disabled") {
		t.Errorf("it says %q, which does not say the rule was never off", said)
	}
}

// TestASkillIsTurnedOffBySlug holds the wider stroke: a skill names every sin it teaches the fix for, so silencing
// it has to be written as the skill rather than as the sins it happens to hold today.
func TestASkillIsTurnedOffBySlug(t *testing.T) {
	root := inAProject(t)

	code, said, warned := toggled("disable", "python/absence")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, warned)
	}
	if !strings.Contains(said, "absence") {
		t.Errorf("it does not say what it turned off: %q", said)
	}

	if finds(t, root, "python-blank-string-default") {
		t.Error("a sin the silenced skill teaches the fix for still runs")
	}
}
