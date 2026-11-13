package scan

import (
	"bufio"
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"io"
	"os"
	"path/filepath"
	"runtime"
	"slices"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/sidebyside"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/walk"
)

// keptLines is the one cache of every bridge's file lines, shared by every checkout of a project and keyed on contents
// alone: a line stands while its own file holds the contents it held when it was written and every file its tree read
// holds the same outline, wherever the checkout lies, so an edit inside a function body re-reads that file alone. A
// bridge whose header does not say it names what each tree read keeps nothing.
type keptLines struct {
	folder string
	root   string
	// identity is the code of the bridge that wrote the lines, so a release that leaves the bridge alone keeps them.
	identity string
	outlines sync.Map
	// staged are the lines a read wrote, held on disk until its stream ends and every outline they stand on is known.
	staged  []stagedLine
	staging sync.Mutex
}

type stagedLine struct {
	path  string
	reads []string
	held  string
}

// checkoutMark stands for the checkout's own folder inside a kept line, so a line written in one checkout reads in any.
const checkoutMark = "@checkout@"

func linesKeptFor(b Bridge, roots []string) *keptLines {
	if len(roots) == 0 {
		return &keptLines{}
	}
	shared, root := workspace.SharedFolder(roots[0]), git.Root(roots[0])
	if shared == "" || root == "" {
		return &keptLines{}
	}

	identity := b.identity()
	kind := filepath.Join(shared, "lines", b.Kind)
	folder := filepath.Join(kind, named(identity)[:16])
	if !thisBuildsFolder(kind, folder) {
		return &keptLines{}
	}
	go removeOtherBuilds(kind)

	return &keptLines{folder: folder, root: root, identity: identity}
}

// thisBuildsFolder makes the folder the lines of this bridge's build are kept in. A bridge's lines are keyed on its
// code, so the lines of any other build can never stand again: the first run of a new build moves them all aside at
// once, for removeOtherBuilds to delete.
func thisBuildsFolder(kind, folder string) bool {
	if walk.IsFolder(folder) {
		return true
	}
	defer atomic.Hold(kind + ".lock")()
	if walk.IsFolder(folder) {
		return true
	}
	if walk.IsFolder(kind) {
		_ = os.Rename(kind, kind+otherBuilds+strconv.FormatInt(time.Now().UnixNano(), 10))
	}

	return os.MkdirAll(folder, 0o755) == nil
}

// otherBuilds marks a folder of lines another build of the bridge kept, moved aside to be deleted.
const otherBuilds = ".other-build-"

// removeOtherBuilds deletes the lines of other builds moved aside, as far as the run gets; the next run goes on.
func removeOtherBuilds(kind string) {
	aside, _ := filepath.Glob(kind + otherBuilds + "*")
	for _, folder := range aside {
		_ = os.RemoveAll(folder)
	}
}

// split hands back the files whose kept line still stands, read, and the files a bridge must read.
func (k *keptLines) split(files []string) (hits []*contract.File, header *contract.Header, misses []string) {
	header = k.header()
	if header == nil {
		return nil, nil, files
	}

	found := make([]*contract.File, len(files))
	readsOf := make([][]string, len(files))
	sidebyside.Each(len(files), runtime.GOMAXPROCS(0), func(at int) {
		found[at], readsOf[at] = k.line(contract.KeptUnder(*header), files[at])
	})
	standing := k.standingOnWhatTheyRead(files, readsOf, func(at int) bool { return found[at] != nil })

	for at, file := range files {
		if standing[at] {
			hits = append(hits, found[at])
			continue
		}
		misses = append(misses, file)
	}

	return hits, header, misses
}

// standingRecords answers the record of each file whose kept line still stands and the files a bridge must read, for a
// run that takes each kept line from its record only when it reaches the file's project.
func (k *keptLines) standingRecords(files []string) (records map[string]string, header *contract.Header, misses []string) {
	records = map[string]string{}
	header = k.header()
	if header == nil {
		return records, nil, files
	}
	found := make([]string, len(files))
	readsOf := make([][]string, len(files))
	for at, file := range files {
		found[at], readsOf[at] = k.standingRecord(file)
	}
	standing := k.standingOnWhatTheyRead(files, readsOf, func(at int) bool { return found[at] != "" })
	for at, file := range files {
		if standing[at] {
			records[file] = found[at]

			continue
		}
		misses = append(misses, file)
	}

	return records, header, misses
}

func (k *keptLines) standingRecord(file string) (string, []string) {
	for _, reads := range k.variants(file) {
		if record := k.recordOf(file, reads); walk.IsFile(record) {
			return record, reads
		}
	}

	return "", nil
}

func (k *keptLines) line(reader *contract.Kept, file string) (*contract.File, []string) {
	for _, reads := range k.variants(file) {
		raw, err := used(k.recordOf(file, reads))
		if err != nil {
			continue
		}
		if found, err := reader.File(k.unmarked(raw)); err == nil {
			return found, reads
		}
	}

	return nil, nil
}

// standingOnWhatTheyRead answers which files' kept lines stand: each found one whose reads among the files all stand
// too. A file that must be read again may declare something new, and the outline its readers' keys hold of it is the
// one it had before, so its readers are read after it, however far up the imports they lie.
func (k *keptLines) standingOnWhatTheyRead(files []string, readsOf [][]string, found func(int) bool) []bool {
	standing := make([]bool, len(files))
	missed := map[string]bool{}
	for at, file := range files {
		standing[at] = found(at)
		if !standing[at] {
			missed[k.marked(file)] = true
		}
	}
	for changed := true; changed; {
		changed = false
		for at, file := range files {
			if standing[at] && slices.ContainsFunc(readsOf[at], func(read string) bool { return missed[read] }) {
				standing[at], missed[k.marked(file)], changed = false, true, true
			}
		}
	}

	return standing
}

// keep takes a file line a bridge just wrote: its outline is kept at once, and the line is staged until settle files it
// under its contents and the outlines of every file its tree read, which a line later in the stream may bring.
func (k *keptLines) keep(header contract.Header, file *contract.File, raw []byte) {
	if k.folder == "" || !header.Bridge.Reads || file.Context {
		return
	}
	k.keepHeader(header)
	k.keepOutline(file)

	reads := make([]string, 0, len(file.Reads))
	for _, read := range file.Reads {
		reads = append(reads, k.marked(read))
	}
	slices.Sort(reads)
	reads = slices.Compact(reads)

	staged, err := os.CreateTemp(k.folder, "staged-*")
	if err != nil {
		return
	}
	_, err = staged.WriteString(k.marked(string(raw)))
	if staged.Close() != nil || err != nil {
		_ = os.Remove(staged.Name())

		return
	}
	k.staging.Lock()
	k.staged = append(k.staged, stagedLine{file.Path, reads, staged.Name()})
	k.staging.Unlock()
}

// settle files every staged line under its key, now that the stream that wrote them has ended. A line whose file, or a
// file its tree read, changed while the bridge read it is dropped: its tree may hold contents its key does not name.
func (k *keptLines) settle() {
	k.staging.Lock()
	staged := k.staged
	k.staged = nil
	k.staging.Unlock()

	now := map[string]string{}
	for _, line := range staged {
		if k.changedSince(line.path, now) || slices.ContainsFunc(line.reads, func(read string) bool { return k.changedSince(k.unmarkedPath(read), now) }) {
			_ = os.Remove(line.held)

			continue
		}
		k.addVariant(line.path, line.reads)
		if os.Rename(line.held, k.recordOf(line.path, line.reads)) != nil {
			_ = os.Remove(line.held)
		}
	}
	if k.folder != "" {
		cache.Sweep(k.folder, time.Now())
	}
}

// printedFirst fingerprints every file before a bridge reads them, so a line is keyed on the contents its bridge read.
func (k *keptLines) printedFirst(files []string) {
	if k.folder == "" {
		return
	}
	sidebyside.Each(len(files), runtime.GOMAXPROCS(0), func(at int) { k.print(files[at]) })
}

// changedSince says whether a file's contents differ from the print this run took of it, read once into now.
func (k *keptLines) changedSince(file string, now map[string]string) bool {
	if _, known := now[file]; !known {
		now[file] = printed(walk.PrintNow(file))
	}

	return now[file] != k.print(file)
}

// keepOutline keeps what a file declares for the files that read it, under its contents. An outline holds types its
// imports resolved, so when two checkouts outline the same contents differently the slot is marked contested for good,
// and the file's readers are keyed on what it read instead.
func (k *keptLines) keepOutline(file *contract.File) {
	print := k.print(file.Path)
	if print == "" {
		return
	}
	outline := strconv.FormatUint(file.Outline(k.marked), 16)
	path := k.outlinePath(file.Path, print)
	defer atomic.Hold(filepath.Join(k.folder, "index.lock"))()
	if kept, err := os.ReadFile(path); err == nil && string(kept) != outline {
		outline = contested
	}
	_ = atomic.Write(path, outline)
	k.outlines.Delete(file.Path)
}

// contested is what an outline slot holds once two checkouts kept different outlines for the same contents.
const contested = "contested"

// outlineOf is what a key holds for a file a tree read: the outline kept for its contents, else the contents
// themselves, so a file whose outline was never kept stands by its every byte. A contested outline stands for the
// contents and the outlines of every file it read.
func (k *keptLines) outlineOf(file string) string {
	if held, ok := k.outlines.Load(file); ok {
		return held.(string)
	}
	outline := k.ownOutline(file)
	if outline == contested {
		return k.contestedOutline(file)
	}
	if !strings.HasPrefix(outline, "contents ") {
		k.outlines.Store(file, outline)
	}

	return outline
}

// forget drops the outlines this run took of files a bridge has just read again: their new outlines were kept by the
// read, and a key built after it names them.
func (k *keptLines) forget(files []string) {
	for _, file := range files {
		k.outlines.Delete(file)
	}
}

// ownOutline is the outline kept for a file's contents, contested, or its contents when none was kept.
func (k *keptLines) ownOutline(file string) string {
	print := k.print(file)
	kept, err := used(k.outlinePath(file, print))
	if err != nil || print == "" {
		return "contents " + print
	}

	return string(kept)
}

// contestedOutline names a contested file by its contents and each file any of its kept lines read, by that file's own
// outline, or its contents when that is contested too, so no file is outlined through a chain of others.
func (k *keptLines) contestedOutline(file string) string {
	var reads []string
	for _, variant := range k.variants(file) {
		reads = append(reads, variant...)
	}
	slices.Sort(reads)
	parts := []string{contested, k.print(file)}
	for _, read := range slices.Compact(reads) {
		outline := k.ownOutline(k.unmarkedPath(read))
		if outline == contested {
			outline = "contents " + k.print(k.unmarkedPath(read))
		}
		parts = append(parts, read, outline)
	}

	return named(parts...)
}

func (k *keptLines) outlinePath(file, print string) string {
	return filepath.Join(k.folder, named(k.identity, k.marked(file), print)+".outline")
}

// deferred splits the files a bridge must read into those to read first and those that read one of them: an edit
// inside a body leaves its outline as it was, so once the first are read the others may stand from the cache. Where
// every miss reads another, as files that import each other do, the first are those with no kept line for what they
// read now, and none when every one has: then each stands on its own line, for nothing it reads will be read again.
func (k *keptLines) deferred(misses []string) (first, later []string) {
	missed := map[string]bool{}
	for _, miss := range misses {
		missed[k.marked(miss)] = true
	}
	for _, miss := range misses {
		readsAMiss := slices.ContainsFunc(k.variants(miss), func(reads []string) bool {
			return slices.ContainsFunc(reads, func(read string) bool { return missed[read] })
		})
		if readsAMiss {
			later = append(later, miss)

			continue
		}
		first = append(first, miss)
	}
	if len(first) == 0 {
		return k.withoutALineOfTheirOwn(later)
	}

	return first, later
}

// withoutALineOfTheirOwn splits files into those with no kept line for what they read now and those with one.
func (k *keptLines) withoutALineOfTheirOwn(files []string) (without, with []string) {
	for _, file := range files {
		if k.ownLine(file) == nil {
			without = append(without, file)
			continue
		}
		with = append(with, file)
	}

	return without, with
}

// ownLines are the kept lines of files that stand on lines of their own, each read for what it reads now, and the
// files whose line went in the meantime, for the bridge to read.
func (k *keptLines) ownLines(files []string) (lines []*contract.File, without []string) {
	for _, file := range files {
		if found := k.ownLine(file); found != nil {
			lines = append(lines, found)
			continue
		}
		without = append(without, file)
	}

	return lines, without
}

func (k *keptLines) ownLine(file string) *contract.File {
	header := k.header()
	if header == nil {
		return nil
	}
	found, _ := k.line(contract.KeptUnder(*header), file)

	return found
}

// variants are the sets of files the trees kept for this file's contents read, one per line of its index: a file
// whose imports differ between checkouts keeps a line for each.
func (k *keptLines) variants(file string) [][]string {
	index, err := used(k.indexOf(file))
	if err != nil {
		return nil
	}

	var found [][]string
	lines := bufio.NewScanner(bytes.NewReader(index))
	lines.Buffer(make([]byte, 0, 64<<10), 16<<20)
	for lines.Scan() {
		var reads []string
		if json.Unmarshal(lines.Bytes(), &reads) == nil {
			found = append(found, reads)
		}
	}

	return found
}

// addVariant adds the files a tree read to its file's index, once: the check and the write share the lock, so two runs
// settling the same line never write it twice.
func (k *keptLines) addVariant(file string, reads []string) {
	defer atomic.Hold(filepath.Join(k.folder, "index.lock"))()
	if slices.ContainsFunc(k.variants(file), func(known []string) bool { return slices.Equal(known, reads) }) {
		return
	}
	line, _ := json.Marshal(reads)
	known, _ := os.ReadFile(k.indexOf(file))
	_ = atomic.Write(k.indexOf(file), string(known)+string(line)+"\n")
}

func (k *keptLines) indexOf(file string) string {
	return filepath.Join(k.folder, named(k.identity, k.marked(file), k.print(file))+".reads")
}

func (k *keptLines) recordOf(file string, reads []string) string {
	parts := []string{k.identity, "outlined", k.marked(file), k.print(file)}
	for _, read := range reads {
		parts = append(parts, read, k.outlineOf(k.unmarkedPath(read)))
	}

	return filepath.Join(k.folder, named(parts...)+".line")
}

// print is the fingerprint of a file's contents, read once a process; a file that cannot be read prints empty, so a
// line that read it never stands for one that is there.
func (k *keptLines) print(file string) string {
	return printed(walk.Print(file))
}

func printed(print [32]byte, err error) string {
	if err != nil {
		return ""
	}

	return hex.EncodeToString(print[:])
}

func (k *keptLines) header() *contract.Header {
	if k.folder == "" {
		return nil
	}
	raw, err := used(k.headerPath())
	if err != nil {
		return nil
	}
	var header contract.Header
	if json.Unmarshal(k.unmarked(raw), &header) != nil || !header.Bridge.Reads {
		return nil
	}

	return &header
}

// standing is the kept header as this run's stream carries it: its own roots, and named for the files it serves.
func (k *keptLines) standing(header contract.Header, roots []string, files []*contract.File) contract.Header {
	header.Roots = roots
	header.Language = languageOf(header, files)

	return header
}

// languageOf is the language a stream holding these files is named for, whichever request first kept its header.
func languageOf(header contract.Header, files []*contract.File) contract.Language {
	var languages []contract.Language
	for _, file := range files {
		if !slices.Contains(languages, file.Language) {
			languages = append(languages, file.Language)
		}
	}
	if len(languages) == 0 {
		return header.Language
	}

	return contract.StreamLanguage(languages)
}

func (k *keptLines) keepHeader(header contract.Header) {
	if _, err := os.Stat(k.headerPath()); err == nil {
		return
	}
	header.Roots = nil
	raw, _ := json.Marshal(header)
	_ = atomic.Write(k.headerPath(), string(raw))
}

func (k *keptLines) headerPath() string {
	return filepath.Join(k.folder, named(k.identity)+".header")
}

// program is the program line every kept run of this bridge wrote, joined: a run that reads only the files that
// changed writes the facts those files reached, and the rest stand from before. Only a run that joins a fresh program
// takes the lock: the file is replaced whole, so a run that only reads it never sees half of it.
func (k *keptLines) program(fresh *contract.Program) *contract.Program {
	if k.folder == "" {
		return fresh
	}
	if fresh != nil {
		defer atomic.Hold(k.programPath() + ".lock")()
	}
	var kept contract.Program
	if raw, err := used(k.programPath()); err == nil {
		_ = json.Unmarshal(k.unmarked(raw), &kept)
	}
	joined := joinedPrograms(kept, fresh)
	if fresh != nil {
		raw, _ := json.Marshal(joined)
		_ = atomic.Write(k.programPath(), k.marked(string(raw)))
	}

	return &joined
}

func (k *keptLines) programPath() string {
	return filepath.Join(k.folder, named(k.identity)+".program")
}

func joinedPrograms(kept contract.Program, fresh *contract.Program) contract.Program {
	if fresh == nil {
		return kept
	}
	return contract.Program{
		Symbols:    onceBy(kept.Symbols, fresh.Symbols, func(symbol contract.OutsideSymbol) string { return symbol.Symbol }),
		Unreadable: onceBy(kept.Unreadable, fresh.Unreadable, func(symbol contract.UnreadableSymbol) string { return symbol.Symbol }),
		Packages:   onceBy(kept.Packages, fresh.Packages, func(name string) string { return name }),
		Aliases:    onceBy(kept.Aliases, fresh.Aliases, func(alias contract.Alias) string { return alias.Prefix }),
		Loader:     fresh.Loader,
	}
}

// onceBy is every entry of both, one per key, the fresh one standing where both name the same: a symbol read again
// replaces what an earlier run read of it.
func onceBy[T any](kept, fresh []T, keyOf func(T) string) []T {
	seen := map[string]bool{}
	var joined []T
	for _, entry := range append(slices.Clone(fresh), kept...) {
		if key := keyOf(entry); !seen[key] {
			seen[key] = true
			joined = append(joined, entry)
		}
	}

	return joined
}

func (k *keptLines) marked(text string) string {
	return strings.ReplaceAll(text, k.root, checkoutMark)
}

func (k *keptLines) unmarked(raw []byte) []byte {
	return bytes.ReplaceAll(raw, []byte(checkoutMark), []byte(k.root))
}

func (k *keptLines) unmarkedPath(path string) string {
	return strings.ReplaceAll(path, checkoutMark, k.root)
}

func named(parts ...string) string {
	digest := sha256.New()
	for _, part := range parts {
		digest.Write([]byte(part))
		digest.Write([]byte{0})
	}

	return hex.EncodeToString(digest.Sum(nil))[:40]
}

// used reads a file of the cache and renews its clock when a good part of the time it is kept has gone by, so the sweep
// removes only what no run used for the whole time.
func used(path string) ([]byte, error) {
	file, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer file.Close()
	info, err := file.Stat()
	if err != nil {
		return nil, err
	}
	if time.Since(info.ModTime()) > cache.KeptFor()/4 {
		now := time.Now()
		_ = os.Chtimes(path, now, now)
	}

	return io.ReadAll(file)
}
