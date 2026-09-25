package agents

import (
	"errors"
	"os"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/block"
)

// bom is the byte-order mark a file may open with; it is kept where it was found.
const bom = "\xEF\xBB\xBF"

// Instructions is a file of instructions the user owns, into which the tool keeps one block of its own:
// injected, never overwritten.
type Instructions struct {
	path, root string
}

// InstructionsAt is the instructions file at path, in the project at root.
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

// Inject puts the body in the named block: in place of the block when the file has one, appended when it
// has none, and as a new file when there is none. Markers that cannot be trusted, a path outside the
// project, or a write that fails refuse it.
func (i Instructions) Inject(name, body string) error {
	if !i.insideTheProject() {
		return &Refused{i.path + " resolves outside this project — left alone."}
	}

	wrapped := block.Begin(name, "composer update") + "\n" + strings.TrimSpace(body) + "\n" + block.End(name)

	original, err := os.ReadFile(i.path)
	if errors.Is(err, os.ErrNotExist) {
		return i.save(nil, "# "+strings.TrimSuffix(filepath.Base(i.path), ".md")+"\n\n"+wrapped+"\n")
	}

	document := strings.TrimPrefix(string(original), bom)
	eol := "\n"
	if strings.Contains(document, "\r\n") {
		eol = "\r\n"
	}

	normalised := strings.ReplaceAll(document, "\r\n", "\n")

	updated, found, err := block.Replace(normalised, name, "\n"+strings.TrimSpace(body)+"\n")
	if err != nil {
		return &Refused{i.path + ": " + err.Error()}
	}

	if !found {
		updated = strings.TrimRight(normalised, "\n") + "\n\n" + wrapped + "\n"
	}

	if eol != "\n" {
		updated = strings.ReplaceAll(updated, "\n", eol)
	}

	if strings.HasPrefix(string(original), bom) {
		updated = bom + updated
	}

	return i.save(original, updated)
}

// SameFileAs says whether both name one file on disk, decided by device and inode.
func (i Instructions) SameFileAs(other Instructions) bool {
	mine, err := os.Stat(i.path)
	theirs, otherErr := os.Stat(other.path)

	if err != nil || otherErr != nil {
		return false
	}

	return os.SameFile(mine, theirs)
}

func (i Instructions) insideTheProject() bool {
	root, err := filepath.EvalSymlinks(i.root)
	if err != nil {
		return false
	}

	resolved, err := filepath.EvalSymlinks(i.path)
	if err != nil {
		resolved, err = filepath.EvalSymlinks(filepath.Dir(i.path))
	}

	return err == nil && (resolved == root || strings.HasPrefix(resolved, root+string(filepath.Separator)))
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
