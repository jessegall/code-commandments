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

// TestABaseAnswersForTheBranchItWasReadAt holds the one rule of the keeper: a base belongs to a project AND a
// branch, so two branches of one project are two bases and a question about one never gets the other's answer.
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

// TestABaseNobodyAsksAboutIsDropped holds the eviction: a base is hundreds of megabytes, so a branch nobody is
// working on any more is not kept for the session.
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

// TestTheBaseSaysWhichFilesHaveMovedSinceItWasRead holds what makes the base safe to trust: an edit the service
// did not hear about, a git checkout or a formatter among them, is found by the fingerprints rather than
// answered from a tree that no longer matches the disk.
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

	base := warm.Keep().Hold(root, "main", []string{root}, codebase, nil)
	order := filepath.Join(root, "shop", "order.py")
	invoice := filepath.Join(root, "shop", "invoice.py")

	// The edited file is always read again, whether its mark moved or not: it is the one file we know moved.
	now := time.Now()
	if stale, _ := base.Stale(order, now); len(stale) != 1 || stale[0] != order {
		t.Errorf("a base asked about an edit says %v is stale; the edited file is always read again", stale)
	}

	write(t, invoice, "class Invoice:\n    paid = True\n")

	// Between sweeps the file nobody named is left alone, however much it has moved.
	if stale, _ := base.Stale(order, now.Add(time.Second)); len(stale) != 1 || stale[0] != order {
		t.Errorf("between sweeps the base checks more than the edited file: %v", stale)
	}

	write(t, order, "class Order:\n    paid = True\n")

	if stale, _ := base.Stale(order, now.Add(2*time.Second)); len(stale) != 1 || stale[0] != order {
		t.Errorf("the edited file is %v, want only %s", stale, order)
	}

	// Once a sweep is due, what something else changed is found too.
	stale, _ := base.Stale(order, now.Add(warm.Sweep))
	if len(stale) != 2 {
		t.Errorf("the sweep finds %v, want both files", stale)
	}
}

// TestAFileCreatedSinceTheBaseWasReadIsInTheView holds the other half of a splice: a file the base never had is
// added to the stream of its language, so a new file is judged rather than read as though it did not exist.
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

// TestAWorkersFileIsNotDoubledWhenTheProjectsOwnCopyMovedToo holds what a checkout's file STANDS IN FOR against
// the main checkout drifting under the base. Both copies then arrive at the view: the project's own because it
// moved and was read again, and the worker's because the worker changed it. Kept together, the program holds two
// of that one source, and every rule that reads a recurrence, a twin or a declaration answers about the worker's
// file doubled.
func TestAWorkersFileIsNotDoubledWhenTheProjectsOwnCopyMovedToo(t *testing.T) {
	root := t.TempDir()
	worktree := filepath.Join(root, ".worktrees", "worker-1")

	own := filepath.Join(root, "src", "order.py")
	theirs := filepath.Join(worktree, "src", "order.py")

	base := warm.Keep().Hold(root, "main", []string{root}, codebaseOf(t, own), nil)

	// Both were read again: the project's own because it drifted, the worker's because the worker changed it.
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

// codebaseOf is a codebase of those files alone, as a bridge would have written it.
func codebaseOf(t *testing.T, files ...string) *engine.Codebase {
	t.Helper()

	var streams []*contract.Stream
	for _, file := range files {
		streams = append(streams, streamOf(file))
	}

	return engine.Load(streams...)
}

// streamOf is a stream holding that one file.
func streamOf(file string) *contract.Stream {
	return &contract.Stream{
		Header: contract.Header{Language: contract.Python},
		Files:  []*contract.File{{Path: file, Language: contract.Python}},
	}
}

// TestABaseHoldsWhatAFileWasBeforeItWasRead holds the base against the clock of its own read. Reading a tree
// takes 25 to 55 seconds, and an agent keeps editing through it: a file written during the read is read as it
// was, and marked afterwards it would be marked as it IS, so nothing would ever notice it had moved. That edit
// is then judged against the version before it, at the lines that version had, for as long as the base lives.
func TestABaseHoldsWhatAFileWasBeforeItWasRead(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "order.py")

	if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	// What the file was when the read began.
	taken := warm.Taken([]string{file})

	// Written again while the read ran, which is what an agent editing through a read does.
	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(file, []byte("x = 1\ny = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	base := warm.Keep().Hold(root, "main", []string{root}, codebaseOf(t, file), taken)

	if unchanged, known := base.Holds(file); !known || unchanged {
		t.Errorf("a file written during the read reads as unchanged, known %v unchanged %v", known, unchanged)
	}
}

// TestWhatOneEditReadIsWhatTheNextIsJudgedAgainst holds the base against the agent's own work. A view was thrown
// away the moment its answer was given, so the base stayed the tree as it was FIRST read: an agent that edited
// one file and then another had its own earlier work missing from the second answer, and every rule that reads a
// parent, a caller or a recurrence answered about the first file as it had been. It costs nothing to keep, since
// the view is already built and already filled.
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

	// The first file is edited and read again, and what that read saw becomes the base.
	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(first, []byte("x = 1\ny = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	mark := warm.Mark(first)
	view := base.ViewFrom(root, []*contract.Stream{streamOf(first)}, root, nil)
	keeper.Patched(base, view, map[string]string{first: mark})

	// The next edit asks the keeper, and gets a base that already holds the first file as it now is.
	next, held := keeper.Holds(root, "main")
	if !held {
		t.Fatal("the keeper holds no base after one was patched")
	}

	if unchanged, known := next.Holds(first); !known || !unchanged {
		t.Errorf("the file the last edit read reads as moved again, known %v unchanged %v", known, unchanged)
	}

	// And a sweep of the whole tree does not name it either, which is what used to give the base up and send
	// every later edit back to being judged on its own file alone.
	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(second, []byte("x = 1\nz = 3\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	stale, _ := next.Stale(second, time.Now().Add(warm.Sweep))
	if len(stale) != 1 || stale[0] != second {
		t.Errorf("the next edit must read its own file and no other; it reads %v", stale)
	}
}

// TestAFileTheBaseNeverReadIsNoticed holds the base against a file nobody announced. The sweep walks the files
// the base READ, so a file a shell wrote, a pull brought or a checkout left behind is in no answer at all: every
// rule that follows an import, a parent or a caller answers as though that file did not exist, which is a sin
// missed rather than a sin invented, and the quietest kind of wrong. Only the base can say which of the files
// something else names are ones it never read.
func TestAFileTheBaseNeverReadIsNoticed(t *testing.T) {
	root := t.TempDir()

	read := filepath.Join(root, "order.py")
	never := filepath.Join(root, "invoice.py")

	for _, file := range []string{read, never} {
		if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	// The base read one of them, as a tree read before the other file existed did.
	base := warm.Keep().Hold(root, "main", []string{root}, codebaseOf(t, read), nil)

	unheld := base.Unheld([]string{read, never})
	if len(unheld) != 1 || unheld[0] != never {
		t.Errorf("the base names %v as files it never read; it never read %s and it did read %s", unheld, never, read)
	}
}

// TestTheEditedFileIsAlwaysReadAgain holds the one file an event names against the clock a mark is kept by. A
// mark is a size and a moment written, which is what the filesystem answers without reading the file, so two
// writes of one size inside one tick of that clock carry the same mark. On a filesystem whose clock ticks by the
// second, which is not a rare one, an edit that keeps a file's length would then read as no change at all and be
// judged against the version before it.
//
// The file an event names is the one file we KNOW moved, so it is never asked about.
func TestTheEditedFileIsAlwaysReadAgain(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "order.py")

	if err := os.WriteFile(file, []byte("if a:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	base := warm.Keep().Hold(root, "main", []string{root}, codebaseOf(t, file), nil)

	// Written again with the mark it already had: the same length, and the moment it was written put back.
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
