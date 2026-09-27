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
	needsPHP(t)
	stream, err := Here().Stream(arguments...)
	if err != nil {
		t.Fatal(err)
	}

	return stream
}

// needsPHP fails the test when php is missing: this repository's tests run the PHP bridge, and a test that skips
// without it passes having proven nothing.
func needsPHP(t *testing.T) {
	t.Helper()
	if _, err := exec.LookPath("php"); err != nil {
		t.Fatal("these tests run the PHP bridge, and php is not on PATH: install PHP 8.4+ and run composer install")
	}
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

// TestAProjectWithNoAutoloaderStillKnowsPhpsOwnClasses holds the bridge to listing a built-in interface a class
// implements when the project has no vendor/autoload.php, so what overrides it is known (the frozen shop's
// BlankText::__toString), and to listing nothing else there: the bridge's own php-parser is no project's.
func TestAProjectWithNoAutoloaderStillKnowsPhpsOwnClasses(t *testing.T) {
	folder := written(t, map[string]string{"Blank.php": "<?php\nnamespace Shop;\nfinal class Blank implements \\Stringable {\n    public function __toString(): string { return ''; }\n}\nfinal class Ask extends \\PhpParser\\Node\\Expr\\MethodCall {}\n"})
	stream := bridged(t, folder)
	if stream.Program == nil {
		t.Fatal("no outside symbols")
	}
	var listed []string
	for _, symbol := range stream.Program.Symbols {
		listed = append(listed, symbol.Symbol)
	}
	if !slices.Contains(listed, "Stringable") || slices.ContainsFunc(listed, func(symbol string) bool { return strings.HasPrefix(symbol, "PhpParser") }) {
		t.Errorf("listed %v", listed)
	}
}

func TestOutsideSymbolsAreClosed(t *testing.T) {
	folder := written(t, map[string]string{"Uses.php": "<?php\nnamespace Shop;\nfinal class Ask extends \\PhpParser\\Node\\Expr\\MethodCall {}\n"})
	stream := bridged(t, "--autoload=../../bridge/php/parser/autoload.php", folder)
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
	needsPHP(t)
	folder := written(t, map[string]string{"A.php": "<?php class A {}\n", "B.php": "<?php class B {}\n"})
	a, b := filepath.Join(folder, "A.php"), filepath.Join(folder, "B.php")
	bridge, err := Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	command := exec.Command(bridge[0], append(bridge[1:], "--serve")...)
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

func TestAScannedCodebaseHasItsTypesAndTargetsFilled(t *testing.T) {
	needsPHP(t)
	folder := written(t, map[string]string{"Till.php": `<?php
namespace Shop;

final class Receipt
{
    public function total(): int { return 1; }
}

final class Till
{
    public function ring(Receipt $receipt): int
    {
        return $receipt->total();
    }
}
`})
	codebase, err := Here().Scan(folder)
	if err != nil {
		t.Fatal(err)
	}
	file := codebase.Files()[0].File
	variable := find(file, func(n *contract.Node) bool {
		parent, _ := n.Parent()

		return n.Kind == "Expr_Variable" && n.Name == "receipt" && parent.Kind == "Expr_MethodCall"
	})
	if variable == nil || variable.Resolved == nil || variable.Resolved.Name != `Shop\Receipt` {
		t.Fatalf("the receiver's resolved type is %+v", variable)
	}
	call := find(file, func(n *contract.Node) bool { return n.Kind == "Expr_MethodCall" })
	if call.Target == nil || call.Target.Symbol != `Shop\Receipt::total()` {
		t.Fatalf("the call's target is %+v", call.Target)
	}
}

// TestAProbeForAClassTheParserLacksIsNoFailure holds the bridge's php-parser loader to answering a class it does not
// have as absent, as composer's does: requiring a file that is not there is fatal, and a scanned project asking after
// a class php-parser dropped (`Stmt\Throw_`) cut the bridge's stream short.
func TestAProbeForAClassTheParserLacksIsNoFailure(t *testing.T) {
	needsPHP(t)
	command, err := Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	loader := filepath.Join(filepath.Dir(command[1]), "parser", "autoload.php")
	out, err := exec.Command("php", "-r", `require $argv[1]; var_dump(class_exists('PhpParser\Node\Stmt\Throw_'));`, loader).CombinedOutput()
	if err != nil || strings.TrimSpace(string(out)) != "bool(false)" {
		t.Errorf("the probe answered %q (%v)", out, err)
	}
}
