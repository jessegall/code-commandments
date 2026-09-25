package python_test

import (
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/python"
)

func TestThePackageGraphDrawsTheArrowsThePHPEngineDraws(t *testing.T) {
	codebase := shopFixture(t)
	var want struct {
		Arrows  []string `json:"arrows"`
		Closing []string `json:"closing"`
	}
	golden(t, "packages", &want)
	relative := func(folder string) string { return strings.TrimPrefix(folder, fixture.root+string(filepath.Separator)) }
	var arrows, closing []string
	for _, arrow := range codebase.Program.PackageArrows() {
		arrows = append(arrows, place(python.Node{Match: arrow.At})+" "+relative(arrow.From)+" -> "+relative(arrow.To))
	}
	for _, at := range codebase.Program.PackageArrows().ClosingAMutualPair() {
		closing = append(closing, place(python.Node{Match: at}))
	}
	for _, list := range [][]string{arrows, closing, want.Arrows, want.Closing} {
		slices.Sort(list)
	}
	if strings.Join(arrows, "\n") != strings.Join(want.Arrows, "\n") {
		t.Errorf("the arrows are\n%s\nnot\n%s", strings.Join(arrows, "\n"), strings.Join(want.Arrows, "\n"))
	}
	if strings.Join(closing, ",") != strings.Join(want.Closing, ",") {
		t.Errorf("the closing arrows are %v, not %v", closing, want.Closing)
	}
}
