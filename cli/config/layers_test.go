package config

import (
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
)

func TestTheLayersAProjectDeclaresAreReadPerEngine(t *testing.T) {
	config, err := ReadJSON(writeJSON(t, `{
    "configure": {
        "backend/NamespaceDependencyDetector": [
            {"layer": ["\\App\\Domain"]},
            {"layer": ["App\\Http", ["App\\Domain", "App\\Support"]]}
        ],
        "python/NamespaceDependencyDetector": [
            {"layer": ["shop.domain"]}
        ],
        "backend/DeepNestedDetector": [
            {"layer": ["Not\\A\\Layer"]}
        ]
    }
}`))
	if err != nil {
		t.Fatal(err)
	}

	if got, want := config.Layers(catalog.Backend), map[string][]string{
		`App\Domain`: nil,
		`App\Http`:   {`App\Domain`, `App\Support`},
	}; !reflect.DeepEqual(got, want) {
		t.Errorf("backend layers: %v, want %v", got, want)
	}

	if got, want := config.Layers(catalog.Python), map[string][]string{"shop.domain": nil}; !reflect.DeepEqual(got, want) {
		t.Errorf("python layers: %v, want %v", got, want)
	}

	if got := config.Layers(catalog.CSharp); len(got) != 0 {
		t.Errorf("csharp declares no layers, got %v", got)
	}
}
