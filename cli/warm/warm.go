// Package warm keeps a project's parsed tree in memory, one base per branch, so a hook judges an edit against the whole program instead of reading the tree again.
package warm

import (
	"crypto/sha256"
	"encoding/hex"
	"io"
	"maps"
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

const Sweep = 30 * time.Second

const Idle = 15 * time.Minute

// Base is one branch's tree, held as the streams it was built from so a view can be made over it without parsing anything again, with what each file was when it was read.
type Base struct {
	Project string
	Branch  string

	streams   []*contract.Stream
	marks     map[string]Known
	roots     []string
	codebase  *engine.Codebase
	read      time.Time
	lastAsked time.Time
	stamp     uint64

	mu    sync.Mutex
	swept time.Time
}

// Keeper holds the bases, one per project and branch, and hands out the one a question is about.
type Keeper struct {
	mu       sync.Mutex
	bases    map[string]*Base
	overlays map[string]*Overlay
	cutting  map[string]string
	ceiling  int
	stamps   uint64
	leaving  []func(checkout string)
	now      func() time.Time
}

func Keep() *Keeper {
	return &Keeper{bases: map[string]*Base{}, overlays: map[string]*Overlay{}, cutting: map[string]string{}, now: time.Now}
}

func (k *Keeper) Clocked(now func() time.Time) *Keeper {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.now = now

	return k
}

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

	k.stamps++
	base.stamp = k.stamps
	k.bases[key(project, branch)] = base

	return base
}

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

	k.stamps++
	base.stamp = k.stamps
	k.bases[key(was.Project, was.Branch)] = base

	return base
}

func (b *Base) sweptAt() time.Time {
	b.mu.Lock()
	defer b.mu.Unlock()

	return b.swept
}

func (k *Keeper) Drop(project, branch string) {
	k.mu.Lock()
	defer k.mu.Unlock()

	delete(k.bases, key(project, branch))
}

// Tree is one whole tree the keeper holds: the project and branch it is of, how much it covers and since when.
type Tree struct {
	Project string
	Branch  string
	Files   int
	Nodes   int
	Read    time.Time
}

func (k *Keeper) Trees() []Tree {
	k.mu.Lock()
	defer k.mu.Unlock()

	var trees []Tree
	for _, base := range k.bases {
		trees = append(trees, Tree{Project: base.Project, Branch: base.Branch, Files: base.Files(), Nodes: base.Nodes(), Read: base.read})
	}

	return trees
}

func (k *Keeper) Bases() []*Base {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.sweep()

	return slices.Collect(maps.Values(k.bases))
}

func (b *Base) Stamp() uint64 {
	return b.stamp
}

func (k *Keeper) Held() int {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.sweep()

	return len(k.bases)
}

func (k *Keeper) sweep() {
	for named, base := range k.bases {
		if k.now().Sub(base.lastAsked) >= Idle {
			delete(k.bases, named)
		}
	}

	for checkout, over := range k.overlays {
		if k.now().Sub(over.asked) < Idle && !k.gone(checkout) {
			continue
		}

		delete(k.overlays, checkout)
		k.left(checkout)
	}
}

func (k *Keeper) gone(checkout string) bool {
	info, err := os.Stat(checkout)

	return err != nil || !info.IsDir()
}

func (k *Keeper) Leaving(tell func(checkout string)) {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.leaving = append(k.leaving, tell)
}

func (k *Keeper) left(checkout string) {
	for _, tell := range k.leaving {
		tell(checkout)
	}
}

func key(project, branch string) string {
	return project + "\x00" + branch
}

func (b *Base) Codebase() *engine.Codebase {
	return b.codebase
}

func (b *Base) Roots() []string {
	return b.roots
}

func (b *Base) Moved() []string {
	var moved []string
	for path, known := range b.marks {
		if !known.Stands(path) {
			moved = append(moved, path)
		}
	}

	return moved
}

func (b *Base) Nodes() int {
	held := 0
	for _, file := range b.codebase.Files() {
		held += len(file.Nodes())
	}

	return held
}

func (b *Base) Files() int {
	return len(b.marks)
}

func (b *Base) Languages() []contract.Language {
	var languages []contract.Language
	for _, stream := range b.streams {
		if !slices.Contains(languages, stream.Header.Language) {
			languages = append(languages, stream.Header.Language)
		}
	}

	return languages
}

func (b *Base) Gone() []string {
	var gone []string
	for path := range b.marks {
		if Mark(path) == "" {
			gone = append(gone, path)
		}
	}

	return gone
}

func (b *Base) Stale(edited string, now time.Time) (stale []string, swept bool) {
	return b.StaleSince(now, edited)
}

func (b *Base) StaleSince(now time.Time, edited ...string) (stale []string, swept bool) {
	stale = append(stale, edited...)

	b.mu.Lock()
	due := now.Sub(b.swept) >= Sweep
	if due {
		b.swept = now
	}
	b.mu.Unlock()

	if !due {
		return stale, false
	}

	for _, moved := range b.Moved() {
		if !slices.Contains(edited, moved) {
			stale = append(stale, moved)
		}
	}

	return stale, true
}

func (b *Base) Unheld(paths []string) []string {
	var unheld []string
	for _, path := range paths {
		if _, known := b.marks[path]; !known {
			unheld = append(unheld, path)
		}
	}

	return unheld
}

func (b *Base) Holds(path string) (unchanged bool, known bool) {
	was, held := b.marks[path]
	if !held {
		return false, false
	}

	return was.Stands(path), true
}

func (b *Base) View(rebuilt []*contract.Stream, project string) *engine.Codebase {
	return b.ViewFrom(project, rebuilt, project, nil)
}

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

	added := map[contract.Language][]*contract.File{}
	for _, stream := range rebuilt {
		for _, file := range stream.Files {
			if !held[file.Path] {
				added[stream.Header.Language] = append(added[stream.Header.Language], file)
			}
		}
	}

	var spliced []*contract.Stream
	placed := map[contract.Language]bool{}
	for _, stream := range b.streams {
		part := *stream
		part.Files = nil

		for _, file := range stream.Files {
			switch {
			case dropped[file.Path]:
			case standsFor[file.Path]:
			case fresh[file.Path] != nil:
				part.Files = append(part.Files, fresh[file.Path])
			default:
				part.Files = append(part.Files, file)
			}
		}

		if !placed[stream.Header.Language] {
			part.Files = append(part.Files, added[stream.Header.Language]...)
			placed[stream.Header.Language] = true
		}

		spliced = append(spliced, &part)
	}

	for _, stream := range rebuilt {
		if !slices.ContainsFunc(b.streams, func(held *contract.Stream) bool { return held.Header.Language == stream.Header.Language }) {
			spliced = append(spliced, stream)
		}
	}

	var changed []string
	for path := range fresh {
		changed = append(changed, path)
	}

	view := engine.Filling(changed, spliced...)
	view.Scanned(append(slices.Clone(b.roots), checkout)...)
	view.Rooted(checkout)
	view.Carries(b.codebase, engine.CarriedOver(changed)...)

	return view
}

func Same(one, two string) bool {
	return real(one) == real(two)
}

func Under(folder, path string) (string, bool) {
	under, err := filepath.Rel(real(folder), real(path))
	if err != nil || strings.HasPrefix(under, "..") {
		return "", false
	}

	return under, true
}

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

func Taken(paths []string) map[string]Known {
	marks := map[string]Known{}
	for _, path := range paths {
		marks[path] = Now(path)
	}

	return marks
}

// Known is what a file was when something read it: the cheap stat mark, which the filesystem answers without opening the file, and the digest of its bytes, which is the only thing that settles whether it has really changed.
type Known struct {
	Mark   string
	Digest string
}

func Now(path string) Known {
	return Known{Mark: Mark(path), Digest: Digest(path)}
}

func (k Known) Stands(path string) bool {
	if k.Mark == "" {
		return false
	}

	now := Mark(path)
	if now == k.Mark {
		return true
	}

	if now == "" || k.Digest == "" {
		return false
	}

	return Digest(path) == k.Digest
}

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

func Mark(path string) string {
	info, err := os.Stat(path)
	if err != nil {
		return ""
	}

	return info.ModTime().UTC().Format(time.RFC3339Nano) + "\x00" + strconv.FormatInt(info.Size(), 10)
}

func (k *Keeper) DropsEveryBranchBut(project, branch string) []string {
	k.mu.Lock()
	defer k.mu.Unlock()

	keeping := key(project, branch)

	var dropped []string
	for held := range k.bases {
		if held == keeping || !strings.HasPrefix(held, project+"\x00") {
			continue
		}

		dropped = append(dropped, strings.TrimPrefix(held, project+"\x00"))
		delete(k.bases, held)
	}

	return dropped
}
