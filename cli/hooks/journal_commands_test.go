package hooks

import (
	"io/fs"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func renderedCommandsSections(t *testing.T, chosen string) (sections, skills int) {
	t.Helper()

	plugin := t.TempDir()
	t.Setenv(journalPluginDir, plugin)
	t.Setenv(journalProject, t.TempDir())
	t.Setenv(journalSettings, chosen)

	if _, err := renderSkills(); err != nil {
		t.Fatal(err)
	}

	_ = filepath.WalkDir(filepath.Join(plugin, skillsFolder), func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() || entry.Name() != "SKILL.md" {
			return nil
		}

		text, _ := os.ReadFile(path)
		skills++

		if strings.Contains(string(text), "\n## Commands\n") {
			sections++
		}

		return nil
	})

	return sections, skills
}

func TestTheSkillsCarryTheirCommandsOnlyWhereWorkersMayRunTheCLI(t *testing.T) {
	for name, chosen := range map[string]string{
		"workers_cli off":    `{"workers_cli":"false"}`,
		"workers_cli absent": `{"language_php":"true"}`,
		"no settings at all": "",
	} {
		t.Run(name, func(t *testing.T) {
			if sections, skills := renderedCommandsSections(t, chosen); skills == 0 || sections != 0 {
				t.Errorf("%d of %d rendered skills carry a Commands section, where none may", sections, skills)
			}
		})
	}

	t.Run("workers_cli on", func(t *testing.T) {
		if sections, skills := renderedCommandsSections(t, `{"workers_cli":"true"}`); sections == 0 || sections >= skills {
			t.Errorf("%d of %d rendered skills carry a Commands section, where the disciplines carry theirs and the index has none", sections, skills)
		}
	})
}

func TestTheSkillsAreRenderedIntoTheFolderTheJournalNames(t *testing.T) {
	plugin := t.TempDir()
	t.Setenv(journalPluginDir, plugin)
	t.Setenv(journalProject, t.TempDir())

	rendered, err := renderSkills()
	if err != nil {
		t.Fatal(err)
	}
	skills, _ := os.ReadDir(filepath.Join(plugin, skillsFolder, ".agents", "skills"))
	if rendered == 0 || len(skills) == 0 {
		t.Errorf("rendered %d skills, and %d are in the plugin's folder", rendered, len(skills))
	}
}
