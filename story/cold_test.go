package story

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestAScratchProjectHoldsItsFilesAndNoJournal(t *testing.T) {
	s, _ := storyOver(t, quiet)

	project := s.ScratchProject("one file", PlainFile{Path: "src/order.py", Text: "x = 1\n"})

	if held, err := os.ReadFile(filepath.Join(project.Folder(), "src/order.py")); err != nil || string(held) != "x = 1\n" {
		t.Errorf("the project holds %q (%v), where it was given its file", held, err)
	}

	if _, err := os.Stat(filepath.Join(project.Folder(), ".journal")); !os.IsNotExist(err) {
		t.Errorf("the project has a .journal folder (%v), so its judge could not read it cold", err)
	}

	if printed, err := exec.Command("git", "-C", project.Folder(), "log", "--oneline").CombinedOutput(); err != nil || strings.Count(string(printed), "\n") != 1 {
		t.Errorf("the project's history is %q (%v), where one commit was meant", printed, err)
	}
}

func TestAWorktreeOfAScratchProjectHoldsItsFilesAndIsGoneAtTheEnd(t *testing.T) {
	outer, _ := storyOver(t, quiet)
	project := outer.ScratchProject("one file", PlainFile{Path: "order.py", Text: "x = 1\n"})

	var cut string

	t.Run("a story that cuts one", func(inner *testing.T) {
		s, _ := storyOver(inner, quiet)
		worktree := (&PlainProject{story: s, folder: project.Folder()}).Worktree()
		cut = worktree.Folder()

		if _, err := os.Stat(filepath.Join(cut, "order.py")); err != nil {
			inner.Errorf("the worktree does not hold the project's file: %v", err)
		}
	})

	if _, err := os.Stat(cut); !os.IsNotExist(err) {
		t.Errorf("the worktree %s outlived its story (%v)", cut, err)
	}

	printed, err := exec.Command("git", "-C", project.Folder(), "worktree", "list").CombinedOutput()
	if err != nil || strings.Contains(string(printed), cut) {
		t.Errorf("git still lists the worktree of a finished story (%v):\n%s", err, printed)
	}
}

func TestAPlainProjectsStepsAreToldLikeAnyOther(t *testing.T) {
	s := &Story{t: t, journal: Journal{rehearsing: true}, rehearsal: true}

	project := s.ScratchProject("a sinful file")
	project.Worktree().Judges("src", "--no-checklist")

	told := strings.Join(textsOf(s.steps), "\n")

	for _, step := range []string{"makes a plain project of a sinful file", "cuts a worktree of the plain project",
		"judges the plain project with the real binary src --no-checklist"} {
		if !strings.Contains(told, step) {
			t.Errorf("the story did not tell %q; it told:\n%s", step, told)
		}
	}

	if shape := s.steps[len(s.steps)-1].Shape; strings.Contains(shape, "src") || !strings.Contains(shape, "--no-checklist") {
		t.Errorf("the judging step has the shape %q, where its path is a word and its flag part of the shape", shape)
	}
}
