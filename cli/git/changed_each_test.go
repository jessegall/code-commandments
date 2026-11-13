package git_test

import (
	"os"
	"path/filepath"
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

// One diff of every file answers what a diff of each file on its own answers: the lines a change touched, nothing for an
// untouched file, and everywhere for a file git does not track, whatever its name holds.
func TestOneDiffOfEveryFileAnswersAsADiffOfEach(t *testing.T) {
	root := repository(t)
	if err := os.MkdirAll(filepath.Join(root, "shop"), 0o755); err != nil {
		t.Fatal(err)
	}
	cart, prices, spaced := filepath.Join(root, "shop", "cart.go"), filepath.Join(root, "shop", "prices.go"), filepath.Join(root, "shop", "the till.go")
	write(t, cart, "package shop\n\nfunc total() int {\n\treturn 1\n}\n")
	write(t, prices, "package shop\n\nconst price = 3\n")
	write(t, spaced, "package shop\n\nconst till = 1\n")
	run(t, root, "add", "-A")
	run(t, root, "commit", "-m", "the shop")

	write(t, cart, "package shop\n\nfunc total() int {\n\treturn 2\n}\n\nfunc tax() int {\n\treturn 0\n}\n")
	write(t, spaced, "package shop\n\nconst till = 2\n")
	added := filepath.Join(root, "shop", "receipt.go")
	write(t, added, "package shop\n")

	files := []string{cart, prices, spaced, added}
	each := git.ChangedLinesSinceEach(root, "HEAD", files)
	for _, file := range files {
		if got, want := each(file), git.ChangedLinesSince(root, "HEAD", file); !reflect.DeepEqual(got, want) {
			t.Errorf("one diff of every file answered %+v for %s, a diff of it alone %+v", got, filepath.Base(file), want)
		}
	}
	if !each(added).Covers(1) || each(prices).Covers(3) || !each(cart).Covers(4) {
		t.Errorf("the answers do not say what changed: the new file %+v, the untouched one %+v, the edited one %+v", each(added), each(prices), each(cart))
	}
}
