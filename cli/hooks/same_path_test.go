package hooks

import (
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/detectors"
)

const sinOnLineTwo = `def refund(order) -> int:
    if order.late:
        raise ValueError(order)
    else:
        return order.total
`

const sinOnLineFive = `def total(order) -> int:
    return order.total


def refund(order) -> int:
    if order.late:
        raise ValueError(order)
    else:
        return order.total
`

func TestTwoWorktreesHoldingDifferentTextForOnePathAreEachAnsweredFromTheirOwn(t *testing.T) {
	root, _ := workerProject(t)

	first := stressWorktreeIn(root, 9)
	second := stressWorktreeIn(root, 10)

	cutInto(t, root, first, "feature/same-path-first")
	cutInto(t, root, second, "feature/same-path-second")

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(first, under), sinOnLineTwo)
	wroteInto(t, filepath.Join(second, under), sinOnLineFive)

	settings, _ := config.Load(root)

	warmFor(t, root, settings)

	kept.CutAt(first, "aaaaaaa")
	kept.CutAt(second, "bbbbbbb")

	for _, order := range [][]string{{first, second}, {second, first}} {
		for _, checkout := range order {
			said := linesToldFor(t, checkout, filepath.Join(checkout, under), settings)
			want := map[string]string{first: "2", second: "6"}[checkout]

			if !strings.Contains(said, want) {
				t.Errorf("asked in the order %v, the worktree %s is answered %q, and its own sin is on line %s",
					order, filepath.Base(checkout), said, want)
			}
		}
	}
}

func linesToldFor(t *testing.T, root, file string, settings config.Config) string {
	t.Helper()

	enabled := allRules(t, root, settings)

	together, _, why := AgainstTheProgramNarrowed(Marks(root), []string{file},
		func(string) []detectors.Detector { return enabled },
		func(string) git.ChangedLines { return git.Everywhere() }, root)

	if why != "" {
		return "refused: " + string(why)
	}

	var said []string
	for _, mark := range together[file] {
		said = append(said, mark.ShownFrom(root))
	}

	return strings.Join(said, "; ")
}
