package scan

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"sync"
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

// A file stands only on files that stand themselves: a change to what prices.py declares reads cart.py again, and when
// that changes what cart.py declares, checkout.py, which reads cart.py, with it, though its own key named cart.py's
// outline from before.
func TestAChangeTwoImportsAwayReadsTheTopFileAgain(t *testing.T) {
	if _, err := bridge.Mypy(); err != nil {
		t.Skipf("no Python bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/shop/__init__.py", "")
	written(t, project, "src/shop/prices.py", "def price() -> int:\n    return 3\n")
	written(t, project, "src/shop/cart.py", "from shop.prices import price\n\ndoubled = price() * 2\n")
	written(t, project, "src/shop/checkout.py", "from shop.cart import doubled\n\n\ndef pay() -> None:\n    print(doubled)\n")

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

// Misses are read in waves: a change to what prices.py declares reads cart.py, which imports it, again, and checkout.py,
// which imports cart.py, stands from the cache when cart.py still declares what it did.
func TestAChangeStopsSpreadingWhereAnOutlineStaysTheSame(t *testing.T) {
	if _, err := bridge.Mypy(); err != nil {
		t.Skipf("no Python bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/shop/__init__.py", "")
	written(t, project, "src/shop/prices.py", "def price() -> int:\n    return 3\n")
	written(t, project, "src/shop/cart.py", "import shop.prices\n\n\ndef total() -> int:\n    return len(str(shop.prices.price()))\n")
	written(t, project, "src/shop/checkout.py", "from shop.cart import total\n\n\ndef pay() -> int:\n    return total()\n")

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	chain := append(slices.Clone(theShop), "src/shop/checkout.py")
	askedOfABridge(t, project, chain...)
	written(t, project, "src/shop/prices.py", "def price() -> str:\n    return 'three'\n")
	asked := askedOfABridge(t, project, chain...)
	if !slices.Equal(asked, []string{filepath.Join(project, "src/shop/cart.py"), filepath.Join(project, "src/shop/prices.py")}) {
		t.Errorf("a change to what prices.py declares asked the bridge for %v, not prices.py and cart.py: checkout.py reads "+
			"only cart.py, whose outline stayed as it was", asked)
	}
}

// Files read side by side stand on the outlines each other's share kept: cart.py, written by one share, reads prices.py,
// written by another that ends after it, and the next run still serves cart.py from the cache.
func TestLinesReadSideBySideStandOnTheOutlinesEachOtherKept(t *testing.T) {
	if _, err := bridge.Mypy(); err != nil {
		t.Skipf("no Python bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/shop/__init__.py", "")
	written(t, project, "src/shop/prices.py", "def price() -> int:\n    return 3\n")
	written(t, project, "src/shop/cart.py", "from shop.prices import price\n\n\ndef total() -> int:\n    return price() * 2\n")

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	route := read
	cartRead, readOnce := make(chan struct{}), sync.Once{}
	writes := func(written []string, name string) bool {
		return slices.ContainsFunc(written, func(file string) bool { return filepath.Base(file) == name })
	}
	read = func(b Bridge, kept *keptLines, roots, files, written []string, tally func()) (*contract.Stream, error) {
		if writes(written, "prices.py") {
			<-cartRead
		}
		defer func() {
			if writes(written, "cart.py") {
				readOnce.Do(func() { close(cartRead) })
			}
		}()

		return route(b, kept, roots, files, written, tally)
	}
	var files []string
	for _, file := range theShop {
		files = append(files, filepath.Join(project, file))
	}
	_, err := Python.ReadShared([]string{filepath.Join(project, "src")}, files, func() {}, len(files))
	read = route
	if err != nil {
		t.Fatal(err)
	}

	if asked := askedOfABridge(t, project, theShop...); len(asked) != 0 {
		t.Errorf("a run after a read side by side asked the bridge for %v again: a share keyed its line before another kept "+
			"the outline of a file it read", asked)
	}
}

// A file whose contents outline differently in two checkouts, because what it imports differs, never hands one
// checkout's reader the other's tree: cart.py's limit is an int in the project and a str in the worktree, and
// checkout.py, alike in both, is served the tree that knows its own checkout's limit.
func TestAFileOutlinedDifferentlyInTwoCheckoutsNeverServesTheOthersReader(t *testing.T) {
	if _, err := bridge.Mypy(); err != nil {
		t.Skipf("no Python bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/shop/__init__.py", "")
	written(t, project, "src/shop/prices.py", "def price() -> int:\n    return 3\n")
	written(t, project, "src/shop/cart.py", "from shop.prices import price\n\nlimit = price()\n")
	written(t, project, "src/shop/checkout.py", "from shop.cart import limit\n\n\ndef pay() -> None:\n    print(limit)\n")
	inRepository(t, project, "add", "-A")
	inRepository(t, project, "commit", "-m", "the shop")
	worktree := filepath.Join(project, ".journal", "worktrees", "worker-1")
	inRepository(t, project, "worktree", "add", "-b", "a-worker", worktree)

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	chain := append(slices.Clone(theShop), "src/shop/checkout.py")
	askedOf(t, Python, project, chain...)
	written(t, worktree, "src/shop/prices.py", "def price() -> str:\n    return 'three'\n")
	if _, stream := askedOf(t, Python, worktree, chain...); !limitIs(stream, "str") {
		t.Fatalf("the worktree's checkout.py does not read limit as a str: %v", limitsIn(stream))
	}

	if _, stream := askedOf(t, Python, project, chain...); !limitIs(stream, "int") {
		t.Errorf("the project's checkout.py was served the worktree's tree, where limit is %v, not an int", limitsIn(stream))
	}
	if _, stream := askedOf(t, Python, worktree, chain...); !limitIs(stream, "str") {
		t.Errorf("the worktree's checkout.py was served the project's tree, where limit is %v, not a str", limitsIn(stream))
	}
}

// limitIs says whether every limit checkout.py names resolves to the named builtin type.
func limitIs(stream *contract.Stream, builtin string) bool {
	limits := limitsIn(stream)

	return len(limits) > 0 && !slices.ContainsFunc(limits, func(resolved string) bool { return !strings.Contains(resolved, builtin) })
}

// limitsIn is the resolved type of every limit checkout.py names.
func limitsIn(stream *contract.Stream) []string {
	var limits []string
	var visit func(node *contract.Node)
	visit = func(node *contract.Node) {
		if node.Name == "limit" && node.Resolved != nil {
			limits = append(limits, node.Resolved.Text)
		}
		for _, child := range node.Children {
			visit(child)
		}
	}
	for _, file := range stream.Files {
		if filepath.Base(file.Path) == "checkout.py" && file.Root != nil {
			visit(file.Root)
		}
	}

	return limits
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
	read = func(b Bridge, kept *keptLines, roots, files, written []string, tally func()) (*contract.Stream, error) {
		asked = append(asked, written...)

		return route(b, kept, roots, files, written, tally)
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

// The Go bridge, which the binary carries and answers in this very process, keeps its lines in the one cache as every
// other bridge does: a second run of unchanged files starts no read of them.
func TestTheGoBridgeKeepsTheLinesItReads(t *testing.T) {
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/go.mod", "module shop\n\ngo 1.22\n")
	written(t, project, "src/prices.go", "package shop\n\nfunc price() int { return 3 }\n")
	written(t, project, "src/cart.go", "package shop\n\nfunc total() int { return price() * 2 }\n")
	inRepository(t, project, "add", "-A")
	inRepository(t, project, "commit", "-m", "the shop")

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	if asked, _ := askedOf(t, GoBridge, project, "src/cart.go", "src/prices.go"); len(asked) != 2 {
		t.Fatalf("the first run asked the Go bridge for %v, not both files", asked)
	}
	if asked, _ := askedOf(t, GoBridge, project, "src/cart.go", "src/prices.go"); len(asked) != 0 {
		t.Errorf("a second run of unchanged files asked the Go bridge for %v again: its lines were never kept", asked)
	}
}

// A release that leaves the Go bridge alone keeps every Go line: they are keyed on the bridge's own code, never the
// whole binary.
func TestAReleaseThatLeavesTheGoBridgeAloneKeepsItsLines(t *testing.T) {
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/go.mod", "module shop\n\ngo 1.22\n")
	written(t, project, "src/prices.go", "package shop\n\nfunc price() int { return 3 }\n")
	inRepository(t, project, "add", "-A")
	inRepository(t, project, "commit", "-m", "the shop")

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	askedOf(t, GoBridge, project, "src/prices.go")
	build := bridge.Build
	bridge.Build = func() string { return "a release that changed no bridge" }
	t.Cleanup(func() { bridge.Build = build })
	if asked, _ := askedOf(t, GoBridge, project, "src/prices.go"); len(asked) != 0 {
		t.Errorf("a release that left the Go bridge alone asked it for %v again", asked)
	}
}
