package scan

import (
	"encoding/json"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine/php"
)

// PHP reads through the one cache like every language: a second run of unchanged files asks the bridge for nothing,
// an edit asks for that file alone, and a class a file served from the cache declares never reads as outside the scan.
func TestPHPReadsThroughTheOneCache(t *testing.T) {
	if _, err := php.Here().Command(); err != nil {
		t.Skipf("no PHP bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/Order.php", "<?php\n\nnamespace Shop;\n\nfinal class Order {}\n")
	written(t, project, "src/Cart.php", "<?php\n\nnamespace Shop;\n\nfinal class Cart { public function __construct(public Order $order) {} }\n")

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	shop := []string{"src/Cart.php", "src/Order.php"}
	if asked, _ := askedOf(t, PHPBridge, project, shop...); len(asked) != 2 {
		t.Fatalf("the first run asked the bridge for %v, not both files", asked)
	}
	if asked, _ := askedOf(t, PHPBridge, project, shop...); len(asked) != 0 {
		t.Errorf("a second run of unchanged files asked the bridge for %v", asked)
	}

	written(t, project, "src/Cart.php", "<?php\n\nnamespace Shop;\n\nfinal class Cart { public function __construct(public ?Order $order) {} }\n")
	parsed := parsedToDeclare.Load()
	asked, stream := askedOf(t, PHPBridge, project, shop...)
	if !slices.Equal(asked, []string{filepath.Join(project, "src/Cart.php")}) {
		t.Errorf("an edit to Cart.php asked the bridge for %v, not Cart.php alone", asked)
	}
	if again := parsedToDeclare.Load() - parsed; again != 1 {
		t.Errorf("an edit to Cart.php had the bridge parse %d files to learn what they declare, where what Order.php declares is kept and only Cart.php is parsed", again)
	}
	if stream.Program != nil && slices.ContainsFunc(stream.Program.Symbols, func(symbol contract.OutsideSymbol) bool { return symbol.Symbol == `Shop\Order` }) {
		t.Errorf("Order, which a file of the scan declares, reads as outside it: %v", stream.Program.Symbols)
	}

	cache.KeepIn(t.TempDir())
	_, parsedWhole := askedOf(t, PHPBridge, project, shop...)
	cart := func(read *contract.Stream) string {
		for _, file := range read.Files {
			if strings.HasSuffix(file.Path, "Cart.php") {
				tree, err := json.Marshal(file)
				if err != nil {
					t.Fatal(err)
				}

				return string(tree)
			}
		}

		return "no Cart.php"
	}
	if kept, whole := cart(stream), cart(parsedWhole); kept != whole {
		t.Errorf("Cart.php read against what was kept of Order.php is not the tree read with both files parsed:\n%s\n%s", kept, whole)
	}
}

func TestFortifyRouteContextSharesTheCacheAcrossCheckouts(t *testing.T) {
	if _, err := php.Here().Command(); err != nil {
		t.Skipf("no PHP bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "composer.json", `{}`)
	written(t, project, "routes/web.php", `<?php Route::get('/', fn () => 1)->name('home');`)
	written(t, project, "vendor/laravel/fortify/composer.json", `{"extra":{"laravel":{"providers":["Laravel\\Fortify\\FortifyServiceProvider"]}}}`)
	written(t, project, "vendor/laravel/fortify/routes/routes.php", `<?php Route::post('/login', fn () => 1)->name('login.store');`)
	inRepository(t, project, "add", "-A")
	inRepository(t, project, "commit", "-m", "installed route context")
	worktree := filepath.Join(project, ".journal", "worktrees", "worker-1")
	inRepository(t, project, "worktree", "add", "-b", "a-worker", worktree)

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	for _, checkout := range []string{project, worktree} {
		sources := Walk([]string{checkout}, source.Excluded{})
		files := sources.Files(source.PHP)
		if len(files) != 3 {
			t.Fatalf("expected two project files and the installed route file, found %v", files)
		}
		var relative []string
		for _, file := range files {
			path, err := filepath.Rel(checkout, file)
			if err != nil {
				t.Fatal(err)
			}
			relative = append(relative, path)
		}
		asked, _ := askedOf(t, PHPBridge, checkout, relative...)
		if checkout == worktree && len(asked) != 0 {
			t.Errorf("the worktree reread cached PHP files: %v", asked)
		}

		codebase, err := sources.Load()
		if err != nil {
			t.Fatal(err)
		}
		installed := 0
		for _, call := range codebase.WhereKindReadAsContext("call").Get() {
			fromPackage := strings.HasSuffix(filepath.ToSlash(call.File()), "vendor/laravel/fortify/routes/routes.php")
			if fromPackage {
				installed++
			}
			if call.IsInDependency() != fromPackage {
				t.Errorf("only the installed route file is context, not judged: %s", call.Location())
			}
		}
		if installed == 0 {
			t.Error("the installed route was absent from the request context")
		}
	}

	written(t, project, "composer.json", `{"extra":{"laravel":{"dont-discover":["laravel/fortify"]}}}`)
	if files := Walk([]string{project}, source.Excluded{}).Files(source.PHP); len(files) != 2 {
		t.Errorf("the opted-out provider supplied context: %v", files)
	}
}

func TestAReadTicksItsProgressOnceForEachFileWhetherTheCacheOrTheBridgeReadIt(t *testing.T) {
	if _, err := php.Here().Command(); err != nil {
		t.Skipf("no PHP bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/Order.php", "<?php\n\nnamespace Shop;\n\nfinal class Order {}\n")
	written(t, project, "src/Cart.php", "<?php\n\nnamespace Shop;\n\nfinal class Cart { public function __construct(public Order $order) {} }\n")
	written(t, project, "src/Stock.php", "<?php\n\nnamespace Shop;\n\nfinal class Stock {}\n")
	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	files := []string{filepath.Join(project, "src/Cart.php"), filepath.Join(project, "src/Order.php"), filepath.Join(project, "src/Stock.php")}
	ticked := func() int {
		ticks := 0
		if _, err := PHPBridge.ReadShared([]string{filepath.Join(project, "src")}, files, func() { ticks++ }, 1); err != nil {
			t.Fatal(err)
		}

		return ticks
	}

	if ticks := ticked(); ticks != len(files) {
		t.Errorf("a first read of %d files ticked %d times", len(files), ticks)
	}
	if ticks := ticked(); ticks != len(files) {
		t.Errorf("a read of %d files the cache holds ticked %d times", len(files), ticks)
	}
	written(t, project, "src/Order.php", "<?php\n\nnamespace Shop;\n\nfinal class Order { public function paid(): bool { return true; } }\n")
	if ticks := ticked(); ticks != len(files) {
		t.Errorf("a read of %d files after the one another reads changed ticked %d times", len(files), ticks)
	}
}
