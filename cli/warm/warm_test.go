package warm_test

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/engine"
)

// TestABaseAnswersForTheBranchItWasReadAt holds the one rule of the keeper: a base belongs to a project AND a
// branch, so two branches of one project are two bases and a question about one never gets the other's answer.
func TestABaseAnswersForTheBranchItWasReadAt(t *testing.T) {
	keeper := warm.Keep()
	root, codebase := project(t, map[string]string{"shop/order.py": "class Order:\n    pass\n"})

	if _, held := keeper.Holds(root, "main"); held {
		t.Error("a keeper with nothing in it holds a base")
	}

	keeper.Hold(root, "main", []string{root}, codebase)

	if _, held := keeper.Holds(root, "development"); held {
		t.Error("the base read at main answered for development")
	}

	base, held := keeper.Holds(root, "main")
	if !held {
		t.Fatal("the base read at main is not held")
	}

	if base.Codebase() != codebase {
		t.Error("the base answers with another codebase than the one it was given")
	}
}

// TestABaseNobodyAsksAboutIsDropped holds the eviction: a base is hundreds of megabytes, so a branch nobody is
// working on any more is not kept for the session.
func TestABaseNobodyAsksAboutIsDropped(t *testing.T) {
	keeper := warm.Keep()
	root, codebase := project(t, map[string]string{"shop/order.py": "class Order:\n    pass\n"})

	keeper.Hold(root, "main", []string{root}, codebase)
	keeper.Clocked(func() time.Time { return time.Now().Add(warm.Idle + time.Minute) })

	if _, held := keeper.Holds(root, "main"); held {
		t.Error("a base nobody asked about for the idle time is still held")
	}

	if kept := keeper.Held(); kept != 0 {
		t.Errorf("the keeper holds %d bases after the sweep", kept)
	}
}

// TestTheBaseSaysWhichFilesHaveMovedSinceItWasRead holds what makes the base safe to trust: an edit the service
// did not hear about, a git checkout or a formatter among them, is found by the fingerprints rather than
// answered from a tree that no longer matches the disk.
func TestTheBaseSaysWhichFilesHaveMovedSinceItWasRead(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/order.py":   "class Order:\n    pass\n",
		"shop/invoice.py": "class Invoice:\n    pass\n",
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase)

	if moved := base.Moved(); len(moved) != 0 {
		t.Fatalf("a base nobody has touched says %v moved", moved)
	}

	order := filepath.Join(root, "shop", "order.py")
	if unchanged, known := base.Holds(order); !known || !unchanged {
		t.Errorf("the base says order.py is known %v, unchanged %v", known, unchanged)
	}

	// The fingerprint is the file's size and the moment it was written, so a rewrite of the same length is
	// still a move: the mod time is what the filesystem changed.
	if err := os.WriteFile(order, []byte("class Order:\n    paid = True\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	touch(t, order)

	moved := base.Moved()
	if len(moved) != 1 || moved[0] != order {
		t.Fatalf("the base says %v moved, want only %s", moved, order)
	}

	if unchanged, known := base.Holds(order); !known || unchanged {
		t.Errorf("the rewritten file reads as known %v, unchanged %v", known, unchanged)
	}
}

// TestAViewIsTheBaseWithOneFileSwappedIn holds the splice, which is the whole of an overlay: the view answers
// with the edited file's own tree and with the base's tree for every other file, and the base itself is left as
// it was.
func TestAViewIsTheBaseWithOneFileSwappedIn(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/order.py":   "class Order:\n    pass\n",
		"shop/invoice.py": "class Invoice:\n    pass\n",
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase)

	order := filepath.Join(root, "shop", "order.py")
	if err := os.WriteFile(order, []byte("class Order:\n    def total(self) -> int:\n        return 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	rebuilt, err := scan.OneFile(order).Load()
	if err != nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	view := base.View(rebuilt.Streams(), root)

	if named := declared(view); len(named) != 2 {
		t.Fatalf("the view declares %v, want both files' classes", named)
	}

	if total := functions(view, "total"); total != 1 {
		t.Errorf("the view holds %d functions called total, want the one the edit added", total)
	}

	if total := functions(base.Codebase(), "total"); total != 0 {
		t.Errorf("the base holds %d functions called total, so the splice changed the base", total)
	}
}

// project is a python package of these files, read as the scan reads one, with its root.
func project(t *testing.T, files map[string]string) (string, *engine.Codebase) {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	files["shop/__init__.py"] = ""
	for path, text := range files {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	return root, codebase
}

// touch writes the file's time forward, for a filesystem whose stamp is too coarse to tell two writes apart.
func touch(t *testing.T, path string) {
	t.Helper()

	later := time.Now().Add(time.Second)
	if err := os.Chtimes(path, later, later); err != nil {
		t.Fatal(err)
	}
}

// declared are the names of every type the codebase declares.
func declared(codebase *engine.Codebase) []string {
	var named []string
	for _, match := range codebase.WhereTypeDeclaration().Get() {
		named = append(named, match.Name())
	}

	return named
}

// functions is how many functions of that name the codebase holds.
func functions(codebase *engine.Codebase, name string) int {
	return codebase.WhereFunction().Where(func(match engine.Match) bool { return match.Name() == name }).Count()
}

// TestTheBaseChecksTheEditedFileBetweenSweeps holds what an edit pays to trust the base. Re-stating every file
// of a large tree costs a third of the answer, so between sweeps the edited file alone is checked and the rest
// of the base is trusted; a file something else changed is found at the next sweep.
func TestTheBaseChecksTheEditedFileBetweenSweeps(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/order.py":   "class Order:\n    pass\n",
		"shop/invoice.py": "class Invoice:\n    pass\n",
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase)
	order := filepath.Join(root, "shop", "order.py")
	invoice := filepath.Join(root, "shop", "invoice.py")

	// The first question sweeps, since nothing has been swept yet.
	now := time.Now()
	if stale := base.Stale(order, now); len(stale) != 0 {
		t.Errorf("a base nobody has touched says %v is stale", stale)
	}

	write(t, invoice, "class Invoice:\n    paid = True\n")

	// Between sweeps the file nobody named is left alone, and the edited one is checked.
	if stale := base.Stale(order, now.Add(time.Second)); len(stale) != 0 {
		t.Errorf("between sweeps the base checks more than the edited file: %v", stale)
	}

	write(t, order, "class Order:\n    paid = True\n")

	if stale := base.Stale(order, now.Add(2*time.Second)); len(stale) != 1 || stale[0] != order {
		t.Errorf("the edited file is %v, want only %s", stale, order)
	}

	// Once a sweep is due, what something else changed is found too.
	stale := base.Stale(order, now.Add(warm.Sweep))
	if len(stale) != 2 {
		t.Errorf("the sweep finds %v, want both files", stale)
	}
}

// TestAFileCreatedSinceTheBaseWasReadIsInTheView holds the other half of a splice: a file the base never had is
// added to the stream of its language, so a new file is judged rather than read as though it did not exist.
func TestAFileCreatedSinceTheBaseWasReadIsInTheView(t *testing.T) {
	root, codebase := project(t, map[string]string{"shop/order.py": "class Order:\n    pass\n"})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase)

	fresh := filepath.Join(root, "shop", "refund.py")
	write(t, fresh, "class Refund:\n    pass\n")

	if stale := base.Stale(fresh, time.Now().Add(time.Second)); len(stale) != 1 || stale[0] != fresh {
		t.Fatalf("a file the base never had is %v, want only %s", stale, fresh)
	}

	rebuilt, err := scan.OneFile(fresh).Load()
	if err != nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	view := base.View(rebuilt.Streams(), root)

	if named := declared(view); len(named) != 2 {
		t.Errorf("the view declares %v, want the base's class and the new one", named)
	}
}

// write puts the text in the file and moves its time on, for a filesystem whose stamp is too coarse to tell two
// writes of one second apart.
func write(t *testing.T, path, text string) {
	t.Helper()

	if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}

	touch(t, path)
}

// TestTheSweepNamesTheEditedFileFirst holds what an edit is allowed to pay for. The sweep's job is to notice a
// file something else changed, and the answer it gives names the edited file along with them, so the caller can
// parse that one and give the base up for the rest rather than putting one edit's answer behind every file
// another worker touched.
func TestTheSweepNamesTheEditedFileFirst(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/order.py":   "class Order:\n    pass\n",
		"shop/invoice.py": "class Invoice:\n    pass\n",
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase)
	order := filepath.Join(root, "shop", "order.py")
	invoice := filepath.Join(root, "shop", "invoice.py")

	write(t, order, "class Order:\n    paid = True\n")
	write(t, invoice, "class Invoice:\n    paid = True\n")

	stale := base.Stale(order, time.Now().Add(warm.Sweep))
	if len(stale) != 2 {
		t.Fatalf("the sweep names %v, want both files that moved", stale)
	}

	if stale[0] != order {
		t.Errorf("the sweep names %s first; the edited file is the one an edit may pay for", stale[0])
	}
}
