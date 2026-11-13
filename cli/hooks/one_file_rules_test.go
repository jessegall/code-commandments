package hooks

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/detectors/typescript"
)

func TestARuleOfTheTypeScriptEngineJudgesAnEditedTypeScriptFile(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	file := filepath.Join(root, "late.ts")
	sample := "export function drain(items: string[]): void {\n  for (const item of items) {\n    items.shift()\n  }\n}\n"

	if err = os.WriteFile(file, []byte(sample), 0o644); err != nil {
		t.Fatal(err)
	}

	rules := []detectors.Detector{typescript.CollectionMutatedWhileIteratedDetector{}}

	marks, _, err := marksIn(Marks(root), file, rules, git.ChangedLines{})
	if err != nil {
		t.Fatal(err)
	}

	if len(marks) != 1 {
		t.Errorf("an edited TypeScript file got %d marks from a rule of its own engine, where it holds one sin", len(marks))
	}
}
