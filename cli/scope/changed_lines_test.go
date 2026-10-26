package scope

import (
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

// olderAndNewer is a file whose first function was committed and whose second was added afterwards, so a diff
// against the commit names only the lines of the second.
const olderAndNewer = `<?php

class Refunds
{
    public function older(): int
    {
        return 1;
    }

    public function newer(): int
    {
        return 2;
    }
}
`

// edited is a repository holding that file with only its first function committed, and the second added on top.
// It answers the question the change is about: which lines did this working tree touch?
func edited(t *testing.T) (root, file string) {
	t.Helper()
	root, _ = filepath.EvalSymlinks(t.TempDir())
	committed := `<?php

class Refunds
{
    public function older(): int
    {
        return 1;
    }
}
`
	write(t, root, "src/Refunds.php", committed)
	run(t, root, "init", "-q", "-b", "main")
	run(t, root, "add", ".")
	run(t, root, "commit", "-q", "-m", "the function that was already here")
	write(t, root, "src/Refunds.php", olderAndNewer)

	return root, filepath.Join(root, "src", "Refunds.php")
}

// TestChangesCoversTheLinesItChangedAndNotTheRest is the whole of what --changes now means: a finding on a line
// the working tree touched is reported, and one on a line it did not is not. A sin nobody here wrote is nobody
// here's to answer for, and the moment to raise it is when somebody opens that line.
func TestChangesCoversTheLinesItChangedAndNotTheRest(t *testing.T) {
	root, file := edited(t)

	scoped, err := FromArgs([]string{"--changes"}, root, workspace.At(root, "s"), nil)
	if err != nil {
		t.Fatal(err)
	}

	if !scoped.Includes(file) {
		t.Fatal("the changed file is not even a target")
	}
	if scoped.Covers(file, 7) {
		t.Error("line 7 is covered, and it is the body of the function that was already committed")
	}
	if !scoped.Covers(file, 12) {
		t.Error("line 12 is not covered, and it is the body of the function this change added")
	}
}

// TestChangedFilesCoversTheWholeOfEachFileItNames holds the flag that keeps what --changes used to do, so that
// nothing is lost: a run that wants a changed file's older sins asks for the file.
func TestChangedFilesCoversTheWholeOfEachFileItNames(t *testing.T) {
	root, file := edited(t)

	scoped, err := FromArgs([]string{"--changed-files"}, root, workspace.At(root, "s"), nil)
	if err != nil {
		t.Fatal(err)
	}

	for _, line := range []int{7, 12} {
		if !scoped.Covers(file, line) {
			t.Errorf("line %d is not covered, and --changed-files is about whole files", line)
		}
	}
}

// TestEveryOtherScopeStillCoversWholeFiles is the promise that nothing else moved. A scope about files answers
// about every line of them, so a path, a branch and an unscoped run read exactly as they did.
func TestEveryOtherScopeStillCoversWholeFiles(t *testing.T) {
	root, file := edited(t)
	space := workspace.At(root, "s")

	for _, args := range [][]string{{}, {"--branch=main"}} {
		scoped, err := FromArgs(args, root, space, nil)
		if err != nil {
			t.Fatalf("%v: %v", args, err)
		}
		if !scoped.Includes(file) {
			continue
		}
		for _, line := range []int{7, 12} {
			if !scoped.Covers(file, line) {
				t.Errorf("%v does not cover line %d, where it is about whole files", args, line)
			}
		}
	}
}

// TestAFileWithNoHistoryIsCoveredWhole holds the case a diff cannot answer: a file git has never seen is all
// new, so every line of it is the change.
func TestAFileWithNoHistoryIsCoveredWhole(t *testing.T) {
	root, _ := edited(t)
	write(t, root, "src/Fresh.php", olderAndNewer)
	fresh := filepath.Join(root, "src", "Fresh.php")

	scoped, err := FromArgs([]string{"--changes"}, root, workspace.At(root, "s"), nil)
	if err != nil {
		t.Fatal(err)
	}
	if !scoped.Includes(fresh) {
		t.Fatal("a file git has never seen is not a target of the working tree's changes")
	}
	for _, line := range []int{7, 12} {
		if !scoped.Covers(fresh, line) {
			t.Errorf("line %d of a brand new file is not covered, though every line of it is new", line)
		}
	}
}

// TestAModifiedLineCountsAsChanged holds the case Jesse named first: a line rewritten in place, not added. A
// diff names it on its added side, so a sin standing on it is this change's to answer for.
func TestAModifiedLineCountsAsChanged(t *testing.T) {
	root, file := edited(t)
	// The committed function's own body is rewritten, and nothing is added.
	write(t, root, "src/Refunds.php", `<?php

class Refunds
{
    public function older(): int
    {
        return 99;
    }
}
`)

	scoped, err := FromArgs([]string{"--changes"}, root, workspace.At(root, "s"), nil)
	if err != nil {
		t.Fatal(err)
	}
	if !scoped.Covers(file, 7) {
		t.Error("line 7 is not covered, though this change rewrote it in place")
	}
	if scoped.Covers(file, 4) {
		t.Error("line 4 is covered, and nothing touched the class header")
	}
}

// TestADeletionBesideAnOldSinDoesNotClaimIt holds the case that would be easiest to get wrong. A deleted line is
// on the diff's REMOVED side and exists in no file afterwards, so it can carry no finding; what matters is that
// taking a line away does not make the lines around it this change's.
func TestADeletionBesideAnOldSinDoesNotClaimIt(t *testing.T) {
	root, file := edited(t)
	write(t, root, "src/Refunds.php", `<?php

class Refunds
{
    public function older(): int
    {
        return 1;
    }

    public function newer(): int
    {
        return 2;
    }

    public function third(): int
    {
        return 3;
    }
}
`)
	run(t, root, "add", ".")
	run(t, root, "commit", "-q", "-m", "three functions")
	// The middle function goes, leaving the first untouched and the third where the second was.
	write(t, root, "src/Refunds.php", `<?php

class Refunds
{
    public function older(): int
    {
        return 1;
    }

    public function third(): int
    {
        return 3;
    }
}
`)

	scoped, err := FromArgs([]string{"--changes"}, root, workspace.At(root, "s"), nil)
	if err != nil {
		t.Fatal(err)
	}
	if scoped.Covers(file, 7) {
		t.Error("line 7 is covered, and the deletion happened below it")
	}
}
