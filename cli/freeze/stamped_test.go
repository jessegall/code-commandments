package freeze

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/engine"
)

func ran(t *testing.T, verb, path string) (code int, said string, after string) {
	t.Helper()
	var out, errs bytes.Buffer
	code, err := Command{}.Run(cli.InputOf(verb, path), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the command failed: %v", err)
	}
	written, readErr := os.ReadFile(path)
	if readErr != nil {
		return code, out.String() + errs.String(), ""
	}

	return code, out.String() + errs.String(), string(written)
}

func aFile(t *testing.T, name, content string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(root, name)
	if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
		t.Fatal(err)
	}

	return path
}

func TestTheStampGoesWhereItCannotBreakTheFile(t *testing.T) {
	for name, file := range map[string]struct{ name, content, first string }{
		"a PHP file": {"Order.php", "<?php\n\nfinal class Order {}\n", "<?php"},
		"a script":   {"deploy", "#!/bin/sh\necho shipping\n", "#!/bin/sh"},
	} {
		t.Run(name, func(t *testing.T) {
			path := aFile(t, file.name, file.content)

			code, said, after := ran(t, "freeze", path)

			if code != 0 {
				t.Fatalf("exits %d, saying %q", code, said)
			}

			lines := strings.Split(after, "\n")
			if lines[0] != file.first {
				t.Errorf("the file now opens with %q, and it opened with %q", lines[0], file.first)
			}
			if !strings.Contains(lines[1], engine.FrozenMarker) {
				t.Errorf("the stamp is not the second line:\n%s", after)
			}
		})
	}
}

func TestAnOrdinaryFileIsStampedOnItsFirstLine(t *testing.T) {
	path := aFile(t, "cart.py", "def total():\n    return 0\n")

	if code, said, _ := ran(t, "freeze", path); code != 0 {
		t.Fatalf("exits %d, saying %q", code, said)
	}

	_, _, after := ran(t, "freeze", path)
	if !strings.Contains(strings.Split(after, "\n")[0], engine.FrozenMarker) {
		t.Errorf("the stamp is not the first line:\n%s", after)
	}
}

func TestTheSourceSurvivesBeingFrozenAndThawed(t *testing.T) {
	const source = "<?php\n\nfinal class Order\n{\n    public function total(): int\n    {\n        return 0;\n    }\n}\n"
	path := aFile(t, "Order.php", source)

	if code, said, _ := ran(t, "freeze", path); code != 0 {
		t.Fatalf("freezing exits %d, saying %q", code, said)
	}

	code, said, after := ran(t, "unfreeze", path)
	if code != 0 {
		t.Fatalf("unfreezing exits %d, saying %q", code, said)
	}
	if after != source {
		t.Errorf("the file came back as:\n%q\nand it was:\n%q", after, source)
	}
}

func TestFreezingTwiceSaysSoAndWritesNothingMore(t *testing.T) {
	path := aFile(t, "cart.ts", "export const total = 0;\n")

	if code, said, _ := ran(t, "freeze", path); code != 0 {
		t.Fatalf("exits %d, saying %q", code, said)
	}
	once, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}

	code, said, after := ran(t, "freeze", path)

	if code != 0 {
		t.Errorf("exits %d, saying %q", code, said)
	}
	if !strings.Contains(said, "Already frozen") {
		t.Errorf("it says %q, which does not say the file was already frozen", said)
	}
	if after != string(once) {
		t.Errorf("a second freeze rewrote the file:\n%s", after)
	}
}

func TestThawingWhatWasNeverFrozenSaysSo(t *testing.T) {
	path := aFile(t, "cart.ts", "export const total = 0;\n")

	code, said, after := ran(t, "unfreeze", path)

	if code != 0 {
		t.Errorf("exits %d, saying %q", code, said)
	}
	if !strings.Contains(said, "Not frozen") {
		t.Errorf("it says %q, which does not say the file was never frozen", said)
	}
	if after != "export const total = 0;\n" {
		t.Errorf("it rewrote a file it had nothing to do to:\n%s", after)
	}
}

func TestAFileThatIsNotThereIsSaidRatherThanWritten(t *testing.T) {
	missing := filepath.Join(t.TempDir(), "nope.php")

	code, said, _ := ran(t, "freeze", missing)

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(said, "No such file") {
		t.Errorf("it says %q, which does not say the file is not there", said)
	}
	if _, err := os.Stat(missing); err == nil {
		t.Error("it created the file it was pointed at")
	}
}

func TestAVerbGivenNoFileSaysWhatToType(t *testing.T) {
	for _, verb := range []string{"freeze", "unfreeze"} {
		var out, errs bytes.Buffer
		code, err := Command{}.Run(cli.InputOf(verb), cli.Console{Out: &out, Err: &errs})

		if err != nil {
			t.Fatalf("%s failed: %v", verb, err)
		}
		if code != 2 {
			t.Errorf("%s with no file exits %d, want 2", verb, code)
		}
		if !strings.Contains(errs.String(), verb) {
			t.Errorf("%s says %q, which does not name the verb a reader typed", verb, errs.String())
		}
	}
}
