package gotree

import (
	"crypto/sha256"
	"embed"
	"io/fs"
	"strings"
	"encoding/hex"
	"encoding/json"
	"go/ast"
	"go/parser"
	"go/token"
	"os"
	"path/filepath"
	"sort"
	"strconv"
	"time"

	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/contract"
)

//go:embed *.go
var ownSources embed.FS

var bridgeIdentity = identityOf(ownSources)

func identityOf(sources fs.FS) string {
	names, _ := fs.Glob(sources, "*.go")
	sort.Strings(names)

	digest := sha256.New()
	for _, name := range names {
		if strings.HasSuffix(name, "_test.go") {
			continue
		}
		text, _ := fs.ReadFile(sources, name)
		digest.Write([]byte(name))
		digest.Write([]byte{0})
		digest.Write(text)
	}

	return hex.EncodeToString(digest.Sum(nil))
}

// keptLines are the Go file lines an earlier run wrote, kept on disk with their path left out under a key of the file's own text, its package's import path and the shape of everything its types can see: the skeleton of each file of its package and of every package of its module it imports, near or far, each file with its function bodies cut out. A change inside a body keeps every line; a changed declaration renews the lines of its own package and of the packages that import it, and no other. A file's shape is itself kept under the fingerprint of its text, so a file that did not change is never parsed to key it. A line is touched when read, so pruning by age spares what is used.
type keptLines struct {
	folder   string
	texts    map[string][]byte
	shapes   map[string]shape
	closures map[string]string
	modules  map[string]Module
	pins     map[string]string
}

// shape is what a file shows the files that import its package: the fingerprint of its text with the function bodies cut out, and the packages it imports.
type shape struct {
	Skeleton string   `json:"skeleton"`
	Imports  []string `json:"imports"`
}

func keptLinesFor() (*keptLines, bool) {
	folder, err := bundle.TreesFolder("go")
	if err != nil {
		return nil, false
	}

	return &keptLines{folder: folder, texts: map[string][]byte{}, shapes: map[string]shape{}, closures: map[string]string{}, modules: map[string]Module{}, pins: map[string]string{}}, true
}

func (k *keptLines) allOf(one Package, context func(string) bool) ([]*contract.File, bool) {
	var files []*contract.File
	for _, path := range one.Files {
		file, held := k.read(k.keyOf(path, one), path, context(path))
		if !held {
			return nil, false
		}
		files = append(files, file)
	}

	return files, len(files) > 0
}

func (k *keptLines) keyOf(path string, one Package) string {
	source, err := k.text(path)
	if err != nil {
		return ""
	}

	digest := sha256.New()
	for _, part := range []string{Version(), bridgeIdentity, one.Path, k.closureOf(one.Folder, one.Files), k.pinsOf(one.Folder)} {
		digest.Write([]byte(part))
		digest.Write([]byte{0})
	}
	digest.Write(source)

	return hex.EncodeToString(digest.Sum(nil))
}

func (k *keptLines) text(path string) ([]byte, error) {
	if held, read := k.texts[path]; read {
		return held, nil
	}

	source, err := read(path)
	if err == nil {
		k.texts[path] = source
	}

	return source, err
}

func (k *keptLines) closureOf(folder string, files []string) string {
	var parts, imported []string
	for _, path := range files {
		held := k.shapeOf(path)
		parts = append(parts, held.Skeleton)
		imported = append(imported, held.Imports...)
	}
	sort.Strings(parts)

	module, inModule := moduleOf(folder, k.modules)
	sort.Strings(imported)
	for at, path := range imported {
		if !inModule || (at > 0 && imported[at-1] == path) {
			continue
		}
		if path == module.Path || strings.HasPrefix(path, module.Path+"/") {
			parts = append(parts, path+"="+k.closureOfImport(module, path))
		}
	}

	digest := sha256.New()
	for _, part := range parts {
		digest.Write([]byte(part))
		digest.Write([]byte{0})
	}

	return hex.EncodeToString(digest.Sum(nil))
}

func (k *keptLines) closureOfImport(module Module, path string) string {
	if held, walked := k.closures[path]; walked {
		return held
	}
	k.closures[path] = ""

	folder := filepath.Join(module.Root, filepath.FromSlash(strings.TrimPrefix(strings.TrimPrefix(path, module.Path), "/")))
	entries, _ := os.ReadDir(folder)
	var files []string
	for _, entry := range entries {
		name := entry.Name()
		if !entry.IsDir() && strings.HasSuffix(name, ".go") && !strings.HasSuffix(name, "_test.go") && builds(folder, name) {
			files = append(files, filepath.Join(folder, name))
		}
	}

	closure := k.closureOf(folder, files)
	k.closures[path] = closure

	return closure
}

func (k *keptLines) shapeOf(path string) shape {
	source, err := k.text(path)
	if err != nil {
		return shape{}
	}
	print := fingerprint(source)
	if held, read := k.shapes[print]; read {
		return held
	}

	entry := filepath.Join(k.folder, "shape-"+print)
	var held shape
	if stored, err := os.ReadFile(entry); err == nil && json.Unmarshal(stored, &held) == nil {
		k.shapes[print] = held

		return held
	}

	held = shaped(path, source)
	k.shapes[print] = held
	if written, err := json.Marshal(held); err == nil {
		stored(k.folder, entry, written)
	}

	return held
}

func (k *keptLines) pinsOf(folder string) string {
	module, found := moduleOf(folder, k.modules)
	if !found {
		return ""
	}
	if held, read := k.pins[module.Root]; read {
		return held
	}

	digest := sha256.New()
	for _, file := range []string{"go.mod", "go.sum"} {
		if text, err := os.ReadFile(filepath.Join(module.Root, file)); err == nil {
			digest.Write([]byte(fingerprint(text)))
		}
	}
	k.pins[module.Root] = hex.EncodeToString(digest.Sum(nil))

	return k.pins[module.Root]
}

func (k *keptLines) read(key, path string, context bool) (*contract.File, bool) {
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

func (k *keptLines) keep(key string, file *contract.File) {
	pathless := *file
	pathless.Path, pathless.Context = "", false

	written, err := json.Marshal(&pathless)
	if err != nil {
		return
	}

	stored(k.folder, filepath.Join(k.folder, key+".line"), written)
}

func stored(folder, entry string, written []byte) {
	draft, err := os.CreateTemp(folder, ".writing-")
	if err != nil {
		return
	}
	defer os.Remove(draft.Name())

	if _, err := draft.Write(written); err != nil {
		draft.Close()

		return
	}
	draft.Close()
	os.Rename(draft.Name(), entry)
}

func shaped(path string, source []byte) shape {
	fset := token.NewFileSet()
	parsed, _ := parser.ParseFile(fset, path, source, parser.SkipObjectResolution)
	if parsed == nil {
		return shape{Skeleton: fingerprint(source)}
	}

	held := shape{Skeleton: fingerprint(withoutBodies(fset, parsed, source))}
	for _, spec := range parsed.Imports {
		if imported, err := strconv.Unquote(spec.Path.Value); err == nil {
			held.Imports = append(held.Imports, imported)
		}
	}

	return held
}

func fingerprint(text []byte) string {
	sum := sha256.Sum256(text)

	return hex.EncodeToString(sum[:])
}

func withoutBodies(fset *token.FileSet, parsed *ast.File, source []byte) []byte {
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
