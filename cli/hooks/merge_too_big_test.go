package hooks

import (
	"path/filepath"
	"strconv"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestAMergeOfMoreFilesThanAnAnswerCarriesSaysSoAndJudgesNone(t *testing.T) {
	root, before, _ := mergedProject(t)

	brought := mostFilesAMergeBringsIn + 3
	for each := 0; each < brought; each++ {
		wroteInto(t, filepath.Join(root, "src", "big"+strconv.Itoa(each)+".py"), olderSin)
	}

	commitIn(t, root, "a merge of a long-lived branch")

	said := MergeLanded(mergeLine(root, "main", before, git.Head(root)))

	if said == "" {
		t.Fatal("a merge of more files than an answer carries was answered with nothing at all")
	}

	if !strings.Contains(said, strconv.Itoa(brought+1)) {
		t.Errorf("the answer does not count the files the merge brought in: %s", said)
	}

	if !strings.Contains(said, "none of them was judged here") {
		t.Errorf("the answer does not say that nothing of it was judged: %s", said)
	}

	if !strings.Contains(said, "--branch") {
		t.Errorf("the answer does not say how to judge it properly: %s", said)
	}

	if strings.Contains(said, "big1.py:") {
		t.Errorf("the answer names a sin, so the files were read inside the wait after all: %s", said)
	}

	if kept := MergedIn(workspace.At(root, "")).Upto("main"); kept != git.Head(root) {
		t.Errorf("the mark says %q, and a merge that was answered with a refusal still moves it, or every later merge carries this one's files again", kept)
	}

	found := MergesIn(workspace.At(root, "")).Found()
	if len(found) == 0 || !strings.Contains(found[len(found)-1], "none of them was judged here") {
		t.Error("what a person reads back does not carry the refusal")
	}
}

func TestAMergeJustInsideTheCeilingIsJudged(t *testing.T) {
	root, before, _ := mergedProject(t)

	for each := 0; each < 5; each++ {
		wroteInto(t, filepath.Join(root, "src", "few"+strconv.Itoa(each)+".py"), olderSin)
	}

	commitIn(t, root, "a merge of a few files")

	said := MergeLanded(mergeLine(root, "main", before, git.Head(root)))

	if strings.Contains(said, "none of them was judged here") {
		t.Errorf("a merge of six files was refused as too large: %s", said)
	}

	if !strings.Contains(said, "sin") {
		t.Errorf("a merge of six sinful files found nothing: %s", said)
	}
}
