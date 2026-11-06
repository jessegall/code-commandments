package repent

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
)

const sinful = `<?php

final class Orders
{
    public function announce(bool $urgent, Mailer $mailer): void
    {
        $urgent ? $mailer->rush() : $mailer->queue();
    }
}
`

func repented(t *testing.T, project, file string, args ...string) (code int, said string, after string) {
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
	code, err = Command{}.Run(cli.InputOf("repent", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the command failed: %v", err)
	}

	written, readErr := os.ReadFile(filepath.Join(project, file))
	if readErr != nil {
		t.Fatal(readErr)
	}

	return code, out.String() + errs.String(), string(written)
}

func aProjectHolding(t *testing.T, name, content string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for path, written := range map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/" + name:               content,
	} {
		full := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(full, []byte(written), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func TestAPreviewWritesNothingIntoTheFile(t *testing.T) {
	project := aProjectHolding(t, "Orders.php", sinful)

	code, said, after := repented(t, project, "src/Orders.php", "src", "--dry-run")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, said)
	}
	if after != sinful {
		t.Errorf("a preview rewrote the file:\n%s", after)
	}
	if !strings.Contains(said, "Orders.php") {
		t.Errorf("a preview says %q, which does not name the file it would change", said)
	}
}

func TestRepentingRewritesTheSinAtItsSource(t *testing.T) {
	project := aProjectHolding(t, "Orders.php", sinful)

	code, said, after := repented(t, project, "src/Orders.php", "src")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, said)
	}
	if after == sinful {
		t.Fatalf("the file was not rewritten; it said:\n%s", said)
	}
	if strings.Contains(after, "? $mailer->rush() :") {
		t.Errorf("the ternary statement is still there:\n%s", after)
	}
	for _, kept := range []string{"rush()", "queue()", "$urgent"} {
		if !strings.Contains(after, kept) {
			t.Errorf("the rewrite dropped %s:\n%s", kept, after)
		}
	}
}

func TestAPathThatIsNoFolderIsSaidRatherThanWritten(t *testing.T) {
	project := aProjectHolding(t, "Orders.php", sinful)

	code, said, after := repented(t, project, "src/Orders.php", "nope")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(said, "Not a directory") {
		t.Errorf("it says %q, which does not say the path is no folder", said)
	}
	if after != sinful {
		t.Errorf("it rewrote the file anyway:\n%s", after)
	}
}

func TestATreeWithNothingToFixIsLeftAlone(t *testing.T) {
	const clean = "<?php\n\nfinal class Orders\n{\n    public function count(): int\n    {\n        return 0;\n    }\n}\n"
	project := aProjectHolding(t, "Orders.php", clean)

	code, said, after := repented(t, project, "src/Orders.php", "src")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, said)
	}
	if after != clean {
		t.Errorf("a clean tree was rewritten:\n%s", after)
	}
	if strings.TrimSpace(said) == "" {
		t.Error("a clean run said nothing at all")
	}
}

func TestOnlyTheRuleNamedIsApplied(t *testing.T) {
	project := aProjectHolding(t, "Orders.php", sinful)

	code, said, after := repented(t, project, "src/Orders.php", "src", "--only=NoSuchRuleOfOurs")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, said)
	}
	if after != sinful {
		t.Errorf("a run naming a rule nobody has rewrote the file anyway:\n%s", after)
	}
}
