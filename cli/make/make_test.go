package make

import (
	"bytes"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	_ "github.com/jessegall/code-commandments/registry"
)

// run runs make in a fresh project and answers the project, what it said and its exit code.
func run(t *testing.T, root string, args ...string) (string, int) {
	t.Helper()

	previous, _ := os.Getwd()
	t.Cleanup(func() { os.Chdir(previous) })

	if err := os.Chdir(root); err != nil {
		t.Fatal(err)
	}

	var out, errs bytes.Buffer

	code, err := Command{}.Run(cli.InputOf("make", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatal(err)
	}

	return out.String() + errs.String(), code
}

func TestANewCommandmentIsARuleAndItsSkillTurnedOn(t *testing.T) {
	root := t.TempDir()

	said, code := run(t, root, "NoRawSqlDetector", "--engine=python")
	if code != 0 {
		t.Fatalf("exit %d\n%s", code, said)
	}

	for _, file := range []string{".commandments/custom/NoRawSqlDetector.json", ".commandments/custom/skills/no-raw-sql/SKILL.md"} {
		if _, err := os.Stat(filepath.Join(root, file)); err != nil || !strings.Contains(said, file) {
			t.Errorf("%s not written and named\n%s", file, said)
		}
	}

	loaded := custom.Load(root)
	if len(loaded.Rules) != 1 || len(loaded.Unreadable) != 0 || loaded.Rules[0].Sin().Definition().Slug() != "no-raw-sql" || string(loaded.Rules[0].Engine()) != "python" {
		t.Fatalf("the scaffold is not a rule the binary runs: %+v", loaded)
	}

	project, _ := config.Load(root)
	if !slices.Equal(project.Detectors, []string{"NoRawSqlDetector"}) || len(loaded.Enabled(project)) != 1 {
		t.Errorf("not turned on: %+v", project.Detectors)
	}

	if again, code := run(t, root, "NoRawSql", "--engine=python"); code != 2 || !strings.Contains(again, "Already there") {
		t.Errorf("overwrote without --force: %d\n%s", code, again)
	}

	if forced, code := run(t, root, "NoRawSql", "--engine=python", "--force"); code != 0 || !strings.Contains(forced, "already turned on") {
		t.Errorf("forced: %d\n%s", code, forced)
	}
}

func TestACommandmentTaughtByAnExistingSkillWritesNone(t *testing.T) {
	root := t.TempDir()

	said, code := run(t, root, "LooseNull", "--skill=absence")
	if code != 0 || strings.Contains(said, "SKILL.md") {
		t.Fatalf("exit %d\n%s", code, said)
	}

	if loaded := custom.Load(root); len(loaded.Rules) != 1 || loaded.Rules[0].Sin().Definition().Slug() != "backend/absence" {
		t.Errorf("%+v", loaded)
	}
}

func TestAWrongInvocationSaysWhy(t *testing.T) {
	for args, reason := range map[string]string{
		"":                     "name the commandment",
		"--- ---":              "name the commandment",
		"Thing --engine=cobol": "unknown --engine=cobol",
	} {
		said, code := run(t, t.TempDir(), strings.Fields(args)...)
		if code != 2 || !strings.Contains(said, reason) {
			t.Errorf("%q: %d\n%s", args, code, said)
		}
	}
}

func TestTheEdgeCasesOfANameAndASlug(t *testing.T) {
	for name, want := range map[string]struct {
		args  []string
		files []string
		sin   string
	}{
		"the Detector suffix is dropped once": {[]string{"HTTPClientDetector"}, []string{"HTTPClientDetector.json", "skills/http-client/SKILL.md"}, "http-client"},
		"a name that is its skill's":          {[]string{"Naming"}, []string{"NamingDetector.json", "skills/naming/SKILL.md"}, "naming"},
		"a slug with a slash is one folder":   {[]string{"Thing", "--skill=team/house-rules"}, []string{"ThingDetector.json", "skills/team-house-rules/SKILL.md"}, "thing"},
	} {
		root := t.TempDir()

		if said, code := run(t, root, want.args...); code != 0 {
			t.Fatalf("%s: exit %d\n%s", name, code, said)
		}

		for _, file := range want.files {
			if _, err := os.Stat(filepath.Join(root, ".commandments", "custom", file)); err != nil {
				t.Errorf("%s: %s not written", name, file)
			}
		}

		if loaded := custom.Load(root); len(loaded.Rules) != 1 || loaded.Rules[0].Sin().Definition().Name != want.sin {
			t.Errorf("%s: %+v", name, loaded)
		}
	}
}
