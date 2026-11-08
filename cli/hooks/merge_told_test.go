package hooks

import (
	"encoding/json"
	"path/filepath"
	"strconv"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

func TestAMergeOfManyFilesIsOneThingToReadNamingEverySin(t *testing.T) {
	root, before, _ := mergedProject(t)

	for each := 0; each < 12; each++ {
		wroteInto(t, filepath.Join(root, "src", "many"+strconv.Itoa(each)+".py"), olderSin)
	}

	commitIn(t, root, "a merge of many files")

	said := MergeLanded(mergeLine(root, "main", before, git.Head(root)))

	if said == "" {
		t.Fatal("a merge of twelve sinful files was answered with nothing")
	}

	var answer map[string]any
	if err := json.Unmarshal([]byte(said), &answer); err != nil {
		t.Fatalf("the merge was answered with something that is not a line of JSON: %v", err)
	}

	if cards, held := answer["cards"].([]any); held && len(cards) > 1 {
		t.Errorf("the merge raised %d cards, and it must be one thing to read", len(cards))
	}

	tell := asText(answer["tell"])

	named := strings.Count(tell, " at src/")

	if named < 12 {
		t.Errorf("the merge named %d sins by file and line of twelve sinful files, and a worker cannot run the judge to read the rest:\n%s", named, tell)
	}
}

func TestAMergeNamesNoSinInAFileTheProjectExcludes(t *testing.T) {
	root, before, _ := mergedProject(t)

	wroteInto(t, filepath.Join(root, ".commandments", "config.json"), `{"paths": ["src"], "exclude": ["**/dist", "src/generated"], "detectors": ["DocumentedAnywhereDetector"]}`)
	wroteInto(t, filepath.Join(root, "src", "web", "dist", "bundle.py"), olderSin)
	wroteInto(t, filepath.Join(root, "src", "generated", "made.py"), olderSin)
	commitIn(t, root, "a merge carrying built and generated files")

	said := MergeLanded(mergeLine(root, "main", before, git.Head(root)))

	if !strings.Contains(said, "src/brought.py") {
		t.Errorf("the sin in the merged source was not named:\n%s", said)
	}

	for _, never := range []string{"bundle.py", "made.py"} {
		if strings.Contains(said, never) {
			t.Errorf("the merge named a sin in %s, which the project excludes:\n%s", never, said)
		}
	}
}

func TestTheMergeAndTheHandInUseTheSameWords(t *testing.T) {
	root, _ := workerProject(t)

	marks := []SinMark{}

	if told := mergeTold("worker 7", ", on task 412", root, marks); told != "" {
		t.Errorf("no sins at all is said as %q", told)
	}

	if from, gate := mergeTold("worker 7", "", root, marks), mergeReason("worker 7", marks, root); from != gate {
		t.Errorf("the merge says %q and the hand-in says %q, and they are one form", from, gate)
	}
}
