// Package warm keeps a project's parsed tree in memory, one base per branch, so a hook judges an edit against
// the whole program instead of reading the tree again. A base is loaded once and shared: a worktree has no base
// of its own, it answers from the base of the branch its main checkout is on, and the files it changed are
// spliced into a VIEW of that base for the one question being asked.
//
// Measured on two trees, in report 26: a base is 171 MB for 1518 files and 281 MB for 5286, fingerprinting every
// file of the larger one costs 0.314s, splicing one file in 0.002s, and a second view over the same base 13 MB,
// since every file the view does not replace is the base's own tree read by both. Reading either tree again
// costs 25s and 55s, which is what a base held in memory is for.
package warm

import (
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Sweep is how long the base trusts itself between re-statings of every file. Between sweeps the edited file
// alone is checked, which is the file the hook knows about; a file something else changed, a git checkout, a
// rebase or a formatter, is found at the next sweep. Re-stating every file of a 5286-file tree costs 0.3 to 1.1
// seconds depending on what else the machine is doing, which is a third of the answer, and an edit cannot pay
// that to learn what it already knows.
const Sweep = 30 * time.Second

// Idle is how long a base nobody has asked about is kept. Past it the memory is worth more than the next read:
// a branch an agent has stopped working on is one nobody will ask about again, and a base is hundreds of
// megabytes.
const Idle = 15 * time.Minute

// Base is one branch's tree, held as the streams it was built from so a view can be made over it without
// parsing anything again, with what each file was when it was read.
type Base struct {
	// Project is the main checkout the base belongs to, and Branch the branch it was read at. A worktree of
	// that project answers from this base rather than holding one of its own.
	Project string
	Branch  string

	streams   []*contract.Stream
	marks     map[string]string
	roots     []string
	codebase  *engine.Codebase
	read      time.Time
	lastAsked time.Time

	// mu guards swept, which every question about the base writes.
	mu sync.Mutex
	// swept is when every file was last re-stated.
	swept time.Time
}

// Keeper holds the bases, one per project and branch, and hands out the one a question is about.
type Keeper struct {
	mu    sync.Mutex
	bases map[string]*Base
	// now is the clock, so a test can age a base without waiting for one.
	now func() time.Time
}

// Keep is a keeper with nothing in it yet.
func Keep() *Keeper {
	return &Keeper{bases: map[string]*Base{}, now: time.Now}
}

// Clocked is the keeper reading the time through this, so a test can age a base without waiting a quarter of an
// hour for it.
func (k *Keeper) Clocked(now func() time.Time) *Keeper {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.now = now

	return k
}

// Holds is the base kept for the project at that branch, and false where none is: the first hook of a branch
// finds none, and the caller judges the edited file alone while a base is read in the background.
func (k *Keeper) Holds(project, branch string) (*Base, bool) {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.sweep()

	base, held := k.bases[key(project, branch)]
	if !held {
		return nil, false
	}

	base.lastAsked = k.now()

	return base, true
}

// Hold makes the codebase the base for that project and branch, with the fingerprint of every file it was built
// from, so the next question knows which files have moved since.
func (k *Keeper) Hold(project, branch string, roots []string, codebase *engine.Codebase) *Base {
	base := &Base{
		Project:   project,
		Branch:    branch,
		streams:   codebase.Streams(),
		marks:     fingerprints(codebase),
		roots:     roots,
		codebase:  codebase,
		read:      k.now(),
		lastAsked: k.now(),
		swept:     k.now(),
	}

	k.mu.Lock()
	defer k.mu.Unlock()

	k.bases[key(project, branch)] = base

	return base
}

// Drop forgets the base of that project and branch, for a branch that is gone or a tree that must be read anew.
func (k *Keeper) Drop(project, branch string) {
	k.mu.Lock()
	defer k.mu.Unlock()

	delete(k.bases, key(project, branch))
}

// Held is how many bases the keeper holds, which is what a reader of its memory asks.
func (k *Keeper) Held() int {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.sweep()

	return len(k.bases)
}

// sweep drops every base nobody has asked about for Idle. The caller holds the lock.
func (k *Keeper) sweep() {
	for named, base := range k.bases {
		if k.now().Sub(base.lastAsked) >= Idle {
			delete(k.bases, named)
		}
	}
}

// key names a base by its project and its branch, which is what a worktree's question is pointed at.
func key(project, branch string) string {
	return project + "\x00" + branch
}

// Codebase is the base's own tree, as a question about an unchanged file is answered.
func (b *Base) Codebase() *engine.Codebase {
	return b.codebase
}

// Roots are the folders the base was walked from, which a view is scanned and rooted the same way.
func (b *Base) Roots() []string {
	return b.roots
}

// Moved are the files of the base whose content is no longer what it was when the base was read, and the ones
// that are gone. It is what the base is checked against before it is trusted: re-stating every file of a
// 5286-file tree costs 0.022s and fingerprinting them all 0.314s, so the check is never the cost.
func (b *Base) Moved() []string {
	var moved []string
	for path, mark := range b.marks {
		if Mark(path) != mark {
			moved = append(moved, path)
		}
	}

	return moved
}

// Stale are the files that must be read again before the base answers about this edit: the edited file where it
// has moved or is new to the base, and every file that has moved under the base when a sweep is due.
func (b *Base) Stale(edited string, now time.Time) []string {
	var stale []string
	if unchanged, known := b.Holds(edited); !known || !unchanged {
		stale = append(stale, edited)
	}

	b.mu.Lock()
	due := now.Sub(b.swept) >= Sweep
	if due {
		b.swept = now
	}
	b.mu.Unlock()

	if !due {
		return stale
	}

	// The named file is checked either way, and the sweep knows nothing of a file the base never read, so the
	// two answers are joined rather than one replacing the other.
	for _, moved := range b.Moved() {
		if moved != edited {
			stale = append(stale, moved)
		}
	}

	return stale
}

// Holds says whether the file is one of the base's, and whether its content is still what the base read.
func (b *Base) Holds(path string) (unchanged bool, known bool) {
	mark, known := b.marks[path]
	if !known {
		return false, false
	}

	return Mark(path) == mark, true
}

// View is the base with these files' trees in place of its own, which is what one agent's question is answered
// against: every file it does not name is the base's own tree, shared rather than copied.
//
// The streams are the base's with one slice of file pointers rebuilt, so the view costs the files it replaces
// and nothing else. The caller parses the named files itself, through the bridge that keeps them warm.
func (b *Base) View(rebuilt []*contract.Stream, project string) *engine.Codebase {
	return b.ViewFrom(project, rebuilt, project)
}

// ViewFrom is the base with the files of one CHECKOUT of the project in place of its own, which is how a
// worktree's question is answered: a worker's file at <worktree>/src/order.py stands in for the project's
// <project>/src/order.py, matched by the path each has under its own checkout.
//
// Standing IN FOR it is the whole point. Added beside it, the program would hold two copies of that source, the
// base's as it was and the worker's as it is, and a rule that reads a recurrence, a twin or a declaration would
// answer against two of everything the worker has changed. The view is rooted at the checkout so the paths a rule
// matches folders against are the ones the project means, src and not .journal/worktrees/worker-545/src.
func (b *Base) ViewFrom(checkout string, rebuilt []*contract.Stream, project string) *engine.Codebase {
	fresh := map[string]*contract.File{}
	for _, stream := range rebuilt {
		for _, file := range stream.Files {
			fresh[file.Path] = file
		}
	}

	// What each rebuilt file stands in for: the project's own file at the same path under the project.
	standsFor := map[string]bool{}
	if real(checkout) != real(project) {
		for path := range fresh {
			if under, inside := Under(checkout, path); inside {
				standsFor[filepath.Join(project, under)] = true
			}
		}
	}

	held := map[string]bool{}
	for _, stream := range b.streams {
		for _, file := range stream.Files {
			held[file.Path] = true
		}
	}

	// A file created since the base was read belongs to no stream of it, so it joins the stream of its own
	// language: without this a new file would be judged as though it did not exist.
	added := map[contract.Language][]*contract.File{}
	for _, stream := range rebuilt {
		for _, file := range stream.Files {
			if !held[file.Path] {
				added[stream.Header.Language] = append(added[stream.Header.Language], file)
			}
		}
	}

	var spliced []*contract.Stream
	for _, stream := range b.streams {
		part := *stream
		part.Files = nil

		for _, file := range stream.Files {
			switch {
			case fresh[file.Path] != nil:
				part.Files = append(part.Files, fresh[file.Path])
			case standsFor[file.Path]:
				// The checkout's own file stands in for this one, which is added below.
			default:
				part.Files = append(part.Files, file)
			}
		}

		part.Files = append(part.Files, added[stream.Header.Language]...)
		spliced = append(spliced, &part)
	}

	// A language the base holds no stream for at all comes in whole.
	for _, stream := range rebuilt {
		if !slices.ContainsFunc(b.streams, func(held *contract.Stream) bool { return held.Header.Language == stream.Header.Language }) {
			spliced = append(spliced, stream)
		}
	}

	// Only the files this view replaces have their facts worked out again: the rest are the base's own nodes,
	// which carry the facts they were read with. What reaches a changed file is filled with it, which the
	// language decides, so nothing is left answering against the file as it was.
	var changed []string
	for path := range fresh {
		changed = append(changed, path)
	}

	view := engine.Filling(changed, spliced...)
	view.Scanned(append(slices.Clone(b.roots), checkout)...)
	view.Rooted(checkout)

	return view
}

// Under is the path's place under the folder, and whether it lies there at all. It is what makes a checkout's
// file stand in for the project's own, and what tells a run reading this project's tree from one reading
// another's beside it.
func Under(folder, path string) (string, bool) {
	under, err := filepath.Rel(real(folder), real(path))
	if err != nil || strings.HasPrefix(under, "..") {
		return "", false
	}

	return under, true
}

// fingerprints are what every file of the codebase was when it was read.
func fingerprints(codebase *engine.Codebase) map[string]string {
	marks := map[string]string{}
	for _, file := range codebase.Files() {
		marks[file.File.Path] = Mark(file.File.Path)
	}

	return marks
}

// Mark is what the file is now: its size and the moment it was last written, which is what the filesystem
// answers without reading it. A file whose content is restored to what it was reads as moved, which costs one
// re-parse and never a wrong answer; reading every file to hash it costs more than that mistake.
//
// It is how a base knows which of its files have moved, and how anything else holding a parse of a file knows
// whether that parse is still the file.
func Mark(path string) string {
	info, err := os.Stat(path)
	if err != nil {
		return ""
	}

	return info.ModTime().UTC().Format(time.RFC3339Nano) + "\x00" + strconv.FormatInt(info.Size(), 10)
}
