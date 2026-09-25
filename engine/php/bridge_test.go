package php

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

const fixture = "../../tests/Fixtures/backend"

func bridged(t *testing.T, arguments ...string) *contract.Stream {
	t.Helper()
	if _, err := exec.LookPath("php"); err != nil {
		t.Skip("php is not on PATH")
	}
	stream, err := Here().Stream(arguments...)
	if err != nil {
		t.Fatal(err)
	}

	return stream
}

func written(t *testing.T, files map[string]string) string {
	t.Helper()
	folder, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range files {
		if err := os.WriteFile(filepath.Join(folder, name), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return folder
}

func find(file *contract.File, check func(*contract.Node) bool) *contract.Node {
	for _, node := range file.Nodes() {
		if check(node) {
			return node
		}
	}

	return nil
}

func TestTheShopStreamsWholeAndPointsIntoItsSources(t *testing.T) {
	stream := bridged(t, fixture)
	if len(stream.Files) < 500 || stream.Trailer.Files != len(stream.Files) {
		t.Fatalf("%d files streamed, the trailer counts %d", len(stream.Files), stream.Trailer.Files)
	}
	for _, file := range stream.Files {
		source, err := os.ReadFile(file.Path)
		if err != nil {
			t.Fatal(err)
		}
		if file.Errors != 0 {
			t.Errorf("%s parsed with %d errors", file.Path, file.Errors)
		}
		if file.Root.Kind != "File" || file.Root.Span.End != len(source) {
			t.Errorf("%s's root is %s over %v, not the whole file", file.Path, file.Root.Kind, file.Root.Span)
		}
		for _, node := range file.Nodes() {
			parent, ok := node.Parent()
			if ok && (node.Span.Start < parent.Span.Start || node.Span.End > parent.Span.End) {
				t.Errorf("%s: node %d %v lies outside its parent %v", file.Path, node.ID, node.Span, parent.Span)
			}
		}
		for _, comment := range file.Comments {
			if string(source[comment.Span.Start:comment.Span.End]) != comment.Text {
				t.Errorf("%s: comment %d's span does not hold its text", file.Path, comment.ID)
			}
		}
	}
}

func TestEveryTopLevelStatementLivesUnderTheRoot(t *testing.T) {
	folder := written(t, map[string]string{"Cart.php": "<?php\n\ndeclare(strict_types=1);\n\nnamespace Shop;\n\nfinal class Cart {}\n"})
	file := bridged(t, folder).Files[0]
	var kinds []string
	for _, child := range file.Root.Children {
		kinds = append(kinds, child.Kind)
	}
	if !slices.Equal(kinds, []string{"Stmt_Declare", "Stmt_Namespace"}) {
		t.Fatalf("the root holds %v", kinds)
	}
	if class := find(file, func(n *contract.Node) bool { return n.Kind == "Stmt_Class" }); class == nil || class.Symbol != `Shop\Cart` {
		t.Fatalf("the class is %+v", class)
	}
}

func TestAPartlyParsedFileIsStillWritten(t *testing.T) {
	folder := written(t, map[string]string{"Broken.php": "<?php\n\nfunction ok() { return 1; }\n\nfunction broken( { }\n"})
	file := bridged(t, folder).Files[0]
	if file.Errors == 0 {
		t.Fatal("a syntax error counts no errors")
	}
}

func TestAFileOutsideTheWriteSetOnlyInforms(t *testing.T) {
	folder := written(t, map[string]string{"Judged.php": "<?php class Judged {}\n", "Read.php": "<?php class Read {}\n"})
	stream := bridged(t, "--write="+filepath.Join(folder, "Judged.php"), folder)
	for _, file := range stream.Files {
		if context := strings.HasSuffix(file.Path, "Read.php"); file.Context != context {
			t.Errorf("%s context is %v", file.Path, file.Context)
		}
	}
}

func TestNamesReferToWhatTheImportsResolve(t *testing.T) {
	folder := written(t, map[string]string{"Uses.php": `<?php
namespace Shop;

use Money\Money as Cash;
use Money\{Currency, Parser\Decimal};
use function Money\format;

final class Till
{
    public function __construct(private readonly Cash $cash) {}

    public function sum(Currency $in): ?Decimal
    {
        return format(new Cash(), self::class);
    }
}
`})
	file := bridged(t, folder).Files[0]
	refers := map[string]bool{}
	for _, node := range file.Nodes() {
		if node.Refers != "" {
			refers[node.Refers] = true
		}
	}
	for _, want := range []string{`Money\Money`, `Money\Currency`, `Money\Parser\Decimal`, `Money\format()`} {
		if !refers[want] {
			t.Errorf("nothing refers to %s; refers: %v", want, refers)
		}
	}
	returns := find(file, func(n *contract.Node) bool { return n.Kind == "Stmt_ClassMethod" && n.Name == "sum" }).Returns
	if returns.Text != `?\Money\Parser\Decimal` || returns.Name != `Money\Parser\Decimal` || !returns.Nullable {
		t.Errorf("sum returns %+v", returns)
	}
	promoted := find(file, func(n *contract.Node) bool { return n.Kind == "Param" && n.Name == "cash" })
	if promoted.Symbol != `Shop\Till::$cash` || promoted.Declared.Name != `Money\Money` {
		t.Errorf("the promoted parameter is %+v", promoted)
	}
}

func TestAnAnonymousClassLendsItsMembersNoSymbol(t *testing.T) {
	folder := written(t, map[string]string{"Outer.php": "<?php\nnamespace Shop;\nclass Outer { public function make() { return new class { public function inner() {} }; } }\n"})
	file := bridged(t, folder).Files[0]
	if inner := find(file, func(n *contract.Node) bool { return n.Kind == "Stmt_ClassMethod" && n.Name == "inner" }); inner.Symbol != "" {
		t.Errorf("the anonymous class's method is %s", inner.Symbol)
	}
}

func TestOutsideSymbolsAreClosed(t *testing.T) {
	folder := written(t, map[string]string{"Uses.php": "<?php\nnamespace Shop;\nfinal class Ask extends \\PhpParser\\Node\\Expr\\MethodCall {}\n"})
	stream := bridged(t, "--autoload=../../vendor/autoload.php", folder)
	if stream.Program == nil {
		t.Fatal("no outside symbols")
	}
	listed := map[string]bool{}
	for _, symbol := range stream.Program.Symbols {
		listed[symbol.Symbol] = true
	}
	for _, want := range []string{`PhpParser\Node\Expr\MethodCall`, `PhpParser\Node\Expr\CallLike`, `PhpParser\Node\Expr`, `PhpParser\NodeAbstract`, `PhpParser\Node`, `PhpParser\Node\Identifier`} {
		if !listed[want] {
			t.Errorf("%s is not listed", want)
		}
	}
	if listed[`Shop\Ask`] {
		t.Error("a scanned class is listed as outside")
	}
}

func TestServingAnswersEveryRequestWithAWholeStream(t *testing.T) {
	if _, err := exec.LookPath("php"); err != nil {
		t.Skip("php is not on PATH")
	}
	folder := written(t, map[string]string{"A.php": "<?php class A {}\n", "B.php": "<?php class B {}\n"})
	a, b := filepath.Join(folder, "A.php"), filepath.Join(folder, "B.php")
	command := exec.Command("php", Here().Script, "--serve")
	command.Stdin = strings.NewReader(`{"paths": ["` + a + `"]}` + "\n" + `{"paths": ["` + folder + `"], "write": ["` + b + `"]}` + "\n")
	out, err := command.Output()
	if err != nil {
		t.Fatal(err)
	}
	var streams []*contract.Stream
	var answer strings.Builder
	for _, line := range strings.SplitAfter(string(out), "\n") {
		answer.WriteString(line)
		if strings.HasPrefix(line, `{"trailer"`) {
			stream, err := contract.ReadAll(strings.NewReader(answer.String()))
			if err != nil {
				t.Fatal(err)
			}
			streams = append(streams, stream)
			answer.Reset()
		}
	}
	if len(streams) != 2 || len(streams[0].Files) != 1 || len(streams[1].Files) != 2 || !streams[1].Files[0].Context {
		t.Fatalf("two requests answered with %d streams:\n%s", len(streams), out)
	}
}
