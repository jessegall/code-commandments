package engine

import (
	"go/ast"
	"go/parser"
	"go/token"
	"io/fs"
	"slices"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func TestTwoReadersAtTheSameMomentWaitForOneBuild(t *testing.T) {
	codebase := Load(&contract.Stream{Header: contract.Header{Language: contract.Python}})

	var mu sync.Mutex
	built := 0
	started := make(chan struct{})
	build := func(*Codebase) int {
		mu.Lock()
		built++
		mu.Unlock()
		<-started

		return 7
	}

	var readers sync.WaitGroup
	for range 8 {
		readers.Add(1)
		go func() {
			defer readers.Done()
			if held := Analysis(codebase, "the same thing", build); held != 7 {
				t.Errorf("a reader was handed %d, and the build made 7", held)
			}
		}()
	}

	close(started)
	readers.Wait()

	if built != 1 {
		t.Errorf("eight readers at the same moment built the same thing %d times for one codebase", built)
	}
}

func TestABuildThatFailedIsTriedAgainByTheNextReader(t *testing.T) {
	codebase := Load(&contract.Stream{Header: contract.Header{Language: contract.Python}})

	func() {
		defer func() { recover() }()
		Analysis(codebase, "broken once", func(*Codebase) int { panic("the build broke") })
	}()

	if held := Analysis(codebase, "broken once", func(*Codebase) int { return 3 }); held != 3 {
		t.Errorf("the reader after a failed build was handed %d, and its own build made 3", held)
	}
}

func TestAValueHandedInReplacesTheOneKept(t *testing.T) {
	codebase := Load(&contract.Stream{Header: contract.Header{Language: contract.Python}})

	Analysis(codebase, "replaced", func(*Codebase) int { return 1 })
	Keep(codebase, "replaced", 2)

	if held := Analysis(codebase, "replaced", func(*Codebase) int { return 1 }); held != 2 {
		t.Errorf("after a value was handed in the codebase holds %d, and 2 was handed in", held)
	}
}

func TestACodebaseIsOnlyMadeWhereItIsLoadedAndInTheFunnel(t *testing.T) {
	parsed, err := parser.ParseDir(token.NewFileSet(), ".", func(file fs.FileInfo) bool {
		return !strings.HasSuffix(file.Name(), "_test.go")
	}, 0)
	if err != nil {
		t.Fatal(err)
	}

	var making, narrowing []string

	for _, file := range parsed["engine"].Files {
		for _, declared := range file.Decls {
			function, is := declared.(*ast.FuncDecl)
			if !is || function.Body == nil {
				continue
			}

			ast.Inspect(function.Body, func(node ast.Node) bool {
				if literal, is := node.(*ast.CompositeLit); is {
					if named, is := literal.Type.(*ast.Ident); is && named.Name == "Codebase" {
						making = append(making, function.Name.Name)
					}
				}
				if call, is := node.(*ast.CallExpr); is {
					if selected, is := call.Fun.(*ast.SelectorExpr); is && selected.Sel.Name == "narrowedTo" {
						narrowing = append(narrowing, function.Name.Name)
					}
				}

				return true
			})
		}
	}

	slices.Sort(making)

	if !slices.Equal(making, []string{"built", "narrowedTo"}) {
		t.Errorf("a codebase is made in %v, and only loading (built) and the kept part of a language (narrowedTo) may make one: "+
			"a view built anywhere else is built again for every reader", making)
	}
	if !slices.Equal(narrowing, []string{"Of"}) {
		t.Errorf("a codebase is narrowed to a language in %v, and only Of may, through Analysis, which builds it once", narrowing)
	}
}
