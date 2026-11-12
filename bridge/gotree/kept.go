package gotree

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"go/ast"
	"go/parser"
	"go/token"
	"os"
	"path/filepath"
	"sort"
	"time"

	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/contract"
)

// keptLines are the Go file lines an earlier run wrote, kept on disk with their path left out under a key of the file's own text and the skeleton of every Go file the run reads: each file with its function bodies cut out, which is all one file's types can see of another. A change inside a body keeps every other file's line; a changed declaration anywhere starts them over. A line is touched when read, so pruning by age spares what is used.
type keptLines struct {
	folder   string
	skeleton string
}

func keptLinesFor(found []string, packages []Package) (keptLines, bool) {
	folder, err := bundle.TreesFolder("go")
	if err != nil {
		return keptLines{}, false
	}

	modules := map[string]Module{}
	roots := map[string]bool{}
	for _, one := range packages {
		if module, found := moduleOf(one.Folder, modules); found {
			roots[module.Root] = true
		}
	}

	return keptLines{folder: folder, skeleton: skeletonOf(found, roots)}, true
}

func (k keptLines) allOf(one Package, context func(string) bool) ([]*contract.File, bool) {
	var files []*contract.File
	for _, path := range one.Files {
		file, held := k.read(k.keyOf(path, one.Path), path, context(path))
		if !held {
			return nil, false
		}
		files = append(files, file)
	}

	return files, len(files) > 0
}

func (k keptLines) keyOf(path, module string) string {
	source, err := read(path)
	if err != nil {
		return ""
	}

	digest := sha256.New()
	for _, part := range []string{Version(), module, k.skeleton} {
		digest.Write([]byte(part))
		digest.Write([]byte{0})
	}
	digest.Write(source)

	return hex.EncodeToString(digest.Sum(nil))
}

func (k keptLines) read(key, path string, context bool) (*contract.File, bool) {
	entry := filepath.Join(k.folder, key+".line")

	stored, err := os.ReadFile(entry)
	if err != nil {
		return nil, false
	}

	var file contract.File
	if json.Unmarshal(stored, &file) != nil {
		return nil, false
	}

	now := time.Now()
	os.Chtimes(entry, now, now)
	file.Path, file.Context = path, context

	return &file, true
}

func (k keptLines) keep(key string, file *contract.File) {
	pathless := *file
	pathless.Path, pathless.Context = "", false

	written, err := json.Marshal(&pathless)
	if err != nil {
		return
	}

	draft, err := os.CreateTemp(k.folder, ".writing-")
	if err != nil {
		return
	}
	defer os.Remove(draft.Name())

	if _, err := draft.Write(written); err != nil {
		draft.Close()

		return
	}
	draft.Close()
	os.Rename(draft.Name(), filepath.Join(k.folder, key+".line"))
}

func skeletonOf(found []string, roots map[string]bool) string {
	var parts []string
	for root := range roots {
		for _, file := range []string{"go.mod", "go.sum"} {
			if text, err := os.ReadFile(filepath.Join(root, file)); err == nil {
				parts = append(parts, fingerprint(text))
			}
		}
	}

	fset := token.NewFileSet()
	for _, path := range found {
		if source, err := read(path); err == nil {
			parts = append(parts, fingerprint(withoutBodies(fset, path, source)))
		}
	}

	sort.Strings(parts)
	digest := sha256.New()
	for _, part := range parts {
		digest.Write([]byte(part))
	}

	return hex.EncodeToString(digest.Sum(nil))
}

func fingerprint(text []byte) string {
	sum := sha256.Sum256(text)

	return hex.EncodeToString(sum[:])
}

func withoutBodies(fset *token.FileSet, path string, source []byte) []byte {
	parsed, _ := parser.ParseFile(fset, path, source, parser.SkipObjectResolution)
	if parsed == nil {
		return source
	}

	var kept []byte
	from := 0
	for _, declared := range parsed.Decls {
		function, isFunction := declared.(*ast.FuncDecl)
		if !isFunction || function.Body == nil {
			continue
		}

		start, end := fset.Position(function.Body.Lbrace).Offset, fset.Position(function.Body.Rbrace).Offset+1
		kept = append(kept, source[from:start]...)
		from = end
	}

	return append(kept, source[from:]...)
}
