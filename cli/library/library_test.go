package library

import (
	"io/fs"
	"os"
	"path/filepath"
	"reflect"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/source"
	_ "github.com/jessegall/code-commandments/registry"
)

// TestTheLibraryPublishesWhatThePHPToolPublishes holds the library to what the PHP tool published for each set of
// languages a project writes, recorded under testdata/published: the ids it named and the files it wrote. What a
// skill says is the tool's own and is not pinned here.
func TestTheLibraryPublishesWhatThePHPToolPublishes(t *testing.T) {
	for name, disabled := range map[string][]source.Language{
		"every-language":      nil,
		"no-csharp":           {source.CSharp},
		"no-python":           {source.CSharp, source.Python},
		"backend-only":        {source.CSharp, source.Python, source.Vue, source.TypeScript},
		"frontend-and-python": {source.CSharp, source.PHP},
	} {
		t.Run(name, func(t *testing.T) {
			project := t.TempDir()
			if err := os.WriteFile(filepath.Join(project, "composer.json"), []byte("{}"), 0o644); err != nil {
				t.Fatal(err)
			}

			ids, err := At(project, config.Config{DisabledLanguages: disabled}).Publish()
			if err != nil {
				t.Fatal(err)
			}

			record(t, name+".ids", ids)

			if want := recorded(t, name+".ids"); !reflect.DeepEqual(ids, want) {
				t.Errorf("published\n%v\nthe PHP tool published\n%v", ids, want)
			}

			var paths []string
			for path := range tree(t, project) {
				paths = append(paths, path)
			}
			slices.Sort(paths)

			record(t, name+".paths", paths)

			if want := recorded(t, name+".paths"); !reflect.DeepEqual(paths, want) {
				t.Errorf("wrote\n%v\nthe PHP tool wrote\n%v", paths, want)
			}
		})
	}
}

// recorded is a list the PHP tool left in testdata/published, a line an entry.
// record writes what a set published as its recording, so a curriculum that gains or renames a skill is
// re-recorded deliberately rather than by hand: run the test with COMMANDMENTS_RECORD=1 and read the diff.
func record(t *testing.T, name string, lines []string) {
	t.Helper()

	if os.Getenv("COMMANDMENTS_RECORD") == "" {
		return
	}

	if err := os.WriteFile(filepath.Join("testdata", "published", name), []byte(strings.Join(lines, "\n")+"\n"), 0o644); err != nil {
		t.Fatal(err)
	}
}

func recorded(t *testing.T, name string) []string {
	t.Helper()

	contents, err := os.ReadFile(filepath.Join("testdata", "published", name))
	if err != nil {
		t.Fatal(err)
	}

	return strings.Split(strings.TrimSuffix(string(contents), "\n"), "\n")
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

// TestAPageASkillNoLongerShipsIsTakenAway holds a sync to publishing a skill as it stands: a reference page an
// earlier version published and this one does not is removed, never left for an agent to read as current.
func TestAPageASkillNoLongerShipsIsTakenAway(t *testing.T) {
	project := t.TempDir()
	if _, err := At(project, config.Config{}).Publish(); err != nil {
		t.Fatal(err)
	}
	stale := filepath.Join(project, Dir, "commandments-php-absence", "reference", "retired.md")
	if err := os.MkdirAll(filepath.Dir(stale), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(stale, []byte("what the skill once said\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if _, err := At(project, config.Config{}).Publish(); err != nil {
		t.Fatal(err)
	}
	if _, err := os.Stat(stale); err == nil {
		t.Error("a page the skill no longer ships outlived the sync")
	}
	if _, err := os.Stat(filepath.Join(project, Dir, "commandments-php-absence", "SKILL.md")); err != nil {
		t.Errorf("the skill itself is gone: %v", err)
	}
}

func TestAProjectsOwnSkillIsPublishedIntoALibraryElsewhere(t *testing.T) {
	project := t.TempDir()
	plugin := t.TempDir()
	dir := filepath.Join(project, ".commandments", "custom", "skills", "rows-through-their-repository")

	if err := os.MkdirAll(dir, 0o755); err != nil {
		t.Fatal(err)
	}

	own := "---\nname: Rows through their repository\ndescription: before a query.\nsummary: rows are read through their repository.\ntier: mandatory\nlanguages: [php]\n---\n# Rows through their repository\n"
	os.WriteFile(filepath.Join(dir, "SKILL.md"), []byte(own), 0o644)

	ids, err := For(plugin, project, config.Config{}).Publish()
	if err != nil {
		t.Fatal(err)
	}

	if !slices.Contains(ids, "commandments-rows-through-their-repository") {
		t.Fatalf("the project's own skill is not published: %v", ids)
	}

	if got, _ := os.ReadFile(filepath.Join(plugin, Dir, "commandments-rows-through-their-repository", "SKILL.md")); string(got) != own {
		t.Errorf("published as %q", got)
	}
}

