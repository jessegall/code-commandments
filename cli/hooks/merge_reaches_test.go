package hooks

import (
	"strconv"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestWhatAMergeBroughtInReachesTheAgentHoweverTheMergeWasRun(t *testing.T) {
	for _, told := range []struct {
		what string
		line func(root, before, after string) map[string]any
	}{
		{
			"a merge the agent ran itself",
			func(root, before, after string) map[string]any {
				return mergeLine(root, "main", before, after)
			},
		},
		{
			"a merge another agent ran",
			func(root, before, after string) map[string]any {
				line := mergeLine(root, "main", before, after)
				line["agent"] = 9
				row, _ := line["data"].(map[string]any)["row"].(map[string]any)
				row["session_agent_id"] = "worker-9"

				return line
			},
		},
		{
			"a merge a person ran at a terminal",
			func(root, before, after string) map[string]any {
				line := mergeLine(root, "main", before, after)
				delete(line, "agent")
				row, _ := line["data"].(map[string]any)["row"].(map[string]any)
				delete(row, "session_agent_id")

				return line
			},
		},
	} {
		root, before, after := mergedProject(t)

		said := MergeLanded(told.line(root, before, after))

		if said == "" {
			t.Errorf("%s was answered with nothing", told.what)

			continue
		}

		if !strings.Contains(said, "brought.py") {
			t.Errorf("%s does not name what it brought in: %s", told.what, said)
		}

		found := MergesIn(workspace.At(root, "")).Found()
		if len(found) == 0 {
			t.Errorf("%s left nothing where a person could read it, and the journal drops a line it has nobody to tell", told.what)

			continue
		}

		if !strings.Contains(found[len(found)-1], "brought.py") {
			t.Errorf("%s kept something that does not name what it brought in: %s", told.what, found[len(found)-1])
		}

		if !strings.Contains(found[len(found)-1], "\n") {
			t.Errorf("%s kept its words without their lines: %s", told.what, found[len(found)-1])
		}
	}
}

func TestOnlyTheLastFewMergesAreKept(t *testing.T) {
	root, before, _ := mergedProject(t)

	space := workspace.At(root, "")

	for each := 0; each < mergesKept+5; each++ {
		if err := MergesIn(space).Keeps("main", git.Head(root), "what merge "+strconv.Itoa(each)+" brought in"); err != nil {
			t.Fatal(err)
		}
	}

	found := MergesIn(space).Found()

	if len(found) > mergesKept {
		t.Errorf("%d merges are kept, and the file holds the last %d", len(found), mergesKept)
	}

	if !strings.Contains(found[len(found)-1], strconv.Itoa(mergesKept+4)) {
		t.Errorf("the newest merge kept is %q", found[len(found)-1])
	}

	_ = before
}
