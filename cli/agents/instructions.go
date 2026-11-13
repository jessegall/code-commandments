package agents

import (
	"errors"
	"os"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/block"
	"github.com/jessegall/code-commandments/walk"
)

const bom = "\xEF\xBB\xBF"

// Instructions is a file of instructions the user owns, into which the tool keeps one block of its own: injected, never overwritten.
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

func (i Instructions) Inject(name, body string) error {
	if !i.insideTheProject() {
		return &Refused{i.path + " resolves outside this project — left alone."}
	}

	wrapped := block.Begin(name, "composer update") + "\n" + strings.TrimSpace(body) + "\n" + block.End(name)

	original, err := os.ReadFile(i.path)
	if errors.Is(err, os.ErrNotExist) {
		return i.save(nil, "# "+strings.TrimSuffix(filepath.Base(i.path), ".md")+"\n\n"+wrapped+"\n")
	}

	return i.rewritten(original, func(normalised string) (string, error) {
		updated, found, err := block.Replace(normalised, name, "\n"+strings.TrimSpace(body)+"\n")
		if err == nil && !found {
			updated = strings.TrimRight(normalised, "\n") + "\n\n" + wrapped + "\n"
		}

		return updated, err
	})
}

func (i Instructions) Withdraw(name string) error {
	if !i.insideTheProject() {
		return &Refused{i.path + " resolves outside this project — left alone."}
	}

	original, err := os.ReadFile(i.path)
	if errors.Is(err, os.ErrNotExist) {
		return nil
	}

	return i.rewritten(original, func(normalised string) (string, error) {
		updated, _, err := block.Remove(normalised, name)

		return updated, err
	})
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
