package hooks

import (
	"path/filepath"
	"testing"
)

func TestTheCheckoutIsTakenFromTheLineWhereTheLineSaysIt(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 17)

	cutInto(t, root, worktree, "feature/told")

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(worktree, under), olderSin)

	moment, known := Moment4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
		"cwd": root,
		"data": map[string]any{
			"kind": "create", "path": under,
			"checkout": map[string]any{
				"kind": "worktree", "root": worktree, "project_root": root,
				"branch": "feature/told", "base_commit": "aaaaaaa", "exists": true,
			},
		},
	})

	if !known {
		t.Fatal("the line is not a moment at all")
	}

	held := MomentOf(Stamped(moment))

	if held.Cwd != worktree {
		t.Errorf("the checkout is %s, and the line said %s", held.Cwd, worktree)
	}

	if held.CheckoutSaid != "the line" {
		t.Errorf("the record says the checkout came from %q, and the line gave it", held.CheckoutSaid)
	}

	if held.File == nil || *held.File != filepath.Join(worktree, under) {
		t.Errorf("the file is %v, and it is the relative name joined on the checkout root the line gave", held.File)
	}

	if held.Cut != "aaaaaaa" {
		t.Errorf("the commit it was cut from is %q", held.Cut)
	}
}

func TestWithNoCheckoutOnTheLineWeWorkItOutAndSaySo(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 18)

	cutInto(t, root, worktree, "feature/untold")

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(worktree, under), olderSin)

	t.Setenv("JOURNAL_ROOT", root)

	moment, known := Moment4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
		"cwd":  worktree,
		"data": map[string]any{"cwd": worktree, "kind": "create", "path": under},
	})

	if !known {
		t.Fatal("the line is not a moment at all")
	}

	held := MomentOf(Stamped(moment))

	if held.Cwd != worktree {
		t.Errorf("we worked the checkout out as %s, and the file is in %s", held.Cwd, worktree)
	}

	if held.CheckoutSaid != "git" {
		t.Errorf("the record says the checkout came from %q, and nothing on the line gave it", held.CheckoutSaid)
	}
}

func TestTheCheckoutBlockIsReadFieldByField(t *testing.T) {
	told := toldIn(map[string]any{"checkout": map[string]any{
		"root": "/project-worker-1", "project_root": "/project", "branch": "feature/one",
		"base_commit": "bbbbbbb", "exists": false,
	}})

	if !told.Given || told.Root != "/project-worker-1" || told.Project != "/project" ||
		told.Branch != "feature/one" || told.Base != "bbbbbbb" || told.Exists {
		t.Errorf("the checkout block reads as %+v", told)
	}

	if none := toldIn(map[string]any{}); none.Given || none.Exists {
		t.Errorf("a line with no checkout block reads as %+v, and it said nothing", none)
	}

	if quiet := toldIn(map[string]any{"checkout": map[string]any{"root": "/project"}}); !quiet.Exists {
		t.Error("a checkout block that does not say whether it exists is read as gone, and silence is not a no")
	}
}
