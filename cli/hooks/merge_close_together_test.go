package hooks

import (
	"path/filepath"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestTwoMergesCloseTogetherJudgeTheWholeRangeOnce(t *testing.T) {
	root, before, after := mergedProject(t)

	wroteInto(t, filepath.Join(root, "src", "second.py"), sinOnLineFive)
	commitIn(t, root, "the work of the second merge")

	later := git.Head(root)

	said := make([]string, 2)

	var both sync.WaitGroup
	both.Add(2)

	go func() {
		defer both.Done()
		said[0] = MergeLanded(mergeLine(root, "main", before, after))
	}()

	go func() {
		defer both.Done()
		said[1] = MergeLanded(mergeLine(root, "main", after, later))
	}()

	both.Wait()

	whole := strings.Join(said, "\n")

	for _, named := range []string{"brought.py", "second.py"} {
		if !strings.Contains(whole, named) {
			t.Errorf("neither of the two merges named %s, so a range was skipped:\n%s", named, whole)
		}

		if strings.Count(whole, named+":") > 1 {
			t.Errorf("%s was judged more than once across the two merges:\n%s", named, whole)
		}
	}

	if kept := MergedIn(workspace.At(root, "")).Upto("main"); kept != later {
		t.Errorf("after both merges the mark says %q, and the later of the two ended at %s", kept, later)
	}
}

func TestTheSameMergeDeliveredTwiceAtOnceIsJudgedOnce(t *testing.T) {
	root, before, after := mergedProject(t)

	said := make([]string, 2)

	var both sync.WaitGroup
	both.Add(2)

	for each := range said {
		go func(each int) {
			defer both.Done()
			said[each] = MergeLanded(mergeLine(root, "main", before, after))
		}(each)
	}

	both.Wait()

	answered := 0
	for _, each := range said {
		if each != "" {
			answered++
		}
	}

	if answered != 1 {
		t.Errorf("the same merge delivered twice at once was answered %d times:\n%s", answered, strings.Join(said, "\n"))
	}
}
