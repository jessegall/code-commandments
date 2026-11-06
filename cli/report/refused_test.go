package report

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
)

func filed(t *testing.T, args ...string) (code int, said string, warned string) {
	t.Helper()
	var out, errs bytes.Buffer
	code, err := Command{Version: "1.2.3"}.Run(cli.InputOf("report", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the command failed: %v", err)
	}

	return code, out.String(), errs.String()
}

func TestAReportWithoutAReasonSaysWhatIsMissing(t *testing.T) {
	code, _, warned := filed(t, "--ref=src/Order.php:12")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "--reason") {
		t.Errorf("it says %q, which does not name what is missing", warned)
	}
}

func TestABugReportMustReferenceTheCodeItIsAbout(t *testing.T) {
	code, _, warned := filed(t, "--reason=the scribe rewrote the wrong line")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "--ref") {
		t.Errorf("it says %q, which does not say to reference the code", warned)
	}
	if !strings.Contains(warned, "--global") {
		t.Errorf("it says %q, which does not offer the one way out for a bug tied to no file", warned)
	}
}

func TestADetectorReportConfrontsTheCleanestDesignFirst(t *testing.T) {
	const asksForADesign = "AllNullableDataDetector"

	if !requiresBestDesign(asksForADesign) {
		t.Fatalf("%s no longer asks for the cleanest design, so this test watches nothing: name a rule that does", asksForADesign)
	}

	code, _, warned := filed(t, "--detector="+asksForADesign, "--reason=it fires on correct code", "--ref=src/Order.php:12")

	if code == 0 {
		t.Error("the report was filed with no design named")
	}
	if !strings.Contains(warned, "--best-design") {
		t.Errorf("it says %q, which does not ask for the design", warned)
	}
	if !strings.Contains(warned, "ALREADY IS") {
		t.Errorf("it says %q, which does not put the litmus to the reader: the flagged code must already BE that design", warned)
	}
}
