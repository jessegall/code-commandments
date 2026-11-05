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
	"strconv"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

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
		if fingerprint(path) != mark {
			moved = append(moved, path)
		}
	}

	return moved
}

// Holds says whether the file is one of the base's, and whether its content is still what the base read.
func (b *Base) Holds(path string) (unchanged bool, known bool) {
	mark, known := b.marks[path]
	if !known {
		return false, false
	}

	return fingerprint(path) == mark, true
}

// View is the base with these files' trees in place of its own, which is what one agent's question is answered
// against: every file it does not name is the base's own tree, shared rather than copied.
//
// The streams are the base's with one slice of file pointers rebuilt, so the view costs the files it replaces
// and nothing else. The caller parses the named files itself, through the bridge that keeps them warm.
func (b *Base) View(rebuilt []*contract.Stream, project string) *engine.Codebase {
	fresh := map[string]*contract.File{}
	for _, stream := range rebuilt {
		for _, file := range stream.Files {
			fresh[file.Path] = file
		}
	}

	var spliced []*contract.Stream
	for _, stream := range b.streams {
		held := *stream
		held.Files = make([]*contract.File, len(stream.Files))
		copy(held.Files, stream.Files)

		for i, file := range held.Files {
			if replacement, changed := fresh[file.Path]; changed {
				held.Files[i] = replacement
			}
		}

		spliced = append(spliced, &held)
	}

	view := engine.Load(spliced...)
	view.Scanned(b.roots...)
	view.Rooted(project)

	return view
}

// fingerprints are what every file of the codebase was when it was read.
func fingerprints(codebase *engine.Codebase) map[string]string {
	marks := map[string]string{}
	for _, file := range codebase.Files() {
		marks[file.File.Path] = fingerprint(file.File.Path)
	}

	return marks
}

// fingerprint is what the file is now: its size and the moment it was last written, which is what the
// filesystem answers without reading it. A file whose content is restored to what it was reads as moved, which
// costs one re-parse and never a wrong answer; reading every file to hash it costs more than that mistake.
func fingerprint(path string) string {
	info, err := os.Stat(path)
	if err != nil {
		return ""
	}

	return info.ModTime().UTC().Format(time.RFC3339Nano) + "\x00" + strconv.FormatInt(info.Size(), 10)
}
