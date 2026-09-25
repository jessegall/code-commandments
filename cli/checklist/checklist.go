// Package checklist is the worklist judge writes into a session's folder: the live `sins.md`, and the
// runs before it kept beside it under their time stamps, newest five at most.
package checklist

import (
	"crypto/md5"
	"encoding/hex"
	"os"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/folder"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const (
	notes        = "README.md"
	keptArchives = 5
	stamp        = "2006-01-02_150405"
)

// notesText explains the folder to whoever opens it.
const notesText = "# Judge checklists\n\n" +
	"`sins.md` is the live worklist from the last `commandments judge` run: one line per sin,\n" +
	"grouped under the skill that fixes it. Work it top to bottom, deleting each line as you\n" +
	"fix its sin, and re-run judge only when the file is empty.\n\n" +
	"`sins-<date>_<time>.md` are the runs before it. **They are kept deliberately** — each one\n" +
	"is the record of what was true when it ran, and `commandments judge --repent=<date>_<time>`\n" +
	"scopes a re-run or a `repent` to exactly what that run reported. Nothing here leaked: the\n" +
	"newest five are kept and older ones are rotated out on their own.\n\n" +
	"The whole folder is generated and gitignored. Deleting it is safe — the next judge run\n" +
	"writes it again."

// Checklist is one checklist file.
type Checklist struct {
	path string
}

// At is the checklist at path.
func At(path string) Checklist {
	return Checklist{path}
}

// InSession is the session's live checklist.
func InSession(space workspace.Workspace) Checklist {
	return Checklist{space.Checklist()}
}

// Prepare makes the folder a checklist is written to, explains the session's own folder the first time,
// and marks the session as touched; false when the folder cannot be made.
func Prepare(target string, space workspace.Workspace) bool {
	dir := filepath.Dir(target)

	if os.MkdirAll(dir, 0o755) != nil {
		return false
	}

	if target == space.Checklist() {
		explain(dir)
	}

	now := time.Now()
	os.Chtimes(space.SessionDir(), now, now)

	return true
}

func explain(dir string) {
	if _, err := os.Stat(dir + "/" + notes); err == nil {
		return
	}

	atomic.Write(dir+"/"+notes, notesText)
}

// RemainingSins is how many sin lines the checklist still holds.
func (c Checklist) RemainingSins() int {
	raw, err := os.ReadFile(c.path)
	if err != nil {
		return 0
	}

	count := 0

	for _, line := range strings.SplitAfter(string(raw), "\n") {
		if strings.HasPrefix(strings.TrimLeft(line, " \t\n\r\x00\x0B"), "- `") {
			count++
		}
	}

	return count
}

// Fingerprint is the checklist's content hash, and whether there is a checklist.
func (c Checklist) Fingerprint() (string, bool) {
	raw, err := os.ReadFile(c.path)
	if err != nil {
		return "", false
	}

	sum := md5.Sum(raw)

	return hex.EncodeToString(sum[:]), true
}

// Archive moves the live checklist aside under the time it was written, then keeps the newest five.
func (c Checklist) Archive() {
	info, err := os.Stat(c.path)
	if err != nil || !info.Mode().IsRegular() {
		return
	}

	written := info.ModTime().UTC().Format(stamp)
	archive := c.sibling(written)

	for n := 2; exists(archive); n++ {
		archive = c.sibling(written + "-" + strconv.Itoa(n))
	}

	os.Rename(c.path, archive)
	c.PruneArchives()
}

// PruneArchives deletes all but the newest five archives.
func (c Checklist) PruneArchives() {
	archives := c.archives()

	sort.SliceStable(archives, func(i, j int) bool {
		return folder.Modified(archives[i]) > folder.Modified(archives[j])
	})

	for _, old := range archives[min(keptArchives, len(archives)):] {
		os.Remove(old)
	}
}

// ClearAll deletes the checklist, its archives, and a session checklist folder with them.
func (c Checklist) ClearAll() {
	os.Remove(c.path)

	for _, archive := range c.archives() {
		os.Remove(archive)
	}

	if dir := filepath.Dir(c.path); filepath.Base(dir) == workspace.Sins {
		os.Remove(dir + "/" + notes)
		os.Remove(dir)
	}
}

// Delete removes the live checklist, as a clean run does.
func (c Checklist) Delete() {
	if exists(c.path) {
		os.Remove(c.path)
	}
}

func (c Checklist) archives() []string {
	archives, _ := filepath.Glob(c.stem() + "-*" + c.suffix())

	return archives
}

func (c Checklist) sibling(stamp string) string {
	return c.stem() + "-" + stamp + c.suffix()
}

func (c Checklist) stem() string {
	return strings.TrimSuffix(c.path, c.suffix())
}

func (c Checklist) suffix() string {
	return filepath.Ext(c.path)
}

func exists(path string) bool {
	info, err := os.Stat(path)

	return err == nil && info.Mode().IsRegular()
}
