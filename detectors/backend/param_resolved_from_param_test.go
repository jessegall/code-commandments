package backend_test

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine/php"
)

const packages = `<?php
namespace Illuminate\Http { class Request {} class RedirectResponse {} }
namespace Illuminate\Foundation\Http { class FormRequest extends \Illuminate\Http\Request { public function validated(): array { return []; } } }
namespace Lorisleiva\Actions { class ActionRequest extends \Illuminate\Foundation\Http\FormRequest {} }
namespace Lorisleiva\Actions\Concerns { trait AsAction { public static function run(mixed ...$arguments): mixed { return null; } } }
`

const action = `<?php

namespace App\Actions;

use App\Http\RequestState;
use App\Models\Trailer;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateTrailer
{
    use AsAction;

    public function handle(Trailer $trailer, array $input): void
    {
        $trailer->vin = $input['vin'];
    }

    public function asController(PARAMSRequestState $state, string $id): RedirectResponse
    {
        $trailer = $state->organization->trailers()->findOrFail($id);

        self::run($trailer, []);

        return new RedirectResponse();
    }
}
`

func TestAnEntryTakingAPackagesRequestIsABoundary(t *testing.T) {
	for name, test := range map[string]struct {
		params string
		want   int
	}{
		"an entry taking the package's request": {"ActionRequest $request, ", 0},
		"a method taking no request":            {"", 1},
	} {
		t.Run(name, func(t *testing.T) {
			found := paramResolvedIn(t, map[string]string{
				"vendor/autoload.php":           "<?php\nrequire_once __DIR__ . '/packages.php';\n",
				"vendor/packages.php":           packages,
				"composer.json":                 `{"require": {"lorisleiva/laravel-actions": "^2.8"}}`,
				"app/Actions/UpdateTrailer.php": strings.Replace(action, "PARAMS", test.params, 1),
				"app/Http/RequestState.php":     "<?php\n\nnamespace App\\Http;\n\nuse App\\Models\\Organization;\n\nfinal class RequestState\n{\n    public function __construct(public readonly Organization $organization)\n    {\n    }\n}\n",
				"app/Models/Organization.php":   "<?php\n\nnamespace App\\Models;\n\nfinal class Organization\n{\n    public function trailers(): TrailerQuery\n    {\n        return new TrailerQuery();\n    }\n}\n",
				"app/Models/TrailerQuery.php":   "<?php\n\nnamespace App\\Models;\n\nfinal class TrailerQuery\n{\n    public function findOrFail(string $id): Trailer\n    {\n        return new Trailer();\n    }\n}\n",
				"app/Models/Trailer.php":        "<?php\n\nnamespace App\\Models;\n\nfinal class Trailer\n{\n    public string $vin = '';\n}\n",
			})

			if len(found) != test.want {
				t.Fatalf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}

func paramResolvedIn(t *testing.T, files map[string]string) []string {
	t.Helper()
	root := t.TempDir()
	for name, source := range files {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	codebase, err := php.Here().Scan(filepath.Join(root, "app"))
	if err != nil {
		t.Fatal(err)
	}
	var found []string
	for _, match := range (backend.ParamResolvedFromParamDetector{}).Find(codebase) {
		found = append(found, match.Location())
	}

	return found
}
