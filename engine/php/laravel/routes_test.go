package laravel

import (
	"os"
	"path/filepath"
	"slices"
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

func TestNamedRoutePathMatchesRequireALiteralSegment(t *testing.T) {
	root := t.TempDir()
	if err := os.WriteFile(filepath.Join(root, "routes.php"), []byte(`<?php
use Illuminate\Support\Facades\Route;
Route::get('/{path?}', fn () => 1)->name('client');
Route::get('/shops/{shop}', fn () => 1)->name('shops.show');
Route::get('/dashboard', fn () => 1)->name('dashboard');
`), 0o644); err != nil {
		t.Fatal(err)
	}
	codebase, err := php.Here().Scan(root)
	if err != nil {
		t.Fatal(err)
	}
	paths := RoutePathsOf(codebase)
	if paths.Matches([]string{"^orders$"}) || paths.Matches([]string{"tmp"}) {
		t.Error("a parameter-only route matches arbitrary regexes and filesystem paths")
	}
	if !paths.Matches([]string{"shops", "42"}) || !paths.Matches([]string{"dashboard"}) {
		t.Error("a route with a matching literal segment was missed")
	}
}

func TestInstalledFortifyRoutesRespectConfiguration(t *testing.T) {
	root := t.TempDir()
	write := func(path, source string) string {
		t.Helper()
		file := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
		return file
	}
	web := write("routes/web.php", `<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => 1)->name('home');
`)
	installed := write("vendor/laravel/fortify/routes/routes.php", `<?php
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
Route::group([], function () {
    $views = config('fortify.views', true);
    if ($views) { Route::get('/login', fn () => 1)->name('login'); }
    Route::post('/login', fn () => 1)->name('login.store');
    if (Features::enabled(Features::twoFactorAuthentication())) {
        Route::post('/challenge', fn () => 1)->name('two-factor.login.store');
    }
    if (Features::enabled(Features::registration())) {
        Route::post('/register', fn () => 1)->name('register.store');
    }
});
`)
	config := write("config/fortify.php", `<?php
use Laravel\Fortify\Features;
return ['views' => false, 'features' => [Features::twoFactorAuthentication()]];
`)
	defaults := write("vendor/laravel/fortify/config/fortify.php", `<?php
use Laravel\Fortify\Features;
return ['views' => true, 'features' => [Features::registration()]];
`)
	check := func(roots []string, registered ...string) {
		t.Helper()
		codebase, err := php.Here().Scan(roots...)
		if err != nil {
			t.Fatal(err)
		}
		codebase.Rooted(root)
		names := RouteNamesOf(codebase)
		for _, name := range []string{"home", "login", "login.store", "two-factor.login.store", "register.store"} {
			if names.IsRegistered(name) != slices.Contains(registered, name) {
				t.Errorf("route %s registration = %t", name, names.IsRegistered(name))
			}
		}
	}
	check([]string{web, config, installed, defaults}, "home", "login.store", "two-factor.login.store")
	check([]string{web, installed, defaults}, "home", "login.store")
	ignored := write("app/Ignore.php", `<?php \Laravel\Fortify\Fortify::ignoreRoutes();`)
	check([]string{web, config, installed, ignored}, "home")
}
