package judge

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/source"
)

func TestAJudgeSearchesTheProjectForALanguagesManifestOnceHoweverManyRulesJudgeThatLanguage(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	t.Setenv(pluginData, t.TempDir())
	project := emptyProject(t)
	cart := "<?php\n\nfinal class Cart\n{\n    public function total(): int\n    {\n        return 1;\n    }\n}\n"
	if err := os.WriteFile(filepath.Join(project, "cart.php"), []byte(cart), 0o644); err != nil {
		t.Fatal(err)
	}

	before := source.ManifestSearches()
	if code, _, warned := judgedIn(t, project, ".", "--no-checklist"); code > 1 {
		t.Fatalf("the judge exits %d: %s", code, warned)
	}

	if searched := source.ManifestSearches() - before; searched > int64(len(source.Languages)) {
		t.Errorf("one judge walked the whole project %d times looking for a manifest, where each of the %d languages is asked once and never once for each rule", searched, len(source.Languages))
	}
}
