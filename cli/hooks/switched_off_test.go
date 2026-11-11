package hooks

import (
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

func TestEachPieceCanBeSwitchedOffByItself(t *testing.T) {
	t.Run("a worker's own judging off leaves the main agent's judging alone", func(t *testing.T) {
		root, data := workerProject(t)
		t.Setenv(pluginData, data)
		settingsOf(t, map[string]any{liveEditsKey: "true", workersKey: "false"})

		under := filepath.Join("src", "made.py")
		wroteInto(t, filepath.Join(root, under), olderSin)

		worker := Answer4Of(map[string]any{
			"v": 1, "event": "file_edited", "agent": 7, "cwd": root, "role": "worker",
			"data": map[string]any{"cwd": root, "kind": "create", "path": under},
		})

		if strings.Contains(worker, workersKey) || strings.Contains(worker, "made.py") {
			t.Errorf("a worker with its judging off was told something: %s", worker)
		}

		main := Answer4Of(map[string]any{
			"v": 1, "event": "file_edited", "agent": 1, "cwd": root, "role": "main",
			"data": map[string]any{"cwd": root, "kind": "create", "path": under},
		})

		if strings.Contains(main, workersKey) {
			t.Errorf("switching a worker's judging off also turned the main agent away: %s", main)
		}

		if !strings.Contains(main, "redundant-python-else") && !strings.Contains(main, "documented-anywhere") {
			t.Errorf("the main agent's own edit found nothing with workers switched off: %s", main)
		}
	})

	t.Run("the merge judging off leaves an edit judged", func(t *testing.T) {
		root, data := mergeOffProject(t)
		settingsOf(t, map[string]any{mergesKey: "false"})

		before := git.Head(root)

		wroteInto(t, filepath.Join(root, "src", "brought.py"), olderSin)
		commitIn(t, root, "work a merge brought in")

		if said := MergeLanded(mergeLine(root, "main", before, git.Head(root))); said != "" {
			t.Errorf("a merge was judged with the merge judging switched off: %s", said)
		}

		edit := Answer4Of(map[string]any{
			"v": 1, "event": "file_edited", "agent": 1, "cwd": root, "role": "main",
			"data": map[string]any{"cwd": root, "kind": "create", "path": filepath.Join("src", "brought.py")},
		})

		if edit == "" {
			t.Error("switching the merge judging off also stopped an ordinary edit being judged")
		}

		_ = data
	})

	t.Run("the memory ceiling at zero holds nothing back", func(t *testing.T) {
		kept.Ceiling(0)

		if held, ceiling, past := kept.PastItsCeiling(); past || ceiling != 0 {
			t.Errorf("a ceiling of zero says %d of %d and past=%v", held, ceiling, past)
		}
	})

	t.Run("the files at once at zero works it out as before", func(t *testing.T) {
		settingsOf(t, map[string]any{filesInOneGoKey: "0"})

		for _, files := range []int{40, 800, 5000, 20000} {
			if readsInOneGo(files) != min(mostReadInOneGo, max(leastReadInOneGo, files/partOfTheTree)) {
				t.Errorf("with the setting at zero a tree of %d files allows %d, which is not what the constant allowed", files, readsInOneGo(files))
			}
		}
	})

	t.Run("there is no gate before a merge to switch off", func(t *testing.T) {
		root, before, after := mergedProject(t)

		said := MergeLanded(mergeLine(root, "main", before, after))

		if strings.Contains(said, `"refuse"`) {
			t.Errorf("the merge key refused, so there is a gate to switch off after all: %s", said)
		}
	})

}

func mergeOffProject(t *testing.T) (root, data string) {
	t.Helper()

	root, data = workerProject(t)
	t.Setenv(pluginData, data)

	return root, data
}
