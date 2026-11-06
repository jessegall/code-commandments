package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

func crossFileProject(t *testing.T) (root, before string) {
	t.Helper()

	root, _ = workerProject(t)

	for path, text := range map[string]string{
		".commandments/config.json": `{"paths": ["src"], "detectors": ["KindOfThingDetector"]}`,
		".commandments/custom/KindOfThingDetector.json": `{"engine": "python",
 "sin": {"name": "kind-of-thing", "description": "a class the chain of its parents says is a kind", "skill": "python/absence"},
 "find": {"select": "type-declaration", "where": [{"extendsAny": "Kind"}]}}`,
		"src/kinds.py": "class Kind:\n    number = 0\n",
	} {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}

		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	commitIn(t, root, "a project with a rule that reads across files")

	return root, git.Head(root)
}

func TestAMergeIsJudgedByTheRulesThatReadAcrossFiles(t *testing.T) {
	root, before := crossFileProject(t)

	wroteInto(t, filepath.Join(root, "src", "rebuild.py"),
		"from src.kinds import Kind\n\n\nclass RebuildKind(Kind):\n    statements: list[str] = []\n")

	commitIn(t, root, "the work a worker merged")

	said := MergeLanded(mergeLine(root, "main", before, git.Head(root)))

	if said == "" {
		t.Fatal("a merge bringing in a sin that only a rule reading across files can find was answered with nothing")
	}

	if !strings.Contains(said, "rebuild.py") {
		t.Errorf("the merge does not name the file whose parent chain makes it a kind: %s", said)
	}

	if !strings.Contains(said, "kind-of-thing") {
		t.Errorf("the merge does not name the sin only a cross-file rule finds: %s", said)
	}
}

func TestTheMergedTextIsWhatTheCrossFileRuleReads(t *testing.T) {
	root, before := crossFileProject(t)

	wroteInto(t, filepath.Join(root, "src", "rebuild.py"),
		"class RebuildKind:\n    statements: list[str] = []\n")

	commitIn(t, root, "a class that is no kind yet")

	between := git.Head(root)

	if said := MergeLanded(mergeLine(root, "main", before, between)); strings.Contains(said, "kind-of-thing") {
		t.Fatalf("a class that extends nothing was called a kind: %s", said)
	}

	wroteInto(t, filepath.Join(root, "src", "rebuild.py"),
		"from src.kinds import Kind\n\n\nclass RebuildKind(Kind):\n    statements: list[str] = []\n")

	commitIn(t, root, "the merge that makes it a kind")

	said := MergeLanded(mergeLine(root, "main", between, git.Head(root)))

	if !strings.Contains(said, "kind-of-thing") {
		t.Errorf("the second merge read the text from before it, so the parent it just gained was invisible: %s", said)
	}
}
