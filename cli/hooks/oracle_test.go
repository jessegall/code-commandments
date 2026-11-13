package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
)

func oracleOver(t *testing.T, checkout, file string, settings config.Config) []string {
	t.Helper()

	codebase, err := scan.Walk([]string{checkout}, source.Excluded{}).Load()
	if err != nil {
		t.Fatalf("the fresh read of %s failed: %v", checkout, err)
	}

	var said []string
	for _, rule := range allRules(t, checkout, settings) {
		for _, match := range safely(rule, codebase) {
			if match.File() != file {
				continue
			}

			said = append(said, oracleSays(rule, match.Line()))
		}
	}

	sort.Strings(said)

	return said
}

func oracleSays(rule detectors.Detector, line int) string {
	return rule.Sin().Definition().Name + ":" + strconv.Itoa(line)
}

func TestTheOracleReportsADifferenceWhenTheOverlayIsWrong(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 5)

	cutInto(t, root, worktree, "feature/oracle-broken")

	under := filepath.Join("src", "made.py")
	file := filepath.Join(worktree, under)
	wroteInto(t, file, olderSin)

	settings, _ := config.Load(root)

	fresh := oracleOver(t, worktree, file, settings)

	wroteInto(t, file, "class Clean:\n    pass\n")

	stale := oracleOver(t, worktree, file, settings)

	if strings.Join(fresh, ",") == strings.Join(stale, ",") {
		t.Fatal("the oracle reads the same answer for a file whose text changed, so it cannot see a wrong answer at all")
	}
}

func cutInto(t *testing.T, root, worktree, branch string) {
	t.Helper()

	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", branch, worktree).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}
}

func wroteInto(t *testing.T, file, text string) {
	t.Helper()

	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(file, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}
