package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var pageObjectMissingTypeScript = scribeCase{spatie.PageObjectMissingTypeScriptDetector{}, PageObjectMissingTypeScriptScribe{}}

func TestPageObjectMissingTypeScriptStampsTypescriptAndImportsItOnAPageObject(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace Illuminate\Routing { class Controller {} }

namespace App {
    use Illuminate\Routing\Controller;
    use Spatie\LaravelData\Data;

    class Header extends Data { public function __construct(public string $title = '') {} }
    class Sidebar extends Data { public function __construct(public string $nav = '') {} }

    final class Dashboard extends Data
    {
        public function __construct(
            public readonly Header $header,
            public readonly Sidebar $sidebar,
        ) {}
    }

    class PageController extends Controller
    {
        public function show(): Dashboard { return Dashboard::from([]); }
    }
}`

	fixed := pageObjectMissingTypeScript.fixStable(t, source)

	if !strings.Contains(fixed, `#[TypeScript]
    final class Dashboard extends Data`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `use Spatie\TypeScriptTransformer\Attributes\TypeScript;`) {
		t.Fatalf("%s", fixed)
	}
}

func TestPageObjectMissingTypeScriptLeavesAPageObjectThatAlreadyHasTypescript(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace Illuminate\Routing { class Controller {} }
namespace Spatie\TypeScriptTransformer\Attributes { #[\Attribute] class TypeScript {} }

namespace App {
    use Illuminate\Routing\Controller;
    use Spatie\LaravelData\Data;
    use Spatie\TypeScriptTransformer\Attributes\TypeScript;

    class Header extends Data { public function __construct(public string $title = '') {} }
    class Sidebar extends Data { public function __construct(public string $nav = '') {} }

    #[TypeScript]
    final class Dashboard extends Data
    {
        public function __construct(
            public readonly Header $header,
            public readonly Sidebar $sidebar,
        ) {}
    }

    class PageController extends Controller
    {
        public function show(): Dashboard { return Dashboard::from([]); }
    }
}`

	if pageObjectMissingTypeScript.rewrote(t, source) {
		t.Fatal("expected false")
	}
}
