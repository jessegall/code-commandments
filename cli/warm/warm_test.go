package warm_test

import (
	"os"
	"path/filepath"
	"slices"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func TestABaseAnswersForTheBranchItWasReadAt(t *testing.T) {
	keeper := warm.Keep()
	root, codebase := project(t, map[string]string{"shop/order.py": "class Order:\n    pass\n"})

	if _, held := keeper.Holds(root, "main"); held {
		t.Error("a keeper with nothing in it holds a base")
	}

	keeper.Hold(root, "main", []string{root}, codebase, nil)

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

func TestABaseNobodyAsksAboutIsDropped(t *testing.T) {
	keeper := warm.Keep()
	root, codebase := project(t, map[string]string{"shop/order.py": "class Order:\n    pass\n"})

	keeper.Hold(root, "main", []string{root}, codebase, nil)
	keeper.Clocked(func() time.Time { return time.Now().Add(warm.Idle + time.Minute) })

	if _, held := keeper.Holds(root, "main"); held {
		t.Error("a base nobody asked about for the idle time is still held")
	}

	if kept := keeper.Held(); kept != 0 {
		t.Errorf("the keeper holds %d bases after the sweep", kept)
	}
}

func TestTheBaseSaysWhichFilesHaveMovedSinceItWasRead(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/order.py":   "class Order:\n    pass\n",
		"shop/invoice.py": "class Invoice:\n    pass\n",
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase, nil)

	if moved := base.Moved(); len(moved) != 0 {
		t.Fatalf("a base nobody has touched says %v moved", moved)
	}

	order := filepath.Join(root, "shop", "order.py")
	if unchanged, known := base.Holds(order); !known || !unchanged {
		t.Errorf("the base says order.py is known %v, unchanged %v", known, unchanged)
	}

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

func TestAViewIsTheBaseWithOneFileSwappedIn(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/order.py":   "class Order:\n    pass\n",
		"shop/invoice.py": "class Invoice:\n    pass\n",
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase, nil)

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

func touch(t *testing.T, path string) {
	t.Helper()

	later := time.Now().Add(time.Second)
	if err := os.Chtimes(path, later, later); err != nil {
		t.Fatal(err)
	}
}

func declared(codebase *engine.Codebase) []string {
	var named []string
	for _, match := range codebase.WhereTypeDeclaration().Get() {
		named = append(named, match.Name())
	}

	return named
}

func functions(codebase *engine.Codebase, name string) int {
	return codebase.WhereFunction().Where(func(match engine.Match) bool { return match.Name() == name }).Count()
}

func TestTheBaseChecksTheEditedFileBetweenSweeps(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/order.py":   "class Order:\n    pass\n",
		"shop/invoice.py": "class Invoice:\n    pass\n",
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase, nil)
	order := filepath.Join(root, "shop", "order.py")
	invoice := filepath.Join(root, "shop", "invoice.py")

	now := time.Now()
	if stale, _ := base.Stale(order, now); len(stale) != 1 || stale[0] != order {
		t.Errorf("a base asked about an edit says %v is stale; the edited file is always read again", stale)
	}

	write(t, invoice, "class Invoice:\n    paid = True\n")

	if stale, _ := base.Stale(order, now.Add(time.Second)); len(stale) != 1 || stale[0] != order {
		t.Errorf("between sweeps the base checks more than the edited file: %v", stale)
	}

	write(t, order, "class Order:\n    paid = True\n")

	if stale, _ := base.Stale(order, now.Add(2*time.Second)); len(stale) != 1 || stale[0] != order {
		t.Errorf("the edited file is %v, want only %s", stale, order)
	}

	stale, _ := base.Stale(order, now.Add(warm.Sweep))
	if len(stale) != 2 {
		t.Errorf("the sweep finds %v, want both files", stale)
	}
}

func TestAFileCreatedSinceTheBaseWasReadIsInTheView(t *testing.T) {
	root, codebase := project(t, map[string]string{"shop/order.py": "class Order:\n    pass\n"})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase, nil)

	fresh := filepath.Join(root, "shop", "refund.py")
	write(t, fresh, "class Refund:\n    pass\n")

	if stale, _ := base.Stale(fresh, time.Now().Add(time.Second)); len(stale) != 1 || stale[0] != fresh {
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

func write(t *testing.T, path, text string) {
	t.Helper()

	if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}

	touch(t, path)
}

func TestTheSweepNamesTheEditedFileFirst(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/order.py":   "class Order:\n    pass\n",
		"shop/invoice.py": "class Invoice:\n    pass\n",
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase, nil)
	order := filepath.Join(root, "shop", "order.py")
	invoice := filepath.Join(root, "shop", "invoice.py")

	write(t, order, "class Order:\n    paid = True\n")
	write(t, invoice, "class Invoice:\n    paid = True\n")

	stale, _ := base.Stale(order, time.Now().Add(warm.Sweep))
	if len(stale) != 2 {
		t.Fatalf("the sweep names %v, want both files that moved", stale)
	}

	if stale[0] != order {
		t.Errorf("the sweep names %s first; the edited file is the one an edit may pay for", stale[0])
	}
}

func TestAWorkersFileIsNotDoubledWhenTheProjectsOwnCopyMovedToo(t *testing.T) {
	root := t.TempDir()
	worktree := filepath.Join(root, ".worktrees", "worker-1")

	own := filepath.Join(root, "src", "order.py")
	theirs := filepath.Join(worktree, "src", "order.py")

	base := warm.Keep().Hold(root, "main", []string{root}, codebaseOf(t, own), nil)

	view := base.ViewFrom(worktree, []*contract.Stream{streamOf(own), streamOf(theirs)}, root, nil)

	var held []string
	for _, file := range view.Files() {
		held = append(held, file.File.Path)
	}

	if slices.Contains(held, own) {
		t.Errorf("the program holds the project's %s beside the worker's copy of it: %v", own, held)
	}

	if !slices.Contains(held, theirs) {
		t.Errorf("the program does not hold the worker's own %s: %v", theirs, held)
	}
}

func codebaseOf(t *testing.T, files ...string) *engine.Codebase {
	t.Helper()

	var streams []*contract.Stream
	for _, file := range files {
		streams = append(streams, streamOf(file))
	}

	return engine.Load(streams...)
}

func streamOf(file string) *contract.Stream {
	return &contract.Stream{
		Header: contract.Header{Language: contract.Python},
		Files:  []*contract.File{{Path: file, Language: contract.Python}},
	}
}

func TestABaseHoldsWhatAFileWasBeforeItWasRead(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "order.py")

	if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	taken := warm.Taken([]string{file})

	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(file, []byte("x = 1\ny = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	base := warm.Keep().Hold(root, "main", []string{root}, codebaseOf(t, file), taken)

	if unchanged, known := base.Holds(file); !known || unchanged {
		t.Errorf("a file written during the read reads as unchanged, known %v unchanged %v", known, unchanged)
	}
}

func TestWhatOneEditReadIsWhatTheNextIsJudgedAgainst(t *testing.T) {
	root := t.TempDir()
	keeper := warm.Keep()

	first := filepath.Join(root, "first.py")
	second := filepath.Join(root, "second.py")

	for _, file := range []string{first, second} {
		if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	base := keeper.Hold(root, "main", []string{root}, codebaseOf(t, first, second), nil)

	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(first, []byte("x = 1\ny = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	mark := warm.Now(first)
	view := base.ViewFrom(root, []*contract.Stream{streamOf(first)}, root, nil)
	keeper.Patched(base, view, map[string]warm.Known{first: mark})

	next, held := keeper.Holds(root, "main")
	if !held {
		t.Fatal("the keeper holds no base after one was patched")
	}

	if unchanged, known := next.Holds(first); !known || !unchanged {
		t.Errorf("the file the last edit read reads as moved again, known %v unchanged %v", known, unchanged)
	}

	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(second, []byte("x = 1\nz = 3\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	stale, _ := next.Stale(second, time.Now().Add(warm.Sweep))
	if len(stale) != 1 || stale[0] != second {
		t.Errorf("the next edit must read its own file and no other; it reads %v", stale)
	}
}

func TestAFileTheBaseNeverReadIsNoticed(t *testing.T) {
	root := t.TempDir()

	read := filepath.Join(root, "order.py")
	never := filepath.Join(root, "invoice.py")

	for _, file := range []string{read, never} {
		if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	base := warm.Keep().Hold(root, "main", []string{root}, codebaseOf(t, read), nil)

	unheld := base.Unheld([]string{read, never})
	if len(unheld) != 1 || unheld[0] != never {
		t.Errorf("the base names %v as files it never read; it never read %s and it did read %s", unheld, never, read)
	}
}

func TestTheEditedFileIsAlwaysReadAgain(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "order.py")

	if err := os.WriteFile(file, []byte("if a:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	base := warm.Keep().Hold(root, "main", []string{root}, codebaseOf(t, file), nil)

	was, err := os.Stat(file)
	if err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(file, []byte("if b:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if err := os.Chtimes(file, was.ModTime(), was.ModTime()); err != nil {
		t.Fatal(err)
	}

	if unchanged, _ := base.Holds(file); !unchanged {
		t.Fatal("this test proves nothing: the mark moved, so the base would have read the file anyway")
	}

	stale, _ := base.Stale(file, time.Now())
	if len(stale) != 1 || stale[0] != file {
		t.Errorf("the edited file is not read again; the base names %v as stale", stale)
	}
}

func TestAFileWrittenBackUnchangedHasNotMoved(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/order.py":   "class Order:\n    pass\n",
		"shop/invoice.py": "class Invoice:\n    pass\n",
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase, nil)

	order := filepath.Join(root, "shop", "order.py")
	invoice := filepath.Join(root, "shop", "invoice.py")

	if err := os.WriteFile(order, []byte("class Order:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	touch(t, order)

	if moved := base.Moved(); len(moved) != 0 {
		t.Errorf("the base says %v moved; those bytes are the ones it read, so nothing has", moved)
	}

	if unchanged, known := base.Holds(order); !known || !unchanged {
		t.Errorf("a file written back unchanged reads as known %v, unchanged %v", known, unchanged)
	}

	if err := os.WriteFile(invoice, []byte("class Invoice:\n    paid = True\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	touch(t, invoice)

	moved := base.Moved()
	if len(moved) != 1 || moved[0] != invoice {
		t.Errorf("the base says %v moved, want only %s", moved, invoice)
	}
}

func TestACheckoutsFileStandsInForTheProjectsOwnOnItsRealPath(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"src/order.py":   "class Order:\n    pass\n",
		"src/invoice.py": "class Invoice:\n    pass\n",
	})

	linked := filepath.Join(t.TempDir(), "project")
	if err := os.Symlink(root, linked); err != nil {
		t.Skipf("this filesystem has no links, so the two paths cannot differ here: %v", err)
	}

	base := warm.Keep().Hold(root, "main", []string{root}, codebase, nil)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	own := filepath.Join(worktree, "src", "order.py")

	stands := warm.Counterpart(worktree, linked, own)

	if _, known := base.Holds(stands); !known {
		t.Errorf("a worker's file stands in for %q, which the tree does not hold; it holds the project's copy at %q",
			stands, filepath.Join(root, "src", "order.py"))
	}

	if stands := warm.Counterpart(root, linked, filepath.Join(root, "src", "invoice.py")); stands != filepath.Join(root, "src", "invoice.py") {
		t.Errorf("a file of the project's own checkout stands in for %q rather than itself", stands)
	}

	elsewhere := filepath.Join(t.TempDir(), "other", "thing.py")
	if stands := warm.Counterpart(worktree, linked, elsewhere); stands != elsewhere {
		t.Errorf("a file outside the checkout stands in for %q", stands)
	}
}
