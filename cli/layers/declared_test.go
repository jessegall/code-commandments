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

// The layer stack a project declares is the one thing the dependency rule reads, and nothing in this package was
// held to anything: the seventeen recorded parity cases ran the built binary. The command reads the project's own
// config and writes it back, so its answers are held here, the refusals first, because a layer silently not added
// is a rule silently not enforced.

// asked runs the command inside the project and answers its exit code and what it said.
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

// aProjectDeclaring is a project whose config declares the layers written in it.
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

// stackOf are the layers the project declares, in order, as the config reads them back.
func stackOf(t *testing.T, project string) []config.Layer {
	t.Helper()
	layers, err := config.EditorIn(project).Layers()
	if err != nil {
		t.Fatal(err)
	}

	return layers
}

// TestAVerbGivenTooLittleSaysWhatToType holds the two verbs that take arguments: a reader who typed half of one is
// shown the form rather than left with a config that silently did not change.
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

// TestAllowingFromALayerNobodyDeclaredIsRefused holds the one answer that keeps the stack honest: a rule written
// from a layer that is not declared would be read by nothing, so the reader is told to declare it first.
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

// TestAllowingWhatIsAlreadyAllowedChangesNothing holds the second run: the reader is told it was already so, and
// the stack is not written again.
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

// TestAllowingWidensTheLayerItNames holds the verb doing its work: the target is added to what that layer may use,
// and nothing else about the stack moves.
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

// TestAddingALayerDeclaresItAndWhatItMayUse holds `layers add`: the namespace joins the stack with the layers the
// option names, and the empty entries a careless list carries are left out.
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

// contains says whether the list holds the value.
func contains(list []string, value string) bool {
	for _, each := range list {
		if each == value {
			return true
		}
	}

	return false
}
