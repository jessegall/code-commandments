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
		if err := os.MkdirAll(filepath.Dir(filepath.Join(folder, name)), 0o755); err != nil {
			t.Fatal(err)
		}
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
		if file.Root.Kind != "file" || file.Root.Span.End != len(source) {
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
	for _, child := range file.Root.Children() {
		kinds = append(kinds, child.Kind)
	}
	if !slices.Equal(kinds, []string{"module"}) {
		t.Fatalf("the root holds %v", kinds)
	}
	if class := find(file, func(n *contract.Node) bool { return n.Kind == "type" }); class == nil || class.Symbol != `Shop\Cart` {
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

func TestAWrittenFileResolvesWhatAFileOnlyReadDeclares(t *testing.T) {
	folder := written(t, map[string]string{
		"Category.php": "<?php\nnamespace App;\nenum Category: string {\n    case Food = 'food';\n}\n",
		"Shelf.php":    "<?php\nnamespace App;\nfinal class Shelf {\n    public function aisle(string $kind): int {\n        return $kind === Category::Food->value ? 1 : 9;\n    }\n}\n",
	})
	stream := bridged(t, "--write="+filepath.Join(folder, "Shelf.php"), folder)
	for _, file := range stream.Files {
		if !strings.HasSuffix(file.Path, "Shelf.php") {
			continue
		}
		food := find(file, func(node *contract.Node) bool { return named(node, "Food") })
		if food == nil || food.Refers != `App\Category::Food` {
			t.Fatalf("the case Food in the written Shelf.php resolves to %q, not to App\\Category::Food declared in the read Category.php", food.Refers)
		}

		return
	}
	t.Fatal("Shelf.php was not written")
}

func TestAnAnonymousClassGivesItsMembersItsOwnSymbol(t *testing.T) {
	folder := written(t, map[string]string{"Outer.php": "<?php\nnamespace Shop;\nclass Outer { public function make() { return new class { public function inner() {} }; } }\n"})
	file := bridged(t, folder).Files[0]
	inner := find(file, func(n *contract.Node) bool { return n.Kind == "function" && named(n, "inner") })
	if inner == nil || !strings.HasPrefix(inner.Symbol, `Shop\class@anonymous@Outer.php:`) || !strings.HasSuffix(inner.Symbol, "::inner") {
		t.Errorf("the anonymous class's method is %v, not a member of the anonymous class", inner)
	}
	anonymous := find(file, func(n *contract.Node) bool { return n.Kind == "type" && n.Facts != nil && n.Name == "" })
	if anonymous == nil || anonymous.Symbol == "" {
		t.Errorf("the anonymous class has no symbol of its own: %v", anonymous)
	}
}

func TestAClassTheProjectsLoaderFailsOnIsSaidOnTheStream(t *testing.T) {
	folder := written(t, map[string]string{"Cart.php": "<?php\nnamespace Shop;\nfinal class Cart extends \\Acme\\Broken {}\n"})
	if err := os.MkdirAll(filepath.Join(folder, "vendor"), 0o755); err != nil {
		t.Fatal(err)
	}
	loader := "<?php\nspl_autoload_register(function (string $class): void {\n    if ($class === 'Acme\\\\Broken') {\n        throw new RuntimeException('the file is not there');\n    }\n});\n"
	if err := os.WriteFile(filepath.Join(folder, "vendor", "autoload.php"), []byte(loader), 0o644); err != nil {
		t.Fatal(err)
	}
	stream := bridged(t, folder)
	if stream.Program == nil || len(stream.Program.Unreadable) != 1 {
		t.Fatalf("the program line says %+v", stream.Program)
	}
	if unreadable := stream.Program.Unreadable[0]; unreadable.Symbol != `Acme\Broken` || unreadable.Reason != "the file is not there" {
		t.Errorf("the unreadable class is %+v", unreadable)
	}
}

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

func TestAnAutoloaderThatDoesNotLoadHereLeavesTheStreamWhole(t *testing.T) {
	folder := written(t, map[string]string{
		"vendor/autoload.php": "<?php\nrequire __DIR__ . '/../../elsewhere/helpers.php';\n",
		"src/Blank.php":       "<?php\nnamespace Shop;\nfinal class Blank implements \\Stringable {\n    public function __toString(): string { return ''; }\n}\n",
	})
	stream := bridged(t, filepath.Join(folder, "src"))
	if len(stream.Files) != 1 || stream.Program == nil {
		t.Fatalf("the stream holds %d files and outside symbols %v", len(stream.Files), stream.Program)
	}
	if !slices.ContainsFunc(stream.Program.Symbols, func(symbol contract.OutsideSymbol) bool { return symbol.Symbol == "Stringable" }) {
		t.Errorf("PHP's own Stringable is not listed: %v", stream.Program.Symbols)
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
	command.Stdin = strings.NewReader(`{"paths": ["` + a + `"]}` + "\n" + `{"paths": ["` + a + `", "` + b + `"], "write": ["` + b + `"]}` + "\n")
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
	if len(streams) != 2 || len(streams[0].Files) != 1 || len(streams[1].Files) != 1 || filepath.Base(streams[1].Files[0].Path) != "B.php" {
		t.Fatalf("two requests answered with %d streams:\n%s", len(streams), out)
	}
}

func TestAProbeForAClassTheParserLacksIsNoFailure(t *testing.T) {
	needsPHP(t)
	command, err := Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	loader := filepath.Join(filepath.Dir(command[len(command)-1]), "parser", "autoload.php")
	out, err := exec.Command("php", "-r", `require $argv[1]; var_dump(class_exists('PhpParser\Node\Stmt\Throw_'));`, loader).CombinedOutput()
	if err != nil || strings.TrimSpace(string(out)) != "bool(false)" {
		t.Errorf("the probe answered %q (%v)", out, err)
	}
}

func TestAProjectWithUninstalledDependenciesSaysWhyNoPackageIsRead(t *testing.T) {
	uninstalled := written(t, map[string]string{
		"composer.json":  `{"name":"acme/app","require":{"acme/excel":"^3.1"}}`,
		"src/Export.php": "<?php\nnamespace App;\nfinal class Export implements \\Acme\\Excel\\WithProperties {\n    public function properties(): array { return ['title' => 'a', 'company' => 'b']; }\n}\n",
	})
	stream := bridged(t, filepath.Join(uninstalled, "src"))
	if stream.Program == nil || stream.Program.Loader == "" {
		t.Fatalf("a project with dependencies it has not installed says nothing of its loader: %v", stream.Program)
	}
	if !strings.Contains(stream.Program.Loader, "composer install") {
		t.Errorf("the reason does not say what to do: %q", stream.Program.Loader)
	}

	bare := written(t, map[string]string{
		"composer.json":  `{"name":"acme/app","autoload":{"psr-4":{"App\\":"src/"}}}`,
		"src/Export.php": "<?php\nnamespace App;\nfinal class Export {}\n",
	})
	if program := bridged(t, filepath.Join(bare, "src")).Program; program != nil && program.Loader != "" {
		t.Errorf("a project that declares no dependency is told its loader was not used: %q", program.Loader)
	}
}

// A class a file of the scan declares is never loaded through the project's autoloader, written or not: the scan holds
// it, so the bridge has no reason to run its file, and a read of one file in a large project loads none of the rest.
func TestAClassAFileOfTheScanDeclaresIsNeverLoaded(t *testing.T) {
	composer, err := exec.LookPath("composer")
	if err != nil {
		t.Skip("no composer here to write the project's autoloader")
	}
	project := written(t, map[string]string{
		"composer.json": `{"name":"acme/app","autoload":{"psr-4":{"App\\":"src/"}}}`,
		"src/Order.php": "<?php\nnamespace App;\ntouch(__DIR__ . '/../loaded');\nfinal class Order {}\n",
		"src/Cart.php":  "<?php\nnamespace App;\nfinal class Cart {\n    public function __construct(public Order $order) {}\n}\n",
	})
	if out, err := exec.Command(composer, "dump-autoload", "--working-dir="+project, "--quiet").CombinedOutput(); err != nil {
		t.Fatalf("composer dump-autoload: %v %s", err, out)
	}
	cart, order := filepath.Join(project, "src", "Cart.php"), filepath.Join(project, "src", "Order.php")

	stream := bridged(t, cart, order, "--write="+cart)
	if _, err := os.Stat(filepath.Join(project, "loaded")); err == nil {
		t.Error("the bridge loaded Order.php, a file of the scan it was not asked to write")
	}
	if stream.Program != nil && slices.ContainsFunc(stream.Program.Symbols, func(symbol contract.OutsideSymbol) bool { return symbol.Symbol == `App\Order` }) {
		t.Errorf("Order, which a file of the scan declares, is named outside it")
	}
}

func named(node *contract.Node, name string) bool {
	return node.Facts != nil && node.Name == name
}

func TestADocReferenceToSelfOrParentResolvesToTheClassItIsWrittenOn(t *testing.T) {
	folder := written(t, map[string]string{"Ledger.php": `<?php
namespace App;
class Book { public function close(): void {} }
/**
 * Balanced by {@see self::balance()}.
 */
class Ledger extends Book {
    public function balance(): int { return 0; }
    /**
     * Ends with {@see parent::close()} and {@see static::balance()}.
     */
    public function close(): void {}
}
`})
	var resolved []string
	for _, comment := range bridged(t, folder).Files[0].Comments {
		for _, ref := range comment.Refs {
			resolved = append(resolved, ref.Symbol)
		}
	}
	wanted := []string{`App\Ledger::balance`, `App\Book::close`, `App\Ledger::balance`}
	if strings.Join(resolved, " ") != strings.Join(wanted, " ") {
		t.Errorf("the doc references resolve to %q, not %q", resolved, wanted)
	}
}

func TestADocReferenceIsReadAsAMemberOfItsTypeAFileOrAnAbsoluteName(t *testing.T) {
	folder := written(t, map[string]string{"Accounts.php": `<?php
namespace App\Users;
class Accounts {
    /**
     * Mirrors {@see changeEmail} and {@link App\Users\Accounts::rename()}, as Accounts.php says, through {@link debug_backtrace()}.
     */
    public function updateEmail(): void {}
    public function changeEmail(): void {}
    public function rename(): void {}
}
`})
	var read []string
	for _, comment := range bridged(t, folder).Files[0].Comments {
		for _, ref := range comment.Refs {
			ownedElsewhere := ref.OwnedHere != nil && !*ref.OwnedHere
			read = append(read, ref.Text+"="+ref.Symbol+map[bool]string{true: " (owned elsewhere)", false: ""}[ownedElsewhere])
		}
	}
	wanted := []string{`changeEmail=App\Users\Accounts::changeEmail`, `App\Users\Accounts::rename=App\Users\Accounts::rename`, `debug_backtrace= (owned elsewhere)`}
	if strings.Join(read, " | ") != strings.Join(wanted, " | ") {
		t.Errorf("the doc references read as %q, not %q", read, wanted)
	}
}

func TestAMethodOverridingATraitMethodItsParentUsesIsBoundAndOneReplacingItsOwnTraitIsNot(t *testing.T) {
	folder := written(t, map[string]string{"Tables.php": `<?php
namespace App;
trait InteractsWithTable { public function reorderTable(array $order): void {} }
class RelationManager { use InteractsWithTable; }
class AchievementsManager extends RelationManager { public function reorderTable(array $order): void {} }
trait Sorts { public function sort(array $rows): array { return $rows; } }
class Board { use Sorts; public function sort(array $rows): array { return $rows; } }
`})
	obligations := map[string][]string{}
	for _, node := range bridged(t, folder).Files[0].Nodes() {
		if node.Kind == "function" && node.Facts != nil && node.Symbol != "" {
			for _, each := range node.Obligations {
				obligations[node.Symbol] = append(obligations[node.Symbol], each.Symbol)
			}
		}
	}
	if got := obligations[`App\AchievementsManager::reorderTable`]; !slices.Equal(got, []string{`App\InteractsWithTable::reorderTable`}) {
		t.Errorf("an override of the trait method its parent uses is bound by %v, not by the trait's method", got)
	}
	if got := obligations[`App\Board::sort`]; len(got) != 0 {
		t.Errorf("a method replacing its own trait's method is bound by %v", got)
	}
}

func TestAVariableAnInlineVarDocumentsIsTypedAsTheClassItNames(t *testing.T) {
	folder := written(t, map[string]string{"Till.php": `<?php
namespace App;
use App\Stock\Order;
final class Till {
    public function close(array $found): int {
        /** @var Order $order */
        $order = $this->find();
        /** @var Order */
        $other = $this->find();
        return $order->total() + $other->total();
    }
    private function find() { return null; }
}
`, "Stock/Order.php": "<?php\nnamespace App\\Stock;\nfinal class Order { public function total(): int { return 0; } }\n"})
	var targets []string
	for _, file := range bridged(t, folder).Files {
		for _, node := range file.Nodes() {
			if node.Target != nil && strings.HasSuffix(node.Target.Symbol, "::total") {
				targets = append(targets, node.Target.Symbol)
			}
		}
	}
	if !slices.Equal(targets, []string{`App\Stock\Order::total`, `App\Stock\Order::total`}) {
		t.Errorf("the calls on the documented variables resolve to %v, not Order::total twice", targets)
	}
}

func TestAVariableKeepsATypeAfterABranchingStatementOnlyWhenEveryPathAgrees(t *testing.T) {
	folder := written(t, map[string]string{"Shift.php": `<?php
namespace App;
final class Clock { public function now(): int { return 0; } }
final class Calendar { public function now(): int { return 1; } }
final class Shift {
    public function start(bool $late): int {
        try {
            $source = new Clock();
        } catch (\Throwable $failure) {
            $source = new Calendar();
        }
        if ($late) {
            $fallback = new Clock();
        } else {
            $fallback = new Clock();
        }
        return $source->now() + $fallback->now();
    }
}
`})
	var targets []string
	for _, node := range bridged(t, folder).Files[0].Nodes() {
		if node.Kind == "call" && node.Span.Line == 17 {
			if node.Target != nil {
				targets = append(targets, node.Target.Symbol)
			} else {
				targets = append(targets, "unresolved")
			}
		}
	}
	if !slices.Equal(targets, []string{"unresolved", `App\Clock::now`}) {
		t.Errorf("the calls after the try and the if resolve to %v, not unresolved (two paths disagree) and Clock::now (both agree)", targets)
	}
}

func TestACallToAClassTheAutoloaderLoadsByItsWrittenCaseIsResolved(t *testing.T) {
	folder := written(t, map[string]string{
		"vendor/autoload.php": `<?php
spl_autoload_register(function (string $class): void {
    if (! str_starts_with($class, 'Framework\\')) {
        return;
    }
    $path = __DIR__.'/src/'.str_replace('\\', '/', $class).'.php';
    if (is_file($path)) {
        require $path;
    }
});
`,
		"vendor/src/Framework/Validation/Failure.php": "<?php\nnamespace Framework\\Validation;\nclass Failure extends \\RuntimeException { public static function withMessages(array $messages): static { return new static(); } }\n",
		"app/Form.php": "<?php\nnamespace App;\nuse Framework\\Validation\\Failure;\nclass Form { public function reject(): void { throw Failure::withMessages(['name' => 'taken']); } }\n",
	})
	var targets []string
	for _, file := range bridged(t, filepath.Join(folder, "app")).Files {
		for _, node := range file.Nodes() {
			if node.Target != nil {
				targets = append(targets, node.Target.Symbol)
			}
		}
	}
	if !slices.Contains(targets, `Framework\Validation\Failure::withMessages`) {
		t.Errorf("the static call to a class the autoloader loads reaches %q, not Framework\\Validation\\Failure::withMessages", targets)
	}
}

func TestAMethodATraitDeclaresAbstractIsBoundButOneItWritesIsNot(t *testing.T) {
	folder := written(t, map[string]string{"Report.php": `<?php
namespace App;
trait Exports {
    abstract public function rows(): array;
    public function title(): string { return ''; }
}
class Report {
    use Exports;
    public function rows(): array { return []; }
    public function title(): string { return 'Report'; }
}
`})
	bound := map[string]int{}
	for _, node := range bridged(t, folder).Files[0].Nodes() {
		if node.Kind == "function" && node.Name != "" {
			bound[node.Name] += len(node.Obligations)
		}
	}
	if bound["rows"] != 1 || bound["title"] != 0 {
		t.Errorf("the obligations are %v: rows answers the trait's abstract method once, title overrides nothing it must keep", bound)
	}
}

func TestAFileTheParserFailsOnIsWrittenUnreadAndTheRestIsRead(t *testing.T) {
	failing, err := os.ReadFile("../../bridge/php/parser/lib/PhpParser/ParserAbstract.php")
	if err != nil {
		t.Fatal(err)
	}
	folder := written(t, map[string]string{
		"ParserAbstract.php": string(failing),
		"Cart.php":           "<?php\nnamespace Shop;\nfinal class Cart {}\n",
	})
	files := map[string]*contract.File{}
	for _, file := range bridged(t, folder).Files {
		files[filepath.Base(file.Path)] = file
	}
	if cart := files["Cart.php"]; cart == nil || cart.Errors != 0 {
		t.Fatalf("the file beside the one the parser fails on is not read: %v", files)
	}
	if unread := files["ParserAbstract.php"]; unread == nil || unread.Resolver == nil || unread.Resolver.Ran || unread.Errors == 0 || unread.Unread == "" {
		t.Errorf("the file the parser fails on is not written as unread: %+v", unread)
	}
}

func TestACallOnWhatALibraryMethodDocumentsItReturnsIsResolved(t *testing.T) {
	folder := written(t, map[string]string{
		"vendor/autoload.php": `<?php
spl_autoload_register(function (string $class): void {
    if (! str_starts_with($class, 'Framework\\')) {
        return;
    }
    require __DIR__.'/src/'.str_replace('\\', '/', $class).'.php';
});
`,
		"vendor/src/Framework/Query.php":  "<?php\nnamespace Framework;\nclass Query {\n    /** @return $this */\n    public function where(string $column) {}\n    public function first(): ?object { return null; }\n}\n",
		"vendor/src/Framework/Record.php": "<?php\nnamespace Framework;\nabstract class Record {\n    /** @return \\Framework\\Query<static> */\n    public static function query() {}\n}\n",
		"app/Order.php":                   "<?php\nnamespace App;\nuse Framework\\Record;\nfinal class Order extends Record {}\nfinal class Orders { public function open(): ?object { return Order::query()->where('open')->first(); } }\n",
	})
	var targets []string
	for _, file := range bridged(t, filepath.Join(folder, "app")).Files {
		for _, node := range file.Nodes() {
			if node.Target != nil {
				targets = append(targets, node.Target.Symbol)
			}
		}
	}
	for _, wanted := range []string{`Framework\Record::query`, `Framework\Query::where`, `Framework\Query::first`} {
		if !slices.Contains(targets, wanted) {
			t.Errorf("the chain through documented returns reaches %q, and %s is not among them", targets, wanted)
		}
	}
}

func TestAParameterIsTypedAsTheSubtypeItsDocCommentDocuments(t *testing.T) {
	folder := written(t, map[string]string{
		"app/Setting.php": "<?php\nnamespace App\\Settings;\nabstract class Setting { public function name(): string { return ''; } }\nfinal class AutoSync extends Setting { public function every(): int { return 1; } }\nfinal class Unrelated { public function every(): int { return 1; } }\n",
		"app/Sync.php": "<?php\nnamespace App;\nuse App\\Settings\\AutoSync;\nuse App\\Settings\\Setting;\nuse App\\Settings\\Unrelated;\nfinal class Sync {\n" +
			"    /** @param  AutoSync  $setting */\n    public function run(Setting $setting): int { return $setting->every(); }\n" +
			"    /** @param  Unrelated  $setting */\n    public function other(Setting $setting): string { return $setting->name(); }\n}\n",
	})
	var targets []string
	for _, file := range bridged(t, filepath.Join(folder, "app")).Files {
		for _, node := range file.Nodes() {
			if node.Target != nil {
				targets = append(targets, node.Target.Symbol)
			}
		}
	}
	for _, wanted := range []string{`App\Settings\AutoSync::every`, `App\Settings\Setting::name`} {
		if !slices.Contains(targets, wanted) {
			t.Errorf("the calls reach %q, and %s is not among them", targets, wanted)
		}
	}
	if slices.Contains(targets, `App\Settings\Unrelated::name`) {
		t.Errorf("a documented class that is no subtype of the written one narrowed the parameter: %q", targets)
	}
}

func TestAPropertyAClassDocumentsIsTypedAsTheClassItNames(t *testing.T) {
	folder := written(t, map[string]string{
		"app/Money.php": "<?php\nnamespace App\\Values;\nfinal class Money { public function cents(): int { return 0; } }\n",
		"app/Order.php": "<?php\nnamespace App;\nuse App\\Values\\Money;\n/**\n * @property Money $total\n * @property int $count\n */\nclass Order { public function __get(string $name): mixed { return null; } }\n" +
			"final class Till { public function due(Order $order): int { return $order->total->cents(); } }\n",
	})
	var targets []string
	for _, file := range bridged(t, filepath.Join(folder, "app")).Files {
		for _, node := range file.Nodes() {
			if node.Target != nil {
				targets = append(targets, node.Target.Symbol)
			}
		}
	}
	if !slices.Contains(targets, `App\Values\Money::cents`) {
		t.Errorf("a call on a documented property reaches %q, and App\\Values\\Money::cents is not among them", targets)
	}
}

func TestAForeachValueIsTypedAsTheElementItsListIsDocumentedToHold(t *testing.T) {
	folder := written(t, map[string]string{
		"app/Zone.php": "<?php\nnamespace App\\Delivery;\nfinal class Zone { public function code(): string { return ''; } }\n",
		"app/Profile.php": "<?php\nnamespace App;\nuse App\\Delivery\\Zone;\nfinal class Profile {\n" +
			"    /** @param  array<int, Zone>  $zones */\n    public function __construct(public readonly array $zones = []) {}\n" +
			"    public function codes(): array { $codes = []; foreach ($this->zones as $zone) { $codes[] = $zone->code(); } return $codes; }\n" +
			"    /** @param  list<Zone>  $more */\n    public function first(array $more): string { foreach ($more as $each) { return $each->code(); } return ''; }\n}\n",
	})
	count := 0
	for _, file := range bridged(t, filepath.Join(folder, "app")).Files {
		for _, node := range file.Nodes() {
			if node.Target != nil && node.Target.Symbol == `App\Delivery\Zone::code` {
				count++
			}
		}
	}
	if count != 2 {
		t.Errorf("%d calls on a documented list's element reach Zone::code, and 2 is right", count)
	}
}
