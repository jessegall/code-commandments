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
	"crypto/sha256"
	"encoding/hex"
	"io"
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
	marks     map[string]Known
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
	// overlays are the checkouts' own files held over those bases, one per worktree.
	overlays map[string]*Overlay
	// now is the clock, so a test can age a base without waiting for one.
	now func() time.Time
}

// Keep is a keeper with nothing in it yet.
func Keep() *Keeper {
	return &Keeper{bases: map[string]*Base{}, overlays: map[string]*Overlay{}, now: time.Now}
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

// Any is a base held for that project, whatever branch it was read at, and the most recently asked for where
// several are. It is how a WORKTREE finds a tree: a worker stands on a branch of its own, cut from the branch
// the project was on at the time, and a branch per worker can never have a tree under its own name. Keyed by
// the branch the main checkout happens to be on at that moment, the lookup found nothing the moment that
// checkout moved, and every worker's judging read the whole tree in its own process.
//
// Whether the tree can answer about that worktree is a question about HISTORY, not about a name, and the caller
// asks it: a worktree whose branch shares none with the base's is refused there.
func (k *Keeper) Any(project string) (*Base, bool) {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.sweep()

	var newest *Base
	for _, base := range k.bases {
		if base.Project != project {
			continue
		}

		if newest == nil || base.lastAsked.After(newest.lastAsked) {
			newest = base
		}
	}

	if newest == nil {
		return nil, false
	}

	newest.lastAsked = k.now()

	return newest, true
}

// Hold makes the codebase the base for that project and branch, with the mark of every file it was built from,
// so the next question knows which files have moved since.
//
// Taken are the marks the caller took BEFORE the read, for the files it had then; a file the caller did not name
// is marked as it is now. A read of a tree takes 25 to 55 seconds, so a file written during it is read as it was
// and would otherwise be marked as it is, and nothing would ever notice it had moved: the edit that wrote it is
// judged against the version before it, at the lines that version had.
func (k *Keeper) Hold(project, branch string, roots []string, codebase *engine.Codebase, taken map[string]Known) *Base {
	base := &Base{
		Project:   project,
		Branch:    branch,
		streams:   codebase.Streams(),
		marks:     marksOf(codebase, taken),
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

// Patched holds the view as the base of that project and branch, with the files it read marked as they now are.
// What one edit read is then what every later edit is judged against.
//
// Without it a view was thrown away the moment its answer was given, so the base stayed the tree as it was first
// read: an agent that edited one file and then another had its own earlier work missing from the second answer,
// and a rule that reads a parent, a caller or a recurrence answered about the file as it had been. It cost
// nothing to keep — the view is already built and already filled — and the old base object stays whole for
// whoever is mid-question on it, since a new one is put in its place rather than this one changed.
func (k *Keeper) Patched(was *Base, view *engine.Codebase, read map[string]Known) *Base {
	marks := map[string]Known{}
	for path, mark := range was.marks {
		marks[path] = mark
	}

	for path, mark := range read {
		marks[path] = mark
	}

	base := &Base{
		Project:   was.Project,
		Branch:    was.Branch,
		streams:   view.Streams(),
		marks:     marks,
		roots:     was.roots,
		codebase:  view,
		read:      was.read,
		lastAsked: k.now(),
		swept:     was.sweptAt(),
	}

	k.mu.Lock()
	defer k.mu.Unlock()

	k.bases[key(was.Project, was.Branch)] = base

	return base
}

// sweptAt is when every file of the base was last re-stated, which the base that takes its place carries on from.
func (b *Base) sweptAt() time.Time {
	b.mu.Lock()
	defer b.mu.Unlock()

	return b.swept
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

// sweep drops every base and overlay nobody has asked about for Idle. The caller holds the lock.
func (k *Keeper) sweep() {
	for named, base := range k.bases {
		if k.now().Sub(base.lastAsked) >= Idle {
			delete(k.bases, named)
		}
	}

	for checkout, over := range k.overlays {
		if k.now().Sub(over.asked) >= Idle {
			delete(k.overlays, checkout)
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
	for path, known := range b.marks {
		if !known.Stands(path) {
			moved = append(moved, path)
		}
	}

	return moved
}

// Files is how many files the base holds, which is the size of the program it answers from.
func (b *Base) Files() int {
	return len(b.marks)
}

// Languages are the languages the base holds files of, which is what it can be ASKED about. A base reads the
// languages the project writes, less C#, whose bridge compiles whole units of its own and is kept warm as a
// service rather than held here — so a C# file is one a base never holds, however the project is configured.
//
// Asking a base about a file of any other language answers "never read" every time, for ever: on a consumer that
// was a fixed count of over a thousand files, which no fix to the path mapping could ever clear, and it declined
// every worker's judging on its own.
func (b *Base) Languages() []contract.Language {
	var languages []contract.Language
	for _, stream := range b.streams {
		if !slices.Contains(languages, stream.Header.Language) {
			languages = append(languages, stream.Header.Language)
		}
	}

	return languages
}

// Gone are the files the base read that are no longer on disk. A file something removed outside the plugin's
// sight — rm, a checkout, a rebase — is no longer part of the program, and a view that still holds it judges it
// as though it were: a consumer's judging reported three sins in a file deleted a minute earlier, which no event
// had announced because nothing announces a shell's removals.
func (b *Base) Gone() []string {
	var gone []string
	for path := range b.marks {
		if Mark(path) == "" {
			gone = append(gone, path)
		}
	}

	return gone
}

// Stale are the files that must be read again before the base answers about this edit: the edited file always,
// and every file that has moved under the base when a sweep is due. Swept says
// a sweep was due and happened, so a caller that knows of changes the base cannot see by itself asks about them
// then and not on every edit.
func (b *Base) Stale(edited string, now time.Time) (stale []string, swept bool) {
	// The edited file is ALWAYS read again, never held to its mark. A mark is a size and a moment written, which
	// is what the filesystem answers without reading the file, and two writes of one size inside one tick of
	// that clock are the same mark: on a filesystem whose clock ticks by the second, which is not a rare one, an
	// edit that keeps a file's length would be read as no change at all and judged against the version before
	// it. The one file an event names is the one file we know moved, so it is not worth asking about.
	stale = append(stale, edited)

	b.mu.Lock()
	due := now.Sub(b.swept) >= Sweep
	if due {
		b.swept = now
	}
	b.mu.Unlock()

	if !due {
		return stale, false
	}

	// The named file is checked either way, and the sweep knows nothing of a file the base never read, so the
	// two answers are joined rather than one replacing the other.
	for _, moved := range b.Moved() {
		if moved != edited {
			stale = append(stale, moved)
		}
	}

	return stale, true
}

// Unheld are the paths the base never read. It is how a file nobody announced is noticed: the sweep walks the
// base's own marks, so it knows nothing of a file a shell wrote, a pull brought or a checkout left behind, and
// every cross-file rule then answers as though that file did not exist.
func (b *Base) Unheld(paths []string) []string {
	var unheld []string
	for _, path := range paths {
		if _, known := b.marks[path]; !known {
			unheld = append(unheld, path)
		}
	}

	return unheld
}

// Holds says whether the file is one of the base's, and whether its content is still what the base read.
func (b *Base) Holds(path string) (unchanged bool, known bool) {
	was, held := b.marks[path]
	if !held {
		return false, false
	}

	return was.Stands(path), true
}

// View is the base with these files' trees in place of its own, which is what one agent's question is answered
// against: every file it does not name is the base's own tree, shared rather than copied.
//
// The streams are the base's with one slice of file pointers rebuilt, so the view costs the files it replaces
// and nothing else. The caller parses the named files itself, through the bridge that keeps them warm.
func (b *Base) View(rebuilt []*contract.Stream, project string) *engine.Codebase {
	return b.ViewFrom(project, rebuilt, project, nil)
}

// ViewFrom is the base with the files of one CHECKOUT of the project in place of its own, which is how a
// worktree's question is answered: a worker's file at <worktree>/src/order.py stands in for the project's
// <project>/src/order.py, matched by the path each has under its own checkout.
//
// Standing IN FOR it is the whole point. Added beside it, the program would hold two copies of that source, the
// base's as it was and the worker's as it is, and a rule that reads a recurrence, a twin or a declaration would
// answer against two of everything the worker has changed. The view is rooted at the checkout so the paths a rule
// matches folders against are the ones the project means, src and not .journal/worktrees/worker-545/src.
// Gone names the base's files to leave OUT of it, for files that are no longer on disk: replaced is not the only
// way a file changes, and a program holding a file that was deleted answers about code nobody has any more.
func (b *Base) ViewFrom(checkout string, rebuilt []*contract.Stream, project string, gone []string) *engine.Codebase {
	dropped := map[string]bool{}
	for _, path := range gone {
		dropped[path] = true
	}

	fresh := map[string]*contract.File{}
	for _, stream := range rebuilt {
		for _, file := range stream.Files {
			fresh[file.Path] = file
		}
	}

	// What each rebuilt file stands in for, by the one answer to that question: a second copy of it here read
	// the project's path as it was NAMED rather than as it is, so a project reached through a link stood every
	// rebuilt file in for a path the base does not hold.
	standsFor := map[string]bool{}
	for path := range fresh {
		if stands := Counterpart(checkout, project, path); stands != path {
			standsFor[stands] = true
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
			case dropped[file.Path]:
				// Gone from disk, so gone from the program.
			case standsFor[file.Path]:
				// The checkout's own file stands in for this one, which is added below. Asked before whether a
				// fresh copy of it was read: the main checkout can have drifted under the base while a worker
				// changed the same file, and keeping the drifted copy as well put TWO of that source in the
				// program, so every recurrence, twin and declaration rule saw the worker's file doubled.
			case fresh[file.Path] != nil:
				part.Files = append(part.Files, fresh[file.Path])
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

// Counterpart is the project's own file a checkout's file stands in for: the file itself for a file of the
// project's own checkout, and the file at the same path under the project for one of a worktree. Standing IN FOR
// it is the point, so the program holds one copy of each source, the checkout's.
//
// Built on the project's REAL path, because that is what the base's keys are: a scan resolves every path it
// reads, so a counterpart built on a project root reached through a link — a Mac's /tmp, a linked home, the
// folder a journal names — matches no key the base holds, and then every file of a worktree counts as a file the
// tree never read. Measured on a consumer: 1201 of them for a branch 127 files from the tip it was cut at, which
// is past what one judging reads, so every worker's hand-in was declined and read the whole tree in its own
// process while the main checkout's own runs were served.
//
// A file in neither checkout is left as it came: there is nothing to say about where it stands.
func Counterpart(checkout, project, file string) string {
	if real(checkout) == real(project) {
		return file
	}

	under, inside := Under(checkout, file)
	if !inside {
		return file
	}

	return filepath.Join(real(project), under)
}

// marksOf are what every file of the codebase was when it was read: the mark the caller took before the read
// where it took one, and what the file is now where it did not.
func marksOf(codebase *engine.Codebase, taken map[string]Known) map[string]Known {
	marks := map[string]Known{}
	for _, file := range codebase.Files() {
		if before, took := taken[file.File.Path]; took {
			marks[file.File.Path] = before

			continue
		}

		marks[file.File.Path] = Now(file.File.Path)
	}

	return marks
}

// Taken are what these files are now, by path: the marks to hold a base with, taken before the read it is built
// from begins.
func Taken(paths []string) map[string]Known {
	marks := map[string]Known{}
	for _, path := range paths {
		marks[path] = Now(path)
	}

	return marks
}

// Known is what a file was when something read it: the cheap stat mark, which the filesystem answers without
// opening the file, and the digest of its bytes, which is the only thing that settles whether it has really
// changed. Both, because each answers a question the other cannot afford to: re-stating a 5286-file tree costs
// 0.3 to 1.1 seconds and reading all of it costs far more, while a stat mark on its own says a file changed
// whenever anything merely touched it.
type Known struct {
	// Mark is the stat mark, and empty for a file that is not there.
	Mark string
	// Digest is the digest of the bytes, and empty where they could not be read.
	Digest string
}

// Now is what the file is at this moment, which is what a base is held with.
func Now(path string) Known {
	return Known{Mark: Mark(path), Digest: Digest(path)}
}

// Stands says whether the file is still what this says it was. The stat mark answers first and costs nothing, so
// a tree nothing has touched is settled without a single file being opened; a file whose mark has moved is asked
// the only question that decides it, which is whether its bytes are the ones that were read.
//
// A stat mark taken as proof is what made a worker's judging read the whole tree. A pull, a checkout, a rebase, a
// formatter or a build writes identical bytes back over hundreds of files and gives every one of them a new time:
// measured on a consumer, 1184 files "differed" from the tree for a branch 21 files from the tip it was cut at,
// past the 200 the service splices for one judging, so the run read 6000 files in its own process.
func (k Known) Stands(path string) bool {
	if k.Mark == "" {
		return false
	}

	now := Mark(path)
	if now == k.Mark {
		return true
	}

	// Gone is not unchanged, whatever a digest would say about a file nothing can read.
	if now == "" || k.Digest == "" {
		return false
	}

	return Digest(path) == k.Digest
}

// Digest is the digest of the file's bytes, and empty where they could not be read. Streamed, so a large file
// costs no more memory than a small one.
func Digest(path string) string {
	file, err := os.Open(path)
	if err != nil {
		return ""
	}
	defer file.Close()

	sum := sha256.New()
	if _, err := io.Copy(sum, file); err != nil {
		return ""
	}

	return hex.EncodeToString(sum.Sum(nil))
}

// Mark is the cheap part of what the file is now: its size and the moment it was last written, which is what the
// filesystem answers without opening it. It says a file MIGHT have changed, never that it has, since anything
// that rewrites identical bytes moves it, so it is the first question and never the last.
//
// It is how a base knows which of its files to ask about, and how anything else holding a parse of a file knows
// whether that parse may no longer be the file.
func Mark(path string) string {
	info, err := os.Stat(path)
	if err != nil {
		return ""
	}

	return info.ModTime().UTC().Format(time.RFC3339Nano) + "\x00" + strconv.FormatInt(info.Size(), 10)
}
