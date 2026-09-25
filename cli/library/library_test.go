package library

import (
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"reflect"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/source"
	_ "github.com/jessegall/code-commandments/registry"
)

// publishedByPHP runs the PHP tool's own Library over a fresh project that does not write the languages,
// and answers the ids it published.
func publishedByPHP(t *testing.T, project string, disabled []source.Language) []string {
	t.Helper()

	if _, err := exec.LookPath("php"); err != nil {
		t.Skip("no php to publish the PHP tool's library with")
	}

	repo, err := filepath.Abs("../..")
	if err != nil {
		t.Fatal(err)
	}

	var cases []string
	for _, language := range disabled {
		cases = append(cases, `\JesseGall\CodeCommandments\Language::from('`+string(language)+`')`)
	}

	script := `require '` + repo + `/vendor/autoload.php';
$library = new \JesseGall\CodeCommandments\Skills\Library(
    \JesseGall\CodeCommandments\Workspace::at('` + project + `'),
    new \JesseGall\CodeCommandments\Languages(` + strings.Join(cases, ", ") + `),
);
echo implode("\n", $library->publish('` + repo + `'));`

	out, err := exec.Command("php", "-r", script).CombinedOutput()
	if err != nil {
		t.Fatalf("php: %v\n%s", err, out)
	}

	return strings.Split(string(out), "\n")
}

func TestTheLibraryPublishesWhatThePHPToolPublishes(t *testing.T) {
	// C# is left out of every case until the Go side carries the C# skills (ticket 7).
	for name, disabled := range map[string][]source.Language{
		"every language but C#":   {source.CSharp},
		"no Python either":        {source.CSharp, source.Python},
		"the backend only":        {source.CSharp, source.Python, source.Vue, source.TypeScript},
		"the frontend and Python": {source.CSharp, source.PHP},
	} {
		t.Run(name, func(t *testing.T) {
			php, golang := t.TempDir(), t.TempDir()
			wantIDs := publishedByPHP(t, php, disabled)

			ids, err := At(golang, config.Config{DisabledLanguages: disabled}).Publish()
			if err != nil {
				t.Fatal(err)
			}

			if !reflect.DeepEqual(ids, wantIDs) {
				t.Errorf("published\n%v\nthe PHP tool published\n%v", ids, wantIDs)
			}

			want, got := tree(t, php), tree(t, golang)

			for path, contents := range want {
				if got[path] != contents {
					t.Errorf("%s differs from the PHP tool's", path)
				}
			}

			for path := range got {
				if _, published := want[path]; !published {
					t.Errorf("%s is not one the PHP tool publishes", path)
				}
			}
		})
	}
}

func TestASkillTheLastSyncPublishedAndThisOneDoesNotIsTakenAway(t *testing.T) {
	project := t.TempDir()

	_, err := At(project, config.Config{DisabledLanguages: []source.Language{source.CSharp}}).Publish()
	if err != nil {
		t.Fatal(err)
	}

	mine := filepath.Join(project, Dir, "commandments-mine")
	if err := os.MkdirAll(mine, 0o755); err != nil {
		t.Fatal(err)
	}

	if _, err := At(project, config.Config{DisabledLanguages: []source.Language{source.CSharp, source.Python}}).Publish(); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(filepath.Join(project, Dir, "commandments-python-absence")); err == nil {
		t.Error("a Python skill outlived the project dropping Python")
	}

	if _, err := os.Stat(mine); err != nil {
		t.Error("a skill the tool never published was taken away")
	}
}

// tree is every file under the project's library and shared folder, by its path under the project.
func tree(t *testing.T, project string) map[string]string {
	t.Helper()
	files := map[string]string{}

	filepath.WalkDir(project, func(path string, entry fs.DirEntry, err error) error {
		if err == nil && entry.Type().IsRegular() {
			contents, _ := os.ReadFile(path)
			relative, _ := filepath.Rel(project, path)
			files[relative] = string(contents)
		}

		return nil
	})

	return files
}

func TestAProjectsOwnSkillIsPublishedAndBriefedAsItsOwn(t *testing.T) {
	project := t.TempDir()
	dir := filepath.Join(project, ".commandments", "custom", "skills", "no-raw-sql")

	if err := os.MkdirAll(filepath.Join(dir, "reference"), 0o755); err != nil {
		t.Fatal(err)
	}

	skill := "---\nname: No raw SQL\ndescription: before a query.\nsummary: queries go through the repository.\ntier: mandatory\nlanguages: [php]\n---\n# No raw SQL\n"
	os.WriteFile(filepath.Join(dir, "SKILL.md"), []byte(skill), 0o644)
	os.WriteFile(filepath.Join(dir, "reference", "examples.md"), []byte("examples"), 0o644)

	ids, err := At(project, config.Config{DisabledLanguages: []source.Language{source.CSharp}}).Publish()
	if err != nil || ids[len(ids)-1] != "commandments-no-raw-sql" {
		t.Fatalf("%v %v", ids, err)
	}

	for file, want := range map[string]string{"SKILL.md": skill, "reference/examples.md": "examples"} {
		if got, _ := os.ReadFile(filepath.Join(project, Dir, "commandments-no-raw-sql", file)); string(got) != want {
			t.Errorf("%s published as %q", file, got)
		}
	}

	bullet := "- **`commandments-no-raw-sql`** — queries go through the repository. _(this project's own — `.commandments/custom/`)_"
	if briefing := Briefing(project, config.Config{}); !strings.Contains(briefing, bullet) {
		t.Errorf("the briefing does not name the project's own skill")
	}

	if briefing := Briefing(project, config.Config{DisabledLanguages: []source.Language{source.PHP}}); strings.Contains(briefing, "no-raw-sql") {
		t.Errorf("a skill for a language the project does not write is briefed")
	}
}
