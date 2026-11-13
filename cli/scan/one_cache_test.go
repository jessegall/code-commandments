package scan

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/contract"
)

// Every checkout of a project reads through one cache keyed on contents: a worktree holding the files the main
// checkout already read starts no bridge for them, and a worktree's own change never evicts what the main checkout
// keeps. This is the test that guards it; it fails when a run in one checkout re-reads what another already read.
func TestEveryCheckoutReadsThroughOneCacheKeyedOnContents(t *testing.T) {
	if _, err := bridge.Mypy(); err != nil {
		t.Skipf("no Python bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/shop/__init__.py", "")
	written(t, project, "src/shop/prices.py", "def price() -> int:\n    return 3\n")
	written(t, project, "src/shop/cart.py", "from shop.prices import price\n\n\ndef total() -> int:\n    return price() * 2\n")
	inRepository(t, project, "add", "-A")
	inRepository(t, project, "commit", "-m", "the shop")
	worktree := filepath.Join(project, ".journal", "worktrees", "worker-1")
	inRepository(t, project, "worktree", "add", "-b", "a-worker", worktree)

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	if asked := askedOfABridge(t, project, theShop...); len(asked) != 3 {
		t.Fatalf("the first run of the project asked the bridge for %v, every file of it", asked)
	}
	if asked := askedOfABridge(t, worktree, theShop...); len(asked) != 0 {
		t.Errorf("a worktree holding the files the main checkout read asked the bridge for %v again", asked)
	}

	written(t, worktree, "src/shop/prices.py", "def price() -> str:\n    return 'three'\n")
	asked := askedOfABridge(t, worktree, theShop...)
	if !slices.Equal(asked, []string{filepath.Join(worktree, "src/shop/cart.py"), filepath.Join(worktree, "src/shop/prices.py")}) {
		t.Errorf("a change to prices.py in the worktree asked the bridge for %v, not prices.py and cart.py, which reads it", asked)
	}
	if asked := askedOfABridge(t, worktree, theShop...); len(asked) != 0 {
		t.Errorf("the worktree's second run asked the bridge for %v, which its first run had just read", asked)
	}

	written(t, worktree, "src/shop/prices.py", "def price() -> str:\n    return 'four'\n")
	if asked := askedOfABridge(t, worktree, theShop...); !slices.Equal(asked, []string{filepath.Join(worktree, "src/shop/prices.py")}) {
		t.Errorf("an edit inside price's body asked the bridge for %v, not prices.py alone: cart.py reads only what price declares", asked)
	}
	if asked := askedOfABridge(t, project, theShop...); len(asked) != 0 {
		t.Errorf("the worktree's change evicted the main checkout's lines: the main checkout asked the bridge for %v again", asked)
	}

	written(t, project, "src/shop/prices.py", "def price() -> float:\n    return 3.5\n")
	if asked := askedOfABridge(t, project, "src/shop/cart.py"); !slices.Equal(asked, []string{filepath.Join(project, "src/shop/cart.py")}) {
		t.Errorf("a run given only cart.py, after prices.py which it imports changed, asked the bridge for %v instead of reading cart.py again", asked)
	}

	build := bridge.Build
	bridge.Build = func() string { return "a release that changed no bridge" }
	t.Cleanup(func() { bridge.Build = build })
	if asked := askedOfABridge(t, project, "src/shop/cart.py"); len(asked) != 0 {
		t.Errorf("a release that changed no bridge asked the bridge for %v again, which the release before had read", asked)
	}
}

// A file stands only on files that stand themselves: a change to what prices.py declares reads cart.py again, and
// checkout.py, which reads cart.py, with it, though its own key named cart.py's outline from before.
func TestAChangeTwoImportsAwayReadsTheTopFileAgain(t *testing.T) {
	if _, err := bridge.Mypy(); err != nil {
		t.Skipf("no Python bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/shop/__init__.py", "")
	written(t, project, "src/shop/prices.py", "def price() -> int:\n    return 3\n")
	written(t, project, "src/shop/cart.py", "from shop.prices import price\n\n\ndef total() -> int:\n    return price() * 2\n")
	written(t, project, "src/shop/checkout.py", "from shop.cart import total\n\n\ndef pay() -> int:\n    return total()\n")

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	chain := append(slices.Clone(theShop), "src/shop/checkout.py")
	askedOfABridge(t, project, chain...)
	written(t, project, "src/shop/prices.py", "def price() -> str:\n    return 'three'\n")
	asked := askedOfABridge(t, project, chain...)
	if !slices.Contains(asked, filepath.Join(project, "src/shop/checkout.py")) {
		t.Errorf("a change to what prices.py declares asked the bridge for %v, leaving checkout.py, two imports up, as it was", asked)
	}
}

var theShop = []string{"src/shop/__init__.py", "src/shop/cart.py", "src/shop/prices.py"}

// askedOfABridge reads the checkout's Python files named through the funnel and answers which files a bridge was asked to
// write.
func askedOfABridge(t *testing.T, checkout string, relative ...string) []string {
	t.Helper()
	asked, _ := askedOf(t, Python, checkout, relative...)

	return asked
}

// askedOf reads the checkout's files named through the funnel with the bridge and answers which files it was asked to
// write, and the stream the read made.
func askedOf(t *testing.T, b Bridge, checkout string, relative ...string) ([]string, *contract.Stream) {
	t.Helper()

	route := read
	var asked []string
	read = func(b Bridge, roots, files, written []string, tally func()) (*contract.Stream, error) {
		asked = append(asked, written...)

		return route(b, roots, files, written, tally)
	}
	defer func() { read = route }()

	var files []string
	for _, file := range relative {
		files = append(files, filepath.Join(checkout, file))
	}
	stream, err := b.ReadShared([]string{filepath.Join(checkout, "src")}, files, func() {}, 1)
	if err != nil {
		t.Fatal(err)
	}
	if len(stream.Files) != len(files) {
		t.Fatalf("the read of %s holds %d files, not %d", checkout, len(stream.Files), len(files))
	}
	slices.Sort(asked)

	return asked, stream
}

func journalDriven(t *testing.T, project string) {
	t.Helper()
	written(t, project, ".journal/plugins/code-commandments/.journal-plugin/plugin.json", "{}")
	written(t, project, ".gitignore", ".journal/\n")
}

func written(t *testing.T, root, relative, text string) {
	t.Helper()
	path := filepath.Join(root, relative)
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}
