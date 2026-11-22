// Package gotree writes the generic tree of contract/CONTRACT.md for Go sources.
package gotree

import (
	"fmt"
	"go/ast"
	"go/parser"
	"go/token"
	"go/types"
	"io"
	"path/filepath"
	"runtime"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/walk"
)

const Name = "go-bridge"

func Version() string {
	return strings.TrimPrefix(runtime.Version(), "go")
}

const contractVersion = 5

func Write(out io.Writer, paths, write []string) error {
	stream, err := Stream(paths, write)
	if err != nil {
		return err
	}

	return contract.Write(out, stream)
}

func Stream(paths, write []string) (*contract.Stream, error) {
	found := GoFiles(paths)
	judged := map[string]bool{}
	for _, path := range walk.RealEach(write) {
		judged[path] = true
	}
	asked := func(path string) bool { return len(judged) == 0 || judged[path] }

	fset := token.NewFileSet()
	packages := PackagesOf(found)
	checked := newChecker(fset, packages)
	written := map[string]bool{}
	var files []*contract.File
	folders := map[string]string{}
	for _, one := range packages {
		if len(one.Files) > 0 {
			folders[one.Path] = filepath.Dir(one.Files[0])
		}
	}

	for _, one := range packages {
		if !slices.ContainsFunc(one.Files, asked) {
			for _, path := range one.Files {
				written[path] = true
			}
			continue
		}

		held, facts := checked.check(one)
		reads := checked.readBy(one, held)
		for _, parsed := range checked.filesOf(one) {
			path := fset.Position(parsed.Pos()).Filename
			if written[path] || !asked(path) {
				written[path] = true
				continue
			}
			written[path] = true

			file, err := fileLine(fset, parsed, path, one.Path, facts, folders)
			if err != nil {
				return nil, err
			}
			file.Reads = slices.DeleteFunc(slices.Clone(reads), func(read string) bool { return read == path })
			files = append(files, file)
		}
	}

	for _, path := range found {
		if written[path] || !asked(path) {
			continue
		}
		source, err := read(path)
		if err != nil {
			continue
		}
		parsed, parseErr := parser.ParseFile(fset, path, source, parser.ParseComments|parser.SkipObjectResolution)
		if parsed == nil {
			return nil, fmt.Errorf("%s: %w", path, parseErr)
		}
		file, err := fileLine(fset, parsed, path, parsed.Name.Name, nil, nil)
		if err != nil {
			return nil, err
		}
		files = append(files, file)
	}

	header := contract.Header{
		Contract: "tree",
		Version:  contractVersion,
		Language: contract.Go,
		Bridge:   contract.Bridge{Name: Name, Version: Version(), Reads: true},
		Roots:    walk.RealEach(paths),
	}

	return contract.Accept(header, files, nil, contract.Trailer{Files: len(files)})
}

func fileLine(fset *token.FileSet, parsed *ast.File, path, module string, facts *types.Info, folders map[string]string) (*contract.File, error) {
	source, err := read(path)
	if err != nil {
		return nil, err
	}

	tree := &writer{fset: fset, source: source, facts: facts, module: module, folders: folders, types: map[types.Type]*contract.Type{}, imported: map[string]bool{}}
	tree.owners = ownersIn(parsed, facts, module)
	root := tree.file(parsed)
	nodes := tree.numbered(root)

	return &contract.File{
		Path:     path,
		Language: contract.Go,
		Errors:   errorsIn(parsed, source),
		Module:   module,
		Resolver: &contract.Resolver{Tool: "go/types", Ran: facts != nil},
		Root:     root,
		Comments: tree.comments(parsed, nodes),
	}, nil
}

func errorsIn(parsed *ast.File, source []byte) int {
	if _, err := parser.ParseFile(token.NewFileSet(), "errors.go", source, parser.SkipObjectResolution); err != nil {
		if list, isList := err.(interface{ Len() int }); isList {
			return list.Len()
		}

		return 1
	}

	return 0
}

func quoted(value any) *contract.Value {
	held, err := contract.ValueOf(value)
	if err != nil {
		return nil
	}

	return held
}

func decimal(text string) string {
	cleaned := strings.ReplaceAll(text, "_", "")
	if number, err := strconv.ParseInt(cleaned, 0, 64); err == nil {
		return strconv.FormatInt(number, 10)
	}
	if number, err := strconv.ParseUint(cleaned, 0, 64); err == nil {
		return strconv.FormatUint(number, 10)
	}

	return text
}
