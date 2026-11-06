package layers

import (
	"bytes"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
)

func asked(t *testing.T, project string, args ...string) (code int, said string, warned string) {
	t.Helper()
	was, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	if err := os.Chdir(project); err != nil {
		t.Fatal(err)
	}
	defer os.Chdir(was)

	var out, errs bytes.Buffer
	code, err = Command{}.Run(cli.InputOf("layers", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the command failed: %v", err)
	}

	return code, out.String(), errs.String()
}

func aProjectDeclaring(t *testing.T, declaration string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(root, ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, ".commandments", "config.json"), []byte(declaration), 0o644); err != nil {
		t.Fatal(err)
	}

	return root
}

func stackOf(t *testing.T, project string) []config.Layer {
	t.Helper()
	layers, err := config.EditorIn(project).Layers()
	if err != nil {
		t.Fatal(err)
	}

	return layers
}

func TestAVerbGivenTooLittleSaysWhatToType(t *testing.T) {
	project := aProjectDeclaring(t, `{"paths": ["src"]}`)

	for name, args := range map[string][]string{
		"add with no namespace": {"add"},
		"allow with no target":  {"allow", `Shop\Ui\Pages`},
	} {
		t.Run(name, func(t *testing.T) {
			code, said, warned := asked(t, project, args...)

			if code == 0 {
				t.Errorf("it answered as if the arguments were there:\n%s", said)
			}
			if !strings.Contains(said+warned, "layers") {
				t.Errorf("it says %q, which does not show the form to type", said+warned)
			}
		})
	}
}

func TestAllowingFromALayerNobodyDeclaredIsRefused(t *testing.T) {
	project := aProjectDeclaring(t, `{
        "paths": ["src"],
        "configure": {"php/NamespaceDependencyDetector": [{"layer": ["Shop\\Ui\\Pages", ["Shop\\Ui\\Shared"]]}]}
    }`)

	code, said, warned := asked(t, project, "allow", `Shop\Nope`, `Shop\Ui\Shared`)

	if code == 0 {
		t.Errorf("it answered as if the layer were declared:\n%s", said)
	}
	if !strings.Contains(said+warned, "Shop\\Nope") {
		t.Errorf("it says %q, which does not name the layer that is not declared", said+warned)
	}
}

func TestAllowingWhatIsAlreadyAllowedChangesNothing(t *testing.T) {
	project := aProjectDeclaring(t, `{
        "paths": ["src"],
        "configure": {"php/NamespaceDependencyDetector": [{"layer": ["Shop\\Ui\\Pages", ["Shop\\Ui\\Shared"]]}]}
    }`)
	before := stackOf(t, project)

	code, said, warned := asked(t, project, "allow", `Shop\Ui\Pages`, `Shop\Ui\Shared`)

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, warned)
	}
	if !strings.Contains(said, "already") {
		t.Errorf("it says %q, which does not say it was already allowed", said)
	}

	after := stackOf(t, project)
	if len(after) != len(before) || len(after[0].MayUse) != len(before[0].MayUse) {
		t.Errorf("the stack was rewritten for a change that was not one: %v", after)
	}
}

func TestAllowingWidensTheLayerItNames(t *testing.T) {
	project := aProjectDeclaring(t, `{
        "paths": ["src"],
        "configure": {"php/NamespaceDependencyDetector": [
            {"layer": ["Shop\\Ui\\Pages", ["Shop\\Ui\\Shared"]]},
            {"layer": ["Shop\\Ui\\Shared", []]}
        ]}
    }`)

	code, said, warned := asked(t, project, "allow", `Shop\Ui\Shared`, `Shop\Ui\Tokens`)

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, warned)
	}
	if !strings.Contains(said, "Tokens") {
		t.Errorf("it says %q, which does not name what is now allowed", said)
	}

	for _, layer := range stackOf(t, project) {
		if !strings.HasSuffix(layer.Namespace, "Shared") {
			continue
		}
		if !contains(layer.MayUse, `Shop\Ui\Tokens`) {
			t.Errorf("the layer may use %v, which does not hold what was just allowed", layer.MayUse)
		}
	}
}

func TestAddingALayerDeclaresItAndWhatItMayUse(t *testing.T) {
	project := aProjectDeclaring(t, `{
        "paths": ["src"],
        "configure": {"php/NamespaceDependencyDetector": [{"layer": ["Shop\\Ui\\Shared", []]}]}
    }`)

	code, said, warned := asked(t, project, "add", `\Shop\Ui\Widgets`, `--may-use=Shop\Ui\Elements,,Shop\Ui\Tokens`)

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, warned)
	}
	if !strings.Contains(said, "Widgets") {
		t.Errorf("it says %q, which does not name the layer it declared", said)
	}

	stack := stackOf(t, project)
	at := slices.IndexFunc(stack, func(layer config.Layer) bool { return strings.HasSuffix(layer.Namespace, "Widgets") })
	if at < 0 {
		t.Fatalf("the stack does not hold the layer just added: %v", stack)
	}
	declared := stack[at]
	if strings.HasPrefix(declared.Namespace, `\`) {
		t.Errorf("the layer is declared as %q, with the leading separator a reader typed", declared.Namespace)
	}
	if contains(declared.MayUse, "") {
		t.Errorf("it may use %v, which holds an empty name from the list it was given", declared.MayUse)
	}
}

func contains(list []string, value string) bool {
	for _, each := range list {
		if each == value {
			return true
		}
	}

	return false
}
