package laravel

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
)

func TestOnlyARouterGroupNamesItsRoutes(t *testing.T) {
	root := t.TempDir()
	for path, source := range map[string]string{
		"routes/web.php":     "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::name('admin.')->group(function () {\n    Route::get('/', fn () => 1)->name('home');\n});\n",
		"routes/bundles.php": "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::prefix('bundles')->middleware('auth')->as('application-bundles.')->group(function () {\n    Route::get('/download', fn () => 1)->name('download');\n});\n",
		"app/Menu.php":       "<?php\nnamespace App;\nfinal class Menu\n{\n    public function build(Items $items): \\Closure\n    {\n        $items->name('orphan.')->group(fn () => null);\n\n        return self::group(...);\n    }\n\n    public static function group(): void {}\n}\n",
	} {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, path), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	codebase, err := php.Here().Scan(root)
	if err != nil {
		t.Fatal(err)
	}
	names := RouteNamesOf(codebase)

	if !names.IsRegistered("admin.home") {
		t.Error("the route the router's group names is not registered")
	}
	if !names.IsRegistered("application-bundles.download") {
		t.Error("the route a group opened with as(), behind prefix and middleware, is not registered")
	}
	if names.IsRegistered("orphan") {
		t.Error("a group call on something other than the router names a route")
	}
}
