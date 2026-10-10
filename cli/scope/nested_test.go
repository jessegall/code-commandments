package scope

import (
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

// workspaceOfRepositories is a repository on main holding a repository of its own, trackmijn, whose feature branch
// changes one file and adds another while main stays checked out there.
func workspaceOfRepositories(t *testing.T) string {
	t.Helper()
	root, _ := filepath.EvalSymlinks(t.TempDir())
	write(t, root, "tools/Root.php", "<?php class Root {}")
	run(t, root, "init", "-q", "-b", "main")
	run(t, root, "add", ".")
	run(t, root, "commit", "-q", "-m", "start")

	nested := filepath.Join(root, "trackmijn")
	write(t, nested, "app/Kept.php", "<?php class Kept {}")
	write(t, nested, "app/Branched.php", "<?php class Branched {}")
	run(t, nested, "init", "-q", "-b", "main")
	run(t, nested, "add", ".")
	run(t, nested, "commit", "-q", "-m", "start")
	run(t, nested, "checkout", "-q", "-b", "feature")
	write(t, nested, "app/Branched.php", "<?php class Branched { }")
	write(t, nested, "app/Added.php", "<?php class Added {}")
	run(t, nested, "add", ".")
	run(t, nested, "commit", "-q", "-m", "branch")
	run(t, nested, "checkout", "-q", "main")

	return root
}

// TestANamedRepositorysBranchOrPullRequestChoosesItsFiles holds a workspace of repositories to its own: a run from its
// root names the repository and the branch or the pull request whose files it judges, read as the checkout has them
// (feature's new file is not on disk while main is checked out, so it is not chosen), and a bare --branch, which only
// asks the repository the root lies in, names the repositories it never looked into.
func TestANamedRepositorysBranchOrPullRequestChoosesItsFiles(t *testing.T) {
	root := workspaceOfRepositories(t)
	space := workspace.At(root, "s")
	asked := pullRequest
	pullRequest = func(repository, number string) ([]string, string, error) {
		return []string{"app/Branched.php", "README.md"}, "feature", nil
	}
	t.Cleanup(func() { pullRequest = asked })

	for flags, want := range map[string][]string{
		"--branch=trackmijn:feature":       {"trackmijn/app/Branched.php"},
		"--branch=trackmijn:main..feature": {"trackmijn/app/Branched.php"},
		"--pr=trackmijn#161":               {"trackmijn/app/Branched.php"},
	} {
		chosen, err := FromArgs([]string{flags}, root, space, nil)
		if err != nil {
			t.Fatalf("%s: %v", flags, err)
		}
		var got []string
		for file := range chosen.Files() {
			got = append(got, strings.TrimPrefix(file, root+"/"))
		}
		slices.Sort(got)
		if !slices.Equal(got, want) {
			t.Errorf("%s chose %v, want %v", flags, got, want)
		}
		if !strings.Contains(chosen.ChosenBy(), "trackmijn") || !strings.Contains(chosen.ChosenBy(), "read as main") {
			t.Errorf("%s says it chose %q", flags, chosen.ChosenBy())
		}
		if len(chosen.Unseen()) != 0 {
			t.Errorf("%s left %v unseen, though it named its repository", flags, chosen.Unseen())
		}
	}

	bare, err := FromArgs([]string{"--branch"}, root, space, nil)
	if err != nil {
		t.Fatal(err)
	}
	if !bare.IsEmpty() || !slices.Equal(bare.Unseen(), []string{"trackmijn"}) {
		t.Errorf("a bare --branch at the root chose %v and left %v unseen", bare.Files(), bare.Unseen())
	}

	for _, flags := range []string{"--branch=nowhere:feature", "--branch=trackmijn:gone", "--pr=trackmijn"} {
		if _, err := FromArgs([]string{flags}, root, space, nil); !IsUnavailable(err) {
			t.Errorf("%s: err %v, want it said why it cannot be worked out", flags, err)
		}
	}
}
