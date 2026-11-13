package hooks

import (
	"path/filepath"
	"strings"
	"testing"
)

func TestEveryFieldWeRelyOnHasAnAnswerWhenItIsAbsent(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	for _, told := range []struct {
		what string
		line map[string]any
		then func(t *testing.T, moment Moment, said string)
	}{
		{
			"a cut with no base commit",
			map[string]any{"v": 1, "event": "file_edited", "agent": 1, "cwd": root, "data": map[string]any{
				"cwd": root, "kind": "create", "path": under,
				"checkout": map[string]any{"kind": "worktree", "root": root, "branch": "main"},
			}},
			func(t *testing.T, moment Moment, said string) {
				if moment.Cut != "" {
					t.Errorf("a cut with no base commit reads as %q, and nothing said one", moment.Cut)
				}
			},
		},
		{
			"a line with no checkout block",
			map[string]any{"v": 1, "event": "file_edited", "agent": 1, "cwd": root, "data": map[string]any{
				"cwd": root, "kind": "create", "path": under,
			}},
			func(t *testing.T, moment Moment, said string) {
				if moment.CheckoutSaid != "git" {
					t.Errorf("a line with no checkout block says the checkout came from %q", moment.CheckoutSaid)
				}
			},
		},
		{
			"a role that is empty",
			map[string]any{"v": 1, "event": "file_edited", "agent": 1, "cwd": root, "role": "", "data": map[string]any{
				"cwd": root, "kind": "create", "path": under,
			}},
			func(t *testing.T, moment Moment, said string) {
				if moment.Role != "" {
					t.Errorf("an empty role reads as %q", moment.Role)
				}

				if strings.Contains(said, workersKey) {
					t.Errorf("an empty role was turned away by the workers setting: %s", said)
				}
			},
		},
		{
			"an advance whose commit is unchanged",
			map[string]any{"v": 1, "event": "file_edited", "agent": 1, "cwd": root, "data": map[string]any{
				"cwd": root, "kind": "create", "path": under,
				"checkout": map[string]any{"root": root, "base_commit": "aaaaaaa"},
			}},
			func(t *testing.T, moment Moment, said string) {
				if moment.Cut != "aaaaaaa" {
					t.Errorf("the unchanged commit reads as %q", moment.Cut)
				}
			},
		},
	} {
		moment, known := Moment4Of(told.line)
		if !known {
			t.Errorf("%s: the line is no moment at all", told.what)

			continue
		}

		given := Stamped(moment)
		said := Answer4Of(told.line)

		told.then(t, MomentOf(given), said)
	}
}

func TestAMergeWhoseCommitsAreEmptyIsAnsweredWithoutJudgingNothing(t *testing.T) {
	root, _ := workerProject(t)

	work := mergeOf(map[string]any{
		"event": "worker_merging",
		"data": map[string]any{
			"worktree": root, "role": "main",
			"commit_before_merge": "", "commit_after_merge": "", "files": []any{},
		},
	})

	if work.base != "" {
		t.Errorf("a merge with no commits reads its base as %q", work.base)
	}

	if len(work.files) != 0 {
		t.Errorf("a merge that names no files reads %d of them", len(work.files))
	}

	if said := heldAnswer(map[string]any{"event": "worker_merging", "data": map[string]any{
		"worktree": root, "role": "main", "commit_before_merge": "", "commit_after_merge": "",
	}}); strings.Contains(said, "refuse") {
		t.Errorf("a merge with no commits was refused over sins in nothing: %s", said)
	}
}
