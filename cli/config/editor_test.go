package config

import (
	"os"
	"path/filepath"
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const arrayBag = `JesseGall\CodeCommandments\Sins\Backend\ArrayBag`

func TestAProjectWithNoConfigStartsWithConfigJSON(t *testing.T) {
	dir := t.TempDir()
	editor := EditorIn(dir)

	if disabled, err := editor.Disable(arrayBag); !disabled || err != nil {
		t.Fatalf("%v %v", disabled, err)
	}

	config, err := Load(dir)
	if err != nil || editor.Name() != ".commandments/config.json" || !reflect.DeepEqual(config.Disabled, []Rule{{Sin, catalog.Backend, "ArrayBag"}}) {
		t.Errorf("%s %+v %v", editor.Name(), config, err)
	}

	if again, _ := editor.Disable(arrayBag); again {
		t.Error("disabled twice")
	}

	if enabled, err := editor.Enable(arrayBag); !enabled || err != nil {
		t.Errorf("%v %v", enabled, err)
	}

	if config, _ := Load(dir); len(config.Disabled) != 0 {
		t.Errorf("still disabled: %+v", config.Disabled)
	}
}

func TestAProjectWithOnlyConfigPHPKeepsEditingIt(t *testing.T) {
	dir := t.TempDir()
	must(t, os.MkdirAll(filepath.Join(dir, ".commandments"), 0o755))
	must(t, os.WriteFile(workspace.Config(dir), []byte(Render([]string{"src"})), 0o644))

	if name := EditorIn(dir).Name(); name != ".commandments/config.php" {
		t.Errorf("edits %s", name)
	}
}

func TestLayersAreDeclaredRewrittenAndReadBackInConfigJSON(t *testing.T) {
	dir := t.TempDir()
	editor := EditorIn(dir)
	stack := []Layer{{`App\Domain`, nil}, {`App\Http`, []string{`App\Domain`}}}

	if written, _ := editor.EnsureLayers(stack); written {
		t.Error("declared layers with no config to declare them in")
	}

	_, err := editor.Scaffold([]string{"src"})
	must(t, err)

	if written, err := editor.EnsureLayers(stack); !written || err != nil {
		t.Fatalf("%v %v", written, err)
	}

	if written, _ := editor.EnsureLayers(stack); written {
		t.Error("declared layers twice")
	}

	grown := append(stack, Layer{`App\Console`, []string{`App\Domain`, `App\Http`}})
	if rewritten, err := editor.RewriteLayers(grown); !rewritten || err != nil {
		t.Fatalf("%v %v", rewritten, err)
	}

	if read, err := editor.Layers(); err != nil || !reflect.DeepEqual(read, grown) {
		t.Errorf("%+v %v", read, err)
	}

	if config, _ := Load(dir); !reflect.DeepEqual(config.Paths, []string{"src"}) || len(config.Configurators) != 1 {
		t.Errorf("%+v", config)
	}
}

func TestTheDeclarationShowsTheConfigureBlockConfigJSONWrites(t *testing.T) {
	want := `    "configure": {
        "backend/NamespaceDependencyDetector": [
            {
                "layer": [
                    "App\\Domain"
                ]
            }
        ]
    }`

	if got := EditorIn(t.TempDir()).Declaration([]Layer{{`App\Domain`, nil}}); got != want {
		t.Errorf("got\n%s", got)
	}
}

func TestAProjectsOwnDetectorIsRegisteredByName(t *testing.T) {
	dir := t.TempDir()
	editor := EditorIn(dir)

	if registered, err := editor.RegisterDetector(`App\Commandments\NoRawSqlDetector`); !registered || err != nil {
		t.Fatalf("%v %v", registered, err)
	}

	if again, _ := editor.RegisterDetector(`App\Commandments\NoRawSqlDetector`); again {
		t.Error("registered twice")
	}

	if config, _ := Load(dir); !reflect.DeepEqual(config.Detectors, []string{"NoRawSqlDetector"}) {
		t.Errorf("%+v", config.Detectors)
	}
}
