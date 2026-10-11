package backend_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine/php"
	_ "github.com/jessegall/code-commandments/engine/php/laravel"
)

// body is a method body long enough to be a duplicate rather than a coincidence.
const body = `        $rows = [];
        foreach ($this->entries as $entry) {
            $value = $entry->value();
            if ($value === null) {
                continue;
            }
            $rows[$entry->key()] = [
                'value' => $value,
                'at' => $entry->at(),
                'kind' => $entry->kind(),
            ];
        }

        return $rows;`

// TestADuplicateOfAFrozenFileLeavesTheLiveSiteAlone holds a recurrence to the sites that can change. A migration is
// frozen, so it is read but never a target; a method whose only twin lives there has nothing to be merged with, and
// flagging the one site that can change asks for a merge that cannot happen. Two live copies are still a duplicate.
func TestADuplicateOfAFrozenFileLeavesTheLiveSiteAlone(t *testing.T) {
	model := "<?php\n\nfinal class Message\n{\n    private array $entries = [];\n\n    public function telemetry(): array\n    {\n" + body + "\n    }\n}\n"
	migration := "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class extends Migration\n{\n    private array $entries = [];\n\n    private function telemetry(): array\n    {\n" + body + "\n    }\n};\n"
	other := "<?php\n\nfinal class Reading\n{\n    private array $entries = [];\n\n    public function telemetry(): array\n    {\n" + body + "\n    }\n}\n"

	t.Run("the only twin is frozen", func(t *testing.T) {
		found := findingsIn(t, map[string]string{"Message.php": model, "2026_10_09_104000_backfill.php": migration})
		if len(found) != 0 {
			t.Errorf("the live method is flagged for repeating a frozen one: %v", found)
		}
	})

	t.Run("two live copies are still a duplicate", func(t *testing.T) {
		found := findingsIn(t, map[string]string{"Message.php": model, "Reading.php": other})
		if len(found) != 2 {
			t.Errorf("two live copies read as %d findings, want 2: %v", len(found), found)
		}
	})
}

// findingsIn is where DuplicateFunctionDetector fires over the written files.
func findingsIn(t *testing.T, files map[string]string) []string {
	t.Helper()
	root := t.TempDir()
	for name, source := range files {
		if err := os.WriteFile(filepath.Join(root, name), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	codebase, err := php.Here().Scan(root)
	if err != nil {
		t.Fatal(err)
	}
	var found []string
	for _, match := range (backend.DuplicateFunctionDetector{}).Find(codebase) {
		found = append(found, match.Location())
	}

	return found
}
