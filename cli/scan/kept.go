package scan

import (
	"bufio"
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"os"
	"path/filepath"
	"runtime"
	"slices"
	"strconv"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
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
	prints   sync.Map
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

	return &keptLines{folder: filepath.Join(shared, "lines", b.Kind), root: root, identity: b.identity()}
}

// split hands back the files whose kept line still stands, read, and the files a bridge must read.
func (k *keptLines) split(files []string) (hits []*contract.File, header *contract.Header, misses []string) {
	header = k.header()
	if header == nil {
		return nil, nil, files
	}

	found := make([]*contract.File, len(files))
	next := make(chan int)
	var reading sync.WaitGroup
	for range runtime.GOMAXPROCS(0) {
		reading.Add(1)
		go func() {
			defer reading.Done()
			reader := contract.KeptUnder(*header)
			for at := range next {
				found[at] = k.line(reader, files[at])
			}
		}()
	}
	for at := range files {
		next <- at
	}
	close(next)
	reading.Wait()

	for at, file := range files {
		if found[at] != nil {
			hits = append(hits, found[at])
			continue
		}
		misses = append(misses, file)
	}

	return hits, header, misses
}

func (k *keptLines) line(reader *contract.Kept, file string) *contract.File {
	for _, reads := range k.variants(file) {
		raw, err := os.ReadFile(k.recordOf(file, reads))
		if err != nil {
			continue
		}
		if found, err := reader.File(k.unmarked(raw)); err == nil {
			return found
		}
	}

	return nil
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

// settle files every staged line under its key, now that the stream that wrote them has ended.
func (k *keptLines) settle() {
	k.staging.Lock()
	staged := k.staged
	k.staged = nil
	k.staging.Unlock()

	for _, line := range staged {
		if !slices.ContainsFunc(k.variants(line.path), func(known []string) bool { return slices.Equal(known, line.reads) }) {
			k.addVariant(line.path, line.reads)
		}
		if os.Rename(line.held, k.recordOf(line.path, line.reads)) != nil {
			_ = os.Remove(line.held)
		}
	}
}

// keepOutline keeps what a file declares for the files that read it, under its contents.
func (k *keptLines) keepOutline(file *contract.File) {
	print := k.print(file.Path)
	if print == "" {
		return
	}
	outline := strconv.FormatUint(file.Outline(k.marked), 16)
	k.outlines.Store(file.Path, outline)
	_ = atomic.Write(k.outlinePath(file.Path, print), outline)
}

// outlineOf is what a key holds for a file a tree read: the outline kept for its contents, else the contents
// themselves, so a file whose outline was never kept stands by its every byte.
func (k *keptLines) outlineOf(file string) string {
	if held, ok := k.outlines.Load(file); ok {
		return held.(string)
	}
	print := k.print(file)
	kept, err := os.ReadFile(k.outlinePath(file, print))
	if err != nil || print == "" {
		return "contents " + print
	}
	k.outlines.Store(file, string(kept))

	return string(kept)
}

func (k *keptLines) outlinePath(file, print string) string {
	return filepath.Join(k.folder, named(k.identity, k.marked(file), print)+".outline")
}

// deferred splits the files a bridge must read into those to read first and those that read one of them: an edit
// inside a body leaves its outline as it was, so once the first are read the others may stand from the cache.
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
		return later, nil
	}

	return first, later
}

// variants are the sets of files the trees kept for this file's contents read, one per line of its index: a file
// whose imports differ between checkouts keeps a line for each.
func (k *keptLines) variants(file string) [][]string {
	index, err := os.Open(k.indexOf(file))
	if err != nil {
		return nil
	}
	defer index.Close()

	var found [][]string
	lines := bufio.NewScanner(index)
	lines.Buffer(make([]byte, 0, 64<<10), 16<<20)
	for lines.Scan() {
		var reads []string
		if json.Unmarshal(lines.Bytes(), &reads) == nil {
			found = append(found, reads)
		}
	}

	return found
}

func (k *keptLines) addVariant(file string, reads []string) {
	defer atomic.Hold(filepath.Join(k.folder, "index.lock"))()
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

// print is the fingerprint of a file's contents, read once a run; a file that cannot be read prints empty, so a line
// that read it never stands for one that is there.
func (k *keptLines) print(file string) string {
	if held, ok := k.prints.Load(file); ok {
		return held.(string)
	}
	text, err := os.ReadFile(file)
	printed := ""
	if err == nil {
		sum := sha256.Sum256(text)
		printed = hex.EncodeToString(sum[:])
	}
	k.prints.Store(file, printed)

	return printed
}

func (k *keptLines) header() *contract.Header {
	if k.folder == "" {
		return nil
	}
	raw, err := os.ReadFile(k.headerPath())
	if err != nil {
		return nil
	}
	var header contract.Header
	if json.Unmarshal(k.unmarked(raw), &header) != nil || !header.Bridge.Reads {
		return nil
	}

	return &header
}

// standing is the kept header as this run's stream carries it: its own roots.
func (k *keptLines) standing(header contract.Header, roots []string) contract.Header {
	header.Roots = roots

	return header
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
// changed writes the facts those files reached, and the rest stand from before.
func (k *keptLines) program(fresh *contract.Program) *contract.Program {
	if k.folder == "" {
		return fresh
	}
	defer atomic.Hold(k.programPath() + ".lock")()
	var kept contract.Program
	if raw, err := os.ReadFile(k.programPath()); err == nil {
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
	joined := contract.Program{
		Symbols:    onceOf(kept.Symbols, fresh.Symbols),
		Unreadable: onceOf(kept.Unreadable, fresh.Unreadable),
		Packages:   onceOf(kept.Packages, fresh.Packages),
		Aliases:    onceOf(kept.Aliases, fresh.Aliases),
		Loader:     kept.Loader,
	}
	if fresh.Loader != "" {
		joined.Loader = fresh.Loader
	}

	return joined
}

// onceOf is every entry of both, the fresh one standing where both hold the same.
func onceOf[T any](kept, fresh []T) []T {
	seen := map[string]bool{}
	var joined []T
	for _, entry := range append(slices.Clone(fresh), kept...) {
		key, _ := json.Marshal(entry)
		if !seen[string(key)] {
			seen[string(key)] = true
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
