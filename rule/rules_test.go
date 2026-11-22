package rule_test

import (
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/rules"
	"github.com/jessegall/code-commandments/skill"
)

// judged is what the shipped rule finds in the sources given, each finding as file:line with the file's path from the
// project, read through the same scan a judge runs.
func judged(t *testing.T, shipped string, sources map[string]string) []string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for path, text := range sources {
		file := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	name := strings.TrimSuffix(filepath.Base(shipped), ".json")
	index := slices.IndexFunc(rules.All(), func(each rule.Detector) bool { return each.Sin().Definition().Name == name })
	if index < 0 {
		t.Fatalf("no shipped rule is named %s", name)
	}
	read := rules.All()[index]
	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatal(err)
	}
	var found []string
	for _, match := range read.Find(codebase) {
		relative, _ := filepath.Rel(root, match.File())
		found = append(found, fmt.Sprintf("%s:%d", filepath.ToSlash(relative), match.Line()))
	}
	slices.Sort(found)

	return found
}

func expect(t *testing.T, language string, found []string, wanted ...string) {
	t.Helper()
	slices.Sort(wanted)
	if !slices.Equal(found, wanted) {
		t.Errorf("%s: the rule found %v, and %v is right", language, found, wanted)
	}
}

// The data-clump case of doc 61 section 5: one and two on A and three on B take the same three scalars, three in
// another order; a constructor and a method of two scalars take part in nothing.
func TestDataClumpFindsTheSameScalarsOnTwoOwners(t *testing.T) {
	expect(t, "php", judged(t, "shared-arguments/data-clump.json", map[string]string{
		"src/A.php": "<?php\nnamespace Shop;\n\nclass A\n{\n    public function one(int $x, int $y, string $unit): void\n    {\n    }\n\n    public function two(int $x, int $y, string $unit): void\n    {\n    }\n}\n",
		"src/B.php": "<?php\nnamespace Shop;\n\nclass B\n{\n    public function three(string $unit, int $y, int $x): void\n    {\n    }\n\n    public function __construct(int $x, int $y, string $unit)\n    {\n    }\n}\n",
		"src/C.php": "<?php\nnamespace Shop;\n\nclass C\n{\n    public function four(int $x, int $y): void\n    {\n    }\n}\n",
	}), "src/A.php:6", "src/A.php:10", "src/B.php:6")

	expect(t, "python", judged(t, "shared-arguments/data-clump.json", map[string]string{
		"shop/__init__.py": "",
		"shop/a.py":        "class A:\n    def one(self, x: int, y: int, unit: str) -> None:\n        pass\n\n    def two(self, x: int, y: int, unit: str) -> None:\n        pass\n",
		"shop/b.py":        "class B:\n    def three(self, unit: str, y: int, x: int) -> None:\n        pass\n\n    def __init__(self, x: int, y: int, unit: str) -> None:\n        pass\n",
		"shop/c.py":        "class C:\n    def four(self, x: int, y: int) -> None:\n        pass\n",
	}), "shop/a.py:2", "shop/a.py:5", "shop/b.py:2")

	expect(t, "typescript", judged(t, "shared-arguments/data-clump.json", map[string]string{
		"src/a.ts": "export class A {\n  one(x: number, y: number, unit: string): void {}\n\n  two(x: number, y: number, unit: string): void {}\n}\n",
		"src/b.ts": "export class B {\n  three(unit: string, y: number, x: number): void {}\n\n  constructor(x: number, y: number, unit: string) {}\n}\n",
		"src/c.ts": "export class C {\n  four(x: number, y: number): void {}\n}\n",
	}), "src/a.ts:2", "src/a.ts:4", "src/b.ts:2")

	expect(t, "csharp", judged(t, "shared-arguments/data-clump.json", map[string]string{
		"Shop.csproj": "<Project Sdk=\"Microsoft.NET.Sdk\"><PropertyGroup><TargetFramework>net8.0</TargetFramework></PropertyGroup></Project>\n",
		"A.cs":        "namespace Shop;\n\npublic class A\n{\n    public void One(int x, int y, string unit) { }\n\n    public void Two(int x, int y, string unit) { }\n}\n",
		"B.cs":        "namespace Shop;\n\npublic class B\n{\n    public void Three(string unit, int y, int x) { }\n\n    public B(int x, int y, string unit) { }\n}\n",
		"C.cs":        "namespace Shop;\n\npublic class C\n{\n    public void Four(int x, int y) { }\n}\n",
	}), "A.cs:5", "A.cs:7", "B.cs:5")

	expect(t, "go", judged(t, "shared-arguments/data-clump.json", map[string]string{
		"go.mod": "module example.com/shop\n\ngo 1.22\n",
		"a.go":   "package shop\n\ntype A struct{}\n\nfunc (A) One(x int, y int, unit string) {}\n\nfunc (A) Two(x int, y int, unit string) {}\n",
		"b.go":   "package shop\n\ntype B struct{}\n\nfunc (B) Three(unit string, y int, x int) {}\n",
		"c.go":   "package shop\n\ntype C struct{}\n\nfunc (C) Four(x int, y int) {}\n",
	}), "a.go:5", "a.go:7", "b.go:5")
}

// The duplicate-function case of doc 61 section 5: three copies of one body are found, each once; a copy in a test,
// a stub and a copy whose local is named otherwise join no group.
func TestDuplicateFunctionFindsEveryCopyOfOneBody(t *testing.T) {
	php := func(name, local string) string {
		return fmt.Sprintf("<?php\nnamespace Shop;\n\nfunction %s(array $items): int\n{\n    $%s = 0;\n    foreach ($items as $item) {\n        $%s = $%s + $item->price * $item->count;\n    }\n\n    return $%s;\n}\n", name, local, local, local, local)
	}
	expect(t, "php", judged(t, "shared-duplication/duplicate-function.json", map[string]string{
		"src/a.php":           php("totalA", "sum"),
		"src/b.php":           php("totalB", "sum"),
		"src/c.php":           php("totalC", "sum"),
		"src/d.php":           php("totalD", "acc"),
		"tests/TotalTest.php": php("totalTest", "sum"),
		"src/stub.php":        "<?php\nnamespace Shop;\n\nfunction stub(): int\n{\n    throw new \\LogicException('later');\n}\n",
	}), "src/a.php:4", "src/b.php:4", "src/c.php:4")

	python := func(name, local string) string {
		return fmt.Sprintf("def %s(items):\n    %s = 0\n    for item in items:\n        %s = %s + item.price * item.count\n    return %s\n", name, local, local, local, local)
	}
	expect(t, "python", judged(t, "shared-duplication/duplicate-function.json", map[string]string{
		"shop/__init__.py":    "",
		"shop/a.py":           python("total_a", "total"),
		"shop/b.py":           python("total_b", "total"),
		"shop/c.py":           python("total_c", "total"),
		"shop/d.py":           python("total_d", "acc"),
		"tests/test_total.py": python("total_test", "total"),
	}), "shop/a.py:1", "shop/b.py:1", "shop/c.py:1")

	typescript := func(name, local string) string {
		return fmt.Sprintf("export function %s(items: { price: number; count: number }[]): number {\n  let %s = 0;\n  for (const item of items) {\n    %s = %s + item.price * item.count;\n  }\n  return %s;\n}\n", name, local, local, local, local)
	}
	expect(t, "typescript", judged(t, "shared-duplication/duplicate-function.json", map[string]string{
		"src/a.ts":          typescript("totalA", "sum"),
		"src/b.ts":          typescript("totalB", "sum"),
		"src/c.ts":          typescript("totalC", "sum"),
		"src/d.ts":          typescript("totalD", "acc"),
		"src/total.test.ts": typescript("totalTest", "sum"),
	}), "src/a.ts:1", "src/b.ts:1", "src/c.ts:1")

	golang := func(name, local string) string {
		return fmt.Sprintf("package shop\n\ntype Item struct{ Price, Count int }\n\nfunc %s(items []Item) int {\n\t%s := 0\n\tfor _, item := range items {\n\t\t%s = %s + item.Price*item.Count\n\t}\n\treturn %s\n}\n", name, local, local, local, local)
	}
	expect(t, "go", judged(t, "shared-duplication/duplicate-function.json", map[string]string{
		"go.mod":    "module example.com/shop\n\ngo 1.22\n",
		"a.go":      golang("totalA", "sum"),
		"b/b.go":    strings.Replace(golang("totalB", "sum"), "package shop", "package b", 1),
		"c/c.go":    strings.Replace(golang("totalC", "sum"), "package shop", "package c", 1),
		"d/d.go":    strings.Replace(golang("totalD", "acc"), "package shop", "package d", 1),
		"a_test.go": strings.Replace(golang("totalTest", "sum"), "type Item struct{ Price, Count int }\n", "", 1),
	}), "a.go:5", "b/b.go:5", "c/c.go:5")
}

// The namespace-cycle case of doc 61 section 5: two imports of b in a and three of a in b; a's first import closes
// the cycle, as the thinner side.
func TestNamespaceCycleFindsTheThinnerSideOfAMutualPair(t *testing.T) {
	expect(t, "python", judged(t, "shared-dependencies/namespace-cycle.json", map[string]string{
		"shop/__init__.py": "",
		"shop/a.py":        "from shop.b import one\nfrom shop.b import two\n\n\ndef alpha() -> int:\n    return one() + two()\n",
		"shop/b.py":        "from shop.a import alpha\nfrom shop.a import alpha as first\nfrom shop.a import alpha as second\n\n\ndef one() -> int:\n    return 1\n\n\ndef two() -> int:\n    return alpha() + first() + second()\n",
	}), "shop/a.py:1")

	expect(t, "php", judged(t, "shared-dependencies/namespace-cycle.json", map[string]string{
		"src/A/Alpha.php": "<?php\nnamespace Shop\\A;\n\nuse Shop\\B\\One;\nuse Shop\\B\\Two;\n\nclass Alpha\n{\n    public function run(One $one, Two $two): void\n    {\n    }\n}\n",
		"src/B/One.php":   "<?php\nnamespace Shop\\B;\n\nuse Shop\\A\\Alpha;\n\nclass One\n{\n    public function run(Alpha $alpha): void\n    {\n    }\n}\n",
		"src/B/Two.php":   "<?php\nnamespace Shop\\B;\n\nuse Shop\\A\\Alpha;\n\nclass Two\n{\n    public function run(Alpha $alpha): void\n    {\n    }\n}\n",
		"src/B/Three.php": "<?php\nnamespace Shop\\B;\n\nuse Shop\\A\\Alpha;\n\nclass Three\n{\n    public function run(Alpha $alpha): void\n    {\n    }\n}\n",
	}), "src/A/Alpha.php:4")

	expect(t, "an A→B→C→A ring", judged(t, "shared-dependencies/namespace-cycle.json", map[string]string{
		"ring/__init__.py": "",
		"ring/a.py":        "from ring.b import bee\n\n\ndef ay() -> int:\n    return bee()\n",
		"ring/b.py":        "from ring.c import see\n\n\ndef bee() -> int:\n    return see()\n",
		"ring/c.py":        "from ring.a import ay\n\n\ndef see() -> int:\n    return ay()\n",
	}))
}

// The route table relation: each registration of a route verb with its actions, a class's method or an invokable
// class, and nothing for a call that only shares a verb's name and registers no action.
func TestRelationOfLaravelRouteRegistrations(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	routes := "<?php\nuse Illuminate\\Support\\Facades\\Route;\nuse Shop\\OrderController;\nuse Shop\\ExportOrders;\n\n" +
		"Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');\n" +
		"Route::post('/exports', ExportOrders::class);\n" +
		"$cache->get('orders');\n"
	if err := os.WriteFile(filepath.Join(root, "web.php"), []byte(routes), 0o644); err != nil {
		t.Fatal(err)
	}
	relationText, err := os.ReadFile(filepath.Join("..", "rules", "relations", "laravel-route-registrations.json"))
	if err != nil {
		t.Fatal(err)
	}
	probe := []byte(`{"$schema":"commandments:neutral-rule/v2","engine":"php","sin":{"name":"route-probe","skill":"x"},
		"find":{"from":"laravel-route-registrations","as":"r","pipeline":[{"where":["some","$r.actions","a",["in","$a","Shop\\OrderController::index","Shop\\ExportOrders::__invoke"]]},{"emit":"$r.anchor"}]}}`)
	sources := []rule.Source{
		{Origin: rule.Installed, Path: "rules/relations/laravel-route-registrations.json", Text: relationText},
		{Origin: rule.Project, Path: ".commandments/custom/route-probe.json", Text: probe},
	}
	for _, definitions := range []string{"tree.json", "laravel.json"} {
		text, err := os.ReadFile(filepath.Join("..", "rules", "definitions", definitions))
		if err != nil {
			t.Fatal(err)
		}
		sources = append(sources, rule.Source{Origin: rule.Installed, Path: "rules/definitions/" + definitions, Text: text})
	}
	library := rule.Load(sources, func(string) (skill.Skill, bool) { return nil, true })
	if len(library.Problems) != 0 || len(library.Rules) != 1 {
		t.Fatalf("the loader said %v", library.Problems)
	}
	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatal(err)
	}
	var lines []int
	for _, match := range library.Rules[0].Find(codebase) {
		lines = append(lines, match.Line())
	}
	if !slices.Equal(lines, []int{6, 7}) {
		t.Errorf("the route table holds the registrations on lines %v, and 6 and 7 are right", lines)
	}
}

// A throw that builds a generic failure with its message written at the throw is both sins; a named failure of the
// project, built by its own factory, is neither.
func TestAThrowOfAGenericFailureWithAWrittenMessageIsFound(t *testing.T) {
	sources := map[string]string{
		"src/Formatter.php":      "<?php\nnamespace Shop;\n\nfinal class Formatter\n{\n    public function name(?string $carrier): string\n    {\n        return $carrier ?? throw new \\RuntimeException('no carrier');\n    }\n\n    public function named(?string $carrier): string\n    {\n        return $carrier ?? throw MissingCarrier::make();\n    }\n}\n",
		"src/MissingCarrier.php": "<?php\nnamespace Shop;\n\nfinal class MissingCarrier extends \\DomainException\n{\n    public static function make(): self\n    {\n        return new self();\n    }\n}\n",
	}
	expect(t, "php", judged(t, "shared-exceptions/message-at-throw.json", sources), "src/Formatter.php:8")
	expect(t, "php", judged(t, "shared-exceptions/generic-exception.json", sources), "src/Formatter.php:8")
}

// A promoted constructor parameter is a field, and a parameter a closure reads is read; only the parameter nothing
// reads is unused.
func TestAPromotedOrCapturedParameterIsNotUnused(t *testing.T) {
	expect(t, "php", judged(t, "php-type-honesty/unused-parameter.json", map[string]string{
		"src/Address.php": "<?php\nnamespace Shop;\n\nfinal class Address\n{\n    public function __construct(\n        private string $line,\n        private string $city,\n    ) {}\n\n    public function move(string $line, string $city): callable\n    {\n        return fn () => $line;\n    }\n}\n",
	}), "src/Address.php:11")
}

// A private method a call reaches is used: the call's target names the method's own symbol.
func TestAPrivateMethodACallReachesIsUsed(t *testing.T) {
	expect(t, "php", judged(t, "php-type-honesty/unused-private-member.json", map[string]string{
		"src/Directory.php": "<?php\nnamespace Shop;\n\nfinal class Directory\n{\n    public function latest(): int\n    {\n        return $this->draft();\n    }\n\n    private function draft(): int\n    {\n        return 1;\n    }\n\n    private function never(): int\n    {\n        return 2;\n    }\n}\n",
	}), "src/Directory.php:16")
}

// A private field read through $this, or a promoted one, is used: the fetch refers to the property's own symbol.
func TestAPrivateFieldAFetchReadsIsUsed(t *testing.T) {
	expect(t, "php", judged(t, "php-type-honesty/unused-private-member.json", map[string]string{
		"src/Digest.php": "<?php\nnamespace Shop;\n\nfinal class Digest\n{\n    private int $points = 0;\n\n    private int $never = 0;\n\n    public function __construct(private string $name) {}\n\n    public function add(): string\n    {\n        $this->points++;\n\n        return $this->name;\n    }\n}\n",
	}), "src/Digest.php:8")
}

// A parameter written in the body is reassigned; a local of another name, and a by-reference parameter, are not.
func TestAReassignedPhpParameterIsFound(t *testing.T) {
	expect(t, "php", judged(t, "shared-flow/reassigned-parameter.json", map[string]string{
		"src/Bay.php": "<?php\nnamespace Shop;\n\nfinal class Bay\n{\n    public function receive(int $parcels, int $capacity, array &$log): int\n    {\n        if ($parcels > $capacity) {\n            $parcels = $capacity;\n        }\n        $log = [];\n        $kept = $parcels;\n\n        return $kept;\n    }\n}\n",
	}), "src/Bay.php:9")
}

// A service pulled from the container inside a class it could be handed to is container reach: app() resolves to
// the global function, and the enclosing class is assignable as itself.
func TestAppResolvingAServiceInsideAClassIsContainerReach(t *testing.T) {
	expect(t, "php", judged(t, "php-laravel-idioms/container-reach.json", map[string]string{
		"src/Checkout.php": "<?php\nnamespace Shop;\n\nfinal class Checkout\n{\n    public function pay(): void\n    {\n        app(Payments::class)->charge();\n    }\n}\n",
		"src/Payments.php": "<?php\nnamespace Shop;\n\nfinal class Payments\n{\n    public function charge(): void\n    {\n    }\n}\n",
	}), "src/Checkout.php:8")
}

func TestACommentRuleAskedOfOneFileAfterAnotherFindsTheSecondFilesComments(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	stacked := "<?php\nnamespace App;\nclass %s {\n    /**\n     * Opens it.\n     */\n    /**\n     * Opens it again.\n     */\n    public function open(): void {}\n}\n"
	for _, name := range []string{"First", "Second"} {
		if err := os.WriteFile(filepath.Join(root, name+".php"), fmt.Appendf(nil, stacked, name), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	index := slices.IndexFunc(rules.All(), func(each rule.Detector) bool { return each.Sin().Definition().Name == "stacked-docblock" })
	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatal(err)
	}
	for _, name := range []string{"First", "Second"} {
		path := filepath.Join(root, name+".php")
		found := rules.All()[index].Find(codebase.Reporting(path))
		if len(found) == 0 || found[0].File() != path {
			t.Errorf("stacked-docblock asked of %s alone finds %d sites there", name, len(found))
		}
	}
}
