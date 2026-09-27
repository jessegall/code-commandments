package hooks

import (
	"os"
	"path/filepath"
	"testing"
)

// TestTheSkillsAreRenderedIntoTheFolderTheJournalNames holds the plugin's skills to the plugin's folder when the
// binary lives outside every plugin folder: the folder the journal names is the one written.
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
