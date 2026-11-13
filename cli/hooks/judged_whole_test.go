package hooks

import (
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
)

// A whole judging says what it read and how long reading and its rules took, so a hand-in's record shows where its
// time went.
func TestAWholeJudgingSaysWhatItReadAndHowLongItTook(t *testing.T) {
	root, file, _ := projectWithASin(t)
	project, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	_, times, read := judgedWhole(root, project, nil, nil, []string{file}, func(string) git.ChangedLines { return git.ChangedLines{} }, Marks(root))
	if !read {
		t.Fatal("the checkout could not be read")
	}
	if times.files != 1 || times.read <= 0 {
		t.Errorf("the judging says it read %d files in %v, where it read refund.py", times.files, times.read)
	}
}
