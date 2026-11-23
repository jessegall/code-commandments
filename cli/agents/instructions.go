package agents

import (
	"errors"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/block"
	"github.com/jessegall/code-commandments/walk"
)

const bom = "\xEF\xBB\xBF"

// Instructions is a file of instructions the user owns, in which the tool keeps one line of its own, the one that brings the briefing file in: added, never written over.
type Instructions struct {
	path, root string
}

func InstructionsAt(path, root string) Instructions {
	return Instructions{path, root}
}

// Refused is an injection left undone, and why; the file is as it was.
type Refused struct {
	Reason string
}

func (e *Refused) Error() string {
	return e.Reason
}

func (i Instructions) Hold(line, formerBlock string) error {
	if !i.insideTheProject() {
		return &Refused{i.path + " resolves outside this project — left alone."}
	}

	original, err := os.ReadFile(i.path)
	if errors.Is(err, os.ErrNotExist) {
		return i.save(nil, "# "+strings.TrimSuffix(filepath.Base(i.path), ".md")+"\n\n"+line+"\n")
	}

	return i.rewritten(original, func(normalised string) (string, error) {
		without, err := withoutBlock(normalised, formerBlock)
		if err != nil || slices.Contains(strings.Split(without, "\n"), line) {
			return without, err
		}

		return strings.TrimRight(without, "\n") + "\n\n" + line + "\n", nil
	})
}

func (i Instructions) Withdraw(line, formerBlock string) error {
	if !i.insideTheProject() {
		return &Refused{i.path + " resolves outside this project — left alone."}
	}

	original, err := os.ReadFile(i.path)
	if errors.Is(err, os.ErrNotExist) {
		return nil
	}

	return i.rewritten(original, func(normalised string) (string, error) {
		without, err := withoutBlock(normalised, formerBlock)
		lines := slices.DeleteFunc(strings.Split(without, "\n"), func(each string) bool { return each == line })

		return strings.Join(lines, "\n"), err
	})
}

func withoutBlock(document, formerBlock string) (string, error) {
	if formerBlock == "" {
		return document, nil
	}
	without, _, err := block.Remove(document, formerBlock)

	return without, err
}

func (i Instructions) rewritten(original []byte, change func(string) (string, error)) error {
	document := strings.TrimPrefix(string(original), bom)
	eol := "\n"
	if strings.Contains(document, "\r\n") {
		eol = "\r\n"
	}

	updated, err := change(strings.ReplaceAll(document, "\r\n", "\n"))
	if err != nil {
		return &Refused{i.path + ": " + err.Error()}
	}

	if eol != "\n" {
		updated = strings.ReplaceAll(updated, "\n", eol)
	}

	if strings.HasPrefix(string(original), bom) {
		updated = bom + updated
	}

	return i.save(original, updated)
}

func (i Instructions) SameFileAs(other Instructions) bool {
	mine, err := os.Stat(i.path)
	theirs, otherErr := os.Stat(other.path)

	if err != nil || otherErr != nil {
		return false
	}

	return os.SameFile(mine, theirs)
}

func (i Instructions) insideTheProject() bool {
	root, err := walk.Resolved(i.root)
	if err != nil {
		return false
	}

	resolved, err := walk.Resolved(i.path)
	if err != nil {
		resolved, err = walk.Resolved(filepath.Dir(i.path))
	}

	return err == nil && walk.Within(root, resolved)
}

func (i Instructions) save(original []byte, updated string) error {
	if original != nil && string(original) == updated {
		return nil
	}

	if err := atomic.Write(i.path, updated); err != nil {
		return &Refused{i.path + " could not be written — left as it was."}
	}

	return nil
}
