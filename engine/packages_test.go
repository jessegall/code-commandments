package engine_test

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

// packaged is a codebase of one file of the language holding one call per target given, in a project whose manifest
// is the file named, written as given; no manifest when its name is empty.
func packaged(t *testing.T, language, manifest, written string, targets ...map[string]string) []engine.Match {
	t.Helper()
	root := t.TempDir()
	if manifest != "" {
		if err := os.WriteFile(filepath.Join(root, manifest), []byte(written), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	var statements []string
	for at, target := range targets {
		call, _ := json.Marshal(map[string]any{
			"id": 2 + 3*at, "kind": "expression-statement", "span": []int{at, at + 1, at + 1},
			"parts": map[string]any{"expression": map[string]any{
				"id": 3 + 3*at, "kind": "call", "span": []int{at, at + 1, at + 1}, "target": target,
				"parts": map[string]any{"callee": map[string]any{"id": 4 + 3*at, "kind": "reference", "span": []int{at, at + 1, at + 1}, "name": target["name"]}},
			}},
		})
		statements = append(statements, string(call))
	}
	path := filepath.Join(root, "probe.source")
	stream := fmt.Sprintf(`{"header": {"contract": "tree", "version": 5, "language": %q, "bridge": {"name": "probe", "version": "8.0.0"}, "roots": [%q]}}
{"file": {"path": %q, "language": %q, "errors": 0, "module": "probe", "resolver": {"tool": %q, "ran": true}, "root": {"id": 0, "kind": "file", "span": [0, 99, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 99, 1], "name": "probe", "parts": {"statements": [%s]}}]}}, "comments": []}}
{"trailer": {"files": 1}}
`, language, root, path, language, map[string]string{"php": "php-parser", "csharp": "roslyn", "python": "mypy", "typescript": "tsc", "go": "go/types"}[language], strings.Join(statements, ", "))
	codebase, err := engine.FromString(stream, nil)
	if err != nil {
		t.Fatal(err)
	}

	return codebase.WhereKind("call").Get()
}

func init() {
	engine.RegisterPackageFacts(engine.PackageFacts{Package: "laravel/framework", Ecosystem: engine.Composer, Versions: ">=8", Entries: []engine.PackageEntry{
		{Type: `Illuminate\Database\Eloquent\Builder`, Name: "get", Effect: "executes-query", Execution: "call"},
	}})
	engine.RegisterPackageFacts(engine.PackageFacts{Package: "spatie/laravel-data", Ecosystem: engine.Composer, Versions: ">=3", Entries: []engine.PackageEntry{
		{Type: `Spatie\LaravelData\Data`, Name: "from", Effect: "cast-result-type", Execution: "call"},
	}})
	engine.RegisterPackageFacts(engine.PackageFacts{Package: "Microsoft.EntityFrameworkCore", Ecosystem: engine.NuGet, Versions: ">=6", Entries: []engine.PackageEntry{
		{Type: "System.Linq.Queryable", Name: "Where", Effect: "deferred-query", Execution: "iteration"},
		{Type: "Microsoft.EntityFrameworkCore.EntityFrameworkQueryableExtensions", Name: "ToListAsync", Effect: "executes-query", Execution: "call"},
	}})
}

const composerLock = `{"packages": [{"name": "laravel/framework", "version": "v10.48.2"}, {"name": "spatie/laravel-data", "version": "4.5.0"}]}`

func TestHasEffectOfALaravelQueryMethodAndNotOfALookalike(t *testing.T) {
	calls := packaged(t, "php", "composer.lock", composerLock,
		map[string]string{"symbol": `Illuminate\Database\Eloquent\Builder::get`, "type": `Illuminate\Database\Eloquent\Builder`, "name": "get"},
		map[string]string{"symbol": `App\Reports::get`, "type": `App\Reports`, "name": "get"},
	)
	if calls[0].HasEffect("executes-query") != engine.True || calls[1].HasEffect("executes-query") != engine.False {
		t.Errorf("the builder's get reads %v and a lookalike get %v", calls[0].HasEffect("executes-query"), calls[1].HasEffect("executes-query"))
	}
}

func TestHasEffectOfASpatieCast(t *testing.T) {
	calls := packaged(t, "php", "composer.lock", composerLock,
		map[string]string{"symbol": `Spatie\LaravelData\Data::from`, "type": `Spatie\LaravelData\Data`, "name": "from"})
	if calls[0].HasEffect("cast-result-type") != engine.True {
		t.Errorf("Data::from casts: %v", calls[0].HasEffect("cast-result-type"))
	}
}

func TestHasEffectOfAnEFCoreDeferredQueryAndAnExecutingOne(t *testing.T) {
	project := `<Project Sdk="Microsoft.NET.Sdk"><ItemGroup><PackageReference Include="Microsoft.EntityFrameworkCore" Version="8.0.4" /></ItemGroup></Project>`
	calls := packaged(t, "csharp", "Shop.csproj", project,
		map[string]string{"symbol": "System.Linq.Queryable.Where", "type": "System.Linq.Queryable", "name": "Where"},
		map[string]string{"symbol": "Microsoft.EntityFrameworkCore.EntityFrameworkQueryableExtensions.ToListAsync", "type": "Microsoft.EntityFrameworkCore.EntityFrameworkQueryableExtensions", "name": "ToListAsync"},
	)
	where, list := calls[0], calls[1]
	if where.HasEffect("deferred-query") != engine.True || where.HasEffect("executes-query") != engine.False || list.HasEffect("executes-query") != engine.True {
		t.Errorf("Where defers %v and executes %v; ToListAsync executes %v", where.HasEffect("deferred-query"), where.HasEffect("executes-query"), list.HasEffect("executes-query"))
	}
}

func TestAPackageVersionPinnedCentrallyIsHeld(t *testing.T) {
	pins := `<Project><ItemGroup><PackageVersion Include="Microsoft.EntityFrameworkCore" Version="8.0.4" /></ItemGroup></Project>`
	calls := packaged(t, "csharp", "Directory.Packages.props", pins,
		map[string]string{"symbol": "Microsoft.EntityFrameworkCore.EntityFrameworkQueryableExtensions.ToListAsync", "type": "Microsoft.EntityFrameworkCore.EntityFrameworkQueryableExtensions", "name": "ToListAsync"},
	)
	if effect := calls[0].HasEffect("executes-query"); effect != engine.True {
		t.Errorf("with EF Core pinned in Directory.Packages.props, ToListAsync executes %v", effect)
	}
}

func TestPackageEffectIsUnknownWithoutALockFile(t *testing.T) {
	calls := packaged(t, "php", "", "",
		map[string]string{"symbol": `Illuminate\Database\Eloquent\Builder::get`, "type": `Illuminate\Database\Eloquent\Builder`, "name": "get"})
	if effect := calls[0].HasEffect("executes-query"); effect != engine.Unknown {
		t.Errorf("with no composer.lock the builder's get reads %v", effect)
	}
}

func TestPackageVersionOutsideTheRangeHasNoEffect(t *testing.T) {
	calls := packaged(t, "php", "composer.lock", `{"packages": [{"name": "laravel/framework", "version": "v7.30.0"}]}`,
		map[string]string{"symbol": `Illuminate\Database\Eloquent\Builder::get`, "type": `Illuminate\Database\Eloquent\Builder`, "name": "get"})
	if effect := calls[0].HasEffect("executes-query"); effect != engine.False {
		t.Errorf("Laravel 7 reads %v", effect)
	}
	if !engine.InVersionRange("10.48.2", ">=8 <11") || engine.InVersionRange("11.0", ">=8 <11") {
		t.Errorf("the version range reads wrongly")
	}
}

func init() {
	engine.RegisterPackageFacts(engine.PackageFacts{Package: "laravel/framework", Ecosystem: engine.Composer, Versions: ">=8",
		Exemptions: []engine.PackageExemption{{Tag: "validation-response", On: map[string][]string{`Illuminate\Validation\ValidationException`: {"withMessages"}}}},
		Identities: []engine.PackageIdentity{{Name: "probe-request", Type: `Illuminate\Http\Request`}},
	})
}

func TestExemptedByAResolvedLibraryMethodAndNotALookalike(t *testing.T) {
	calls := packaged(t, "php", "composer.lock", composerLock,
		map[string]string{"symbol": `Illuminate\Validation\ValidationException::withMessages`, "type": `Illuminate\Validation\ValidationException`, "name": "withMessages"},
		map[string]string{"symbol": `App\ValidationException::withMessages`, "type": `App\ValidationException`, "name": "withMessages"},
	)
	if calls[0].ExemptedBy("validation-response") != engine.True || calls[1].ExemptedBy("validation-response") != engine.False {
		t.Errorf("the library's withMessages is excused %v, a lookalike %v", calls[0].ExemptedBy("validation-response"), calls[1].ExemptedBy("validation-response"))
	}
	if calls[0].ExemptedBy("boundary") != engine.False {
		t.Errorf("another tag excuses it: %v", calls[0].ExemptedBy("boundary"))
	}
}

func TestIdentityNamesALibraryType(t *testing.T) {
	if named, found := engine.PackageIdentityNamed("probe-request"); !found || named != `Illuminate\Http\Request` {
		t.Errorf("the identity reads %q, %v", named, found)
	}
}

func TestExemptedByAnAttributeThePackageNames(t *testing.T) {
	engine.RegisterPackageFacts(engine.PackageFacts{Package: "probe/attributes", Ecosystem: engine.Composer, Versions: ">=1",
		Exemptions: []engine.PackageExemption{{Tag: "probe-attribute", Attributes: []string{`Probe\Singleton`}}}})
	root := t.TempDir()
	if err := os.WriteFile(filepath.Join(root, "composer.lock"), []byte(`{"packages": [{"name": "probe/attributes", "version": "1.0.0"}]}`), 0o644); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(root, "probe.source")
	stream := fmt.Sprintf(`{"header": {"contract": "tree", "version": 5, "language": "php", "bridge": {"name": "probe", "version": "8.0.0"}, "roots": [%q]}}
{"file": {"path": %q, "language": "php", "errors": 0, "module": "probe", "resolver": {"tool": "php-parser", "ran": true}, "root": {"id": 0, "kind": "file", "span": [0, 99, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 99, 1], "name": "probe", "parts": {"statements": [ {"id": 2, "kind": "annotation", "span": [0, 1, 1], "name": "Singleton", "refers": "Probe\\Singleton", "parts": {"callee": {"id": 3, "kind": "reference", "span": [0, 1, 1], "name": "Singleton", "refers": "Probe\\Singleton"}}}, {"id": 4, "kind": "annotation", "span": [1, 2, 2], "name": "Scoped", "refers": "Probe\\Scoped", "parts": {"callee": {"id": 5, "kind": "reference", "span": [1, 2, 2], "name": "Scoped", "refers": "Probe\\Scoped"}}}]}}]}}, "comments": []}}
{"trailer": {"files": 1}}
`, root, path)
	codebase, err := engine.FromString(stream, nil)
	if err != nil {
		t.Fatal(err)
	}
	annotations := codebase.WhereKind("annotation").Get()
	if len(annotations) != 2 || annotations[0].ExemptedBy("probe-attribute") != engine.True || annotations[1].ExemptedBy("probe-attribute") != engine.False {
		t.Errorf("the named attribute and another read %d annotations: %v", len(annotations), annotations)
	}
}
