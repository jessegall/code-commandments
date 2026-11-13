package session

import (
	"os"

	"github.com/jessegall/code-commandments/cli/folder"
	"github.com/jessegall/code-commandments/walk"
)

// Adoption is a stranded folder taken into a session's own: everything that fits is moved, nothing is overwritten, and the stranded folder is deleted only when nothing was left behind.
type Adoption struct {
	from  string
	moved []string
	kept  []string
}

func Take(from, into string) *Adoption {
	adoption := &Adoption{from: from}
	adoption.absorb(from, into)

	if adoption.IsComplete() {
		folder.Delete(from)
	}

	return adoption
}

func (a *Adoption) IsComplete() bool {
	return len(a.kept) == 0
}

func (a *Adoption) Moved() []string {
	return a.moved
}

func (a *Adoption) Kept() []string {
	return a.kept
}

func (a *Adoption) absorb(from, into string) {
	if !walk.IsFolder(from) {
		return
	}

	os.MkdirAll(into, 0o777)

	for _, entry := range folder.Entries(from) {
		a.adopt(entry, into+"/"+entry[len(from)+1:])
	}
}

func (a *Adoption) adopt(entry, target string) {
	link, _ := os.Lstat(entry)

	if link != nil && link.Mode()&os.ModeSymlink != 0 {
		a.kept = append(a.kept, a.named(entry))

		return
	}

	if !walk.Exists(target) {
		a.record(entry, move(entry, target))

		return
	}

	if walk.IsFolder(entry) && walk.IsFolder(target) {
		a.absorb(entry, target)

		return
	}

	a.kept = append(a.kept, a.named(entry))
}

func (a *Adoption) record(entry string, succeeded bool) {
	if succeeded {
		a.moved = append(a.moved, a.named(entry))

		return
	}

	a.kept = append(a.kept, a.named(entry))
}

func (a *Adoption) named(path string) string {
	return path[len(a.from)+1:]
}

func move(from, to string) bool {
	if os.Rename(from, to) == nil {
		return true
	}

	if walk.IsFolder(from) {
		return folder.Copy(from, to) && folder.Delete(from)
	}

	return folder.CopyFile(from, to) && os.Remove(from) == nil
}
