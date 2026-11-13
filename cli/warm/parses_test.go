package warm

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/contract"
)

func parseOf(t *testing.T, checkout, name, contents string) (string, *contract.Stream) {
	t.Helper()

	path := filepath.Join(checkout, name)
	if err := os.WriteFile(path, []byte(contents), 0o644); err != nil {
		t.Fatal(err)
	}

	node := contract.NewNode(contract.Node{Kind: "module", Role: "root"})

	return path, &contract.Stream{
		Header: contract.Header{Language: contract.Python},
		Files:  []*contract.File{{Path: path, Language: contract.Python, Root: node}},
	}
}

func keptIn(t *testing.T, parses *Parses, checkout, name, contents string) {
	t.Helper()

	path, stream := parseOf(t, checkout, name, contents)
	parses.Keeps([]*contract.Stream{stream}, map[string]string{path: Digest(path)}, checkout)
}

func TestTheSameBytesInAnotherCheckoutNeedNoBridge(t *testing.T) {
	parses, here, there := Parsing(time.Now), t.TempDir(), t.TempDir()
	keptIn(t, parses, here, "order.py", "ORDER = 1\n")
	path, _ := parseOf(t, there, "order.py", "ORDER = 1\n")

	streams, unknown, _ := parses.Find([]string{path}, there)

	if len(unknown) != 0 {
		t.Fatalf("bytes already parsed are asked of a bridge again: %v", unknown)
	}

	if got := streams[0].Files[0].Path; got != path {
		t.Errorf("the tree says it is of %s and it was asked for %s", got, path)
	}
}

func TestOtherBytesAreNotKnown(t *testing.T) {
	parses, here := Parsing(time.Now), t.TempDir()
	keptIn(t, parses, here, "order.py", "ORDER = 1\n")
	path, _ := parseOf(t, here, "order.py", "ORDER = 2\n")

	if _, unknown, _ := parses.Find([]string{path}, here); len(unknown) != 1 {
		t.Errorf("changed bytes were answered from the tree of the old ones")
	}
}

func TestAKnownFileIsGivenAsACopyOfItsOwn(t *testing.T) {
	parses, here := Parsing(time.Now), t.TempDir()
	keptIn(t, parses, here, "order.py", "ORDER = 1\n")
	path := filepath.Join(here, "order.py")

	first, _, _ := parses.Find([]string{path}, here)
	second, _, _ := parses.Find([]string{path}, here)

	first[0].Files[0].Root.Own().Refers = "written by one view"

	if second[0].Files[0].Root.Refers != "" {
		t.Error("two views share one tree, and a language filling one writes into the other")
	}
}

func TestAKnownFileExpiresOnceUnusedForTheTimeACacheIsKeptAndEachUseRenewsIt(t *testing.T) {
	at := time.Unix(1000, 0)
	parses, here := Parsing(func() time.Time { return at }), t.TempDir()
	keptIn(t, parses, here, "order.py", "ORDER = 1\n")
	path := filepath.Join(here, "order.py")

	at = at.Add(cache.KeptFor() - time.Hour)
	if _, unknown, _ := parses.Find([]string{path}, here); len(unknown) != 0 {
		t.Fatal("a file used within the time a cache is kept was forgotten")
	}

	at = at.Add(cache.KeptFor() - time.Hour)
	if _, unknown, _ := parses.Find([]string{path}, here); len(unknown) != 0 {
		t.Fatal("a use did not renew the time a cache is kept")
	}

	at = at.Add(cache.KeptFor())
	if _, unknown, _ := parses.Find([]string{path}, here); len(unknown) != 1 {
		t.Error("a file unused for the time a cache is kept is still held")
	}
}

func TestAMovedPythonFileIsReadAgainBecauseItsModuleNameMoved(t *testing.T) {
	parses, here := Parsing(time.Now), t.TempDir()
	if err := os.MkdirAll(filepath.Join(here, "shop"), 0o755); err != nil {
		t.Fatal(err)
	}

	path, stream := parseOf(t, here, "shop/order.py", "ORDER = 1\n")
	stream.Files[0].Module = "shop.order"
	parses.Keeps([]*contract.Stream{stream}, map[string]string{path: Digest(path)}, here)

	moved, _ := parseOf(t, here, "bill.py", "ORDER = 1\n")

	if _, unknown, _ := parses.Find([]string{moved}, here); len(unknown) != 1 {
		t.Error("a Python tree naming the module shop.order was given to a file that is another module")
	}
}

func TestAReplacedFileOfTheTreeStaysKnownByItsContents(t *testing.T) {
	keeper, here := Keep(), t.TempDir()
	path, stream := parseOf(t, here, "order.py", "ORDER = 1\n")
	base := &Base{Project: here, Branch: "main", streams: []*contract.Stream{stream}, marks: map[string]Known{path: Now(path)}}

	keeper.keepsWhatIsReplaced(base, map[string]Known{path: {}})

	if !keeper.Contents().Knows(path) {
		t.Error("the text a tree held before a commit was forgotten, so a worktree cut before it is parsed again")
	}
}
