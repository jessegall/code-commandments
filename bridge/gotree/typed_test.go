package gotree_test

import (
	"bytes"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge/gotree"
	"github.com/jessegall/code-commandments/contract"
)

// typedModule is a module of two packages, so what a type-check gives across packages can be read: the cart
// knows the money package's type, and a rule reading a receiver's type needs exactly that.
var typedModule = map[string]string{
	"go.mod":          "module example.com/shop\n",
	"money/money.go":  "package money\n\ntype Money struct {\n\tcents int\n}\n\nfunc Of(cents int) Money { return Money{cents: cents} }\n\nfunc (m Money) Cents() int { return m.cents }\n",
	"cart/cart.go":    "package cart\n\nimport \"example.com/shop/money\"\n\ntype Cart struct {\n\tlines []money.Money\n\tnote  *string\n}\n\nfunc (c *Cart) Total() money.Money {\n\ttotal := 0\n\tfor _, line := range c.lines {\n\t\ttotal += line.Cents()\n\t}\n\n\treturn money.Of(total)\n}\n\nfunc (c *Cart) Lines() []money.Money { return c.lines }\n",
	"cart/outside.go": "package cart\n\nimport \"net/http\"\n\nfunc Serve(w http.ResponseWriter) { w.WriteHeader(200) }\n",
}

// typedStream writes the module's tree and reads it back through the contract's reader.
func typedStream(t *testing.T) *contract.Stream {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range typedModule {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	var written bytes.Buffer
	if err := gotree.Write(&written, []string{root}, nil); err != nil {
		t.Fatalf("the bridge wrote nothing: %v", err)
	}
	stream, err := contract.ReadAll(bytes.NewReader(written.Bytes()))
	if err != nil {
		t.Fatalf("the stream breaks the contract: %v", err)
	}

	return stream
}

// TestAnExpressionCarriesTheTypeTheCheckerGave holds the types to the checker's own answers, named by the
// package that declares them, so a rule reads a type rather than the text that spelled it.
func TestAnExpressionCarriesTheTypeTheCheckerGave(t *testing.T) {
	file := fileNamed(t, typedStream(t), "cart.go")

	if file.Resolver == nil || file.Resolver.Tool != "go/types" || !file.Resolver.Ran {
		t.Fatalf("the file says its resolver is %+v", file.Resolver)
	}

	texts := map[string]bool{}
	for _, node := range file.Nodes() {
		if node.Resolved != nil {
			texts[node.Resolved.Text] = true
		}
	}
	for _, want := range []string{"example.com/shop/money.Money", "int", "[]example.com/shop/money.Money"} {
		if !texts[want] {
			t.Errorf("no expression resolves to %q", want)
		}
	}

	slice := typeNamed(t, file, "[]example.com/shop/money.Money")
	if slice.Kind != "array" {
		t.Errorf("a slice is written as kind %q", slice.Kind)
	}
	if slice.Element == nil || slice.Element.Text != "example.com/shop/money.Money" {
		t.Errorf("a slice carries the element %+v", slice.Element)
	}
	if slice.Nullable {
		t.Error("a slice is written as admitting absence, where a nil slice ranges cleanly")
	}

	money := typeNamed(t, file, "example.com/shop/money.Money")
	if money.Kind != "named" || money.Name != "example.com/shop/money.Money" {
		t.Errorf("a named type is written as %q named %q", money.Kind, money.Name)
	}
	if !money.ValueType {
		t.Error("a struct is not written as a value type, though Go copies one")
	}
}

// TestAPointerAdmitsAbsenceAndASliceDoesNot holds the one place the contract reads absence to Go's own
// semantics: a nil pointer is a value that is missing, and a nil slice is an empty one.
func TestAPointerAdmitsAbsenceAndASliceDoesNot(t *testing.T) {
	file := fileNamed(t, typedStream(t), "cart.go")

	pointer := typeNamed(t, file, "*string")
	if !pointer.Nullable {
		t.Error("a pointer is not written as admitting absence")
	}
	if pointer.Kind != "pointer" {
		t.Errorf("a pointer is written as kind %q", pointer.Kind)
	}
	if pointer.Element == nil || pointer.Element.Text != "string" {
		t.Errorf("a pointer carries %+v as what it points at", pointer.Element)
	}
}

// TestAMapCarriesItsKeyAndItsValue holds a map to a kind of its own, with its value as its element: that is what
// a rule about a bag of values read by keys written in the source reads, where reading `map[` off the text would
// be parsing the type again.
func TestAMapCarriesItsKeyAndItsValue(t *testing.T) {
	source := "package shop" + "\n" + "\n" + "func read(row map[string]any) any { return row[" + "\"" + "total" + "\"" + "] }" + "\n"
	root := module(t, map[string]string{
		"go.mod": "module example.com/shop" + "\n",
		"bag.go": source,
	})

	var written bytes.Buffer
	if err := gotree.Write(&written, []string{root}, nil); err != nil {
		t.Fatal(err)
	}
	stream, err := contract.ReadAll(bytes.NewReader(written.Bytes()))
	if err != nil {
		t.Fatalf("the stream breaks the contract: %v", err)
	}

	held := typeNamed(t, fileNamed(t, stream, "bag.go"), "map[string]any")
	if held.Kind != "map" {
		t.Errorf("a map is written as kind %q", held.Kind)
	}
	if held.Element == nil || held.Element.Text != "any" {
		t.Errorf("a map carries %+v as its element", held.Element)
	}
	if len(held.Args) != 2 || held.Args[0].Text != "string" {
		t.Errorf("a map carries %d args, where its key comes first", len(held.Args))
	}
	if held.Nullable {
		t.Error("a map is written as admitting absence, where a nil map reads")
	}
}

// TestADeclarationCarriesItsOwnId holds the symbols to the way CONTRACT.md spells Go's: a package-level
// declaration is its package's path and its name, and a method or a field is that with its type between them.
func TestADeclarationCarriesItsOwnId(t *testing.T) {
	file := fileNamed(t, typedStream(t), "cart.go")

	ids := map[string]bool{}
	for _, node := range file.Nodes() {
		if node.Symbol != "" {
			ids[node.Symbol] = true
		}
	}
	for _, want := range []string{
		"example.com/shop/cart.Cart",
		"example.com/shop/cart.Cart.Total",
		"example.com/shop/cart.Cart.lines",
	} {
		if !ids[want] {
			t.Errorf("no declaration carries the id %q, of %v", want, keys(ids))
		}
	}
}

// TestACallReachesTheDeclarationItCalls holds a call's target to the declaration it calls, which is what joins a
// call site to the method it reaches, across packages as well as inside one.
func TestACallReachesTheDeclarationItCalls(t *testing.T) {
	file := fileNamed(t, typedStream(t), "cart.go")

	reached := map[string]string{}
	for _, node := range file.Nodes() {
		if node.Target != nil {
			reached[node.Target.Symbol] = node.Target.Type
		}
	}
	if _, found := reached["example.com/shop/money.Money.Cents"]; !found {
		t.Errorf("no call reaches Cents, of %v", keys(reached))
	}
	if reached["example.com/shop/money.Money.Cents"] != "example.com/shop/money.Money" {
		t.Errorf("the call names %q as the type that declares Cents", reached["example.com/shop/money.Money.Cents"])
	}
	if _, found := reached["example.com/shop/money.Of"]; !found {
		t.Errorf("no call reaches the package function Of, of %v", keys(reached))
	}
}

// TestANameSaysWhatItRefersTo holds a use of a name to the declaration it names, so a rule never matches a name
// as text to find what it means.
func TestANameSaysWhatItRefersTo(t *testing.T) {
	file := fileNamed(t, typedStream(t), "cart.go")

	refers := map[string]bool{}
	for _, node := range file.Nodes() {
		if node.Refers != "" {
			refers[node.Refers] = true
		}
	}
	for _, want := range []string{"example.com/shop/money.Money", "example.com/shop/cart.Cart.lines"} {
		if !refers[want] {
			t.Errorf("no name refers to %q, of %v", want, keys(refers))
		}
	}
}

// TestATypeOutsideTheModuleIsUnknownRatherThanWrong holds the typing decision of doc 3 where it shows: an import
// outside the module is read as nothing, so an expression through it carries no type at all. That is what keeps
// a rule from reading a type the checker never gave.
func TestATypeOutsideTheModuleIsUnknownRatherThanWrong(t *testing.T) {
	file := fileNamed(t, typedStream(t), "outside.go")

	for _, node := range file.Nodes() {
		if node.Resolved == nil {
			continue
		}
		if node.Resolved.Text == "net/http.ResponseWriter" {
			t.Errorf("a type from outside the module was resolved as %q", node.Resolved.Text)
		}
	}
	if file.Resolver == nil || !file.Resolver.Ran {
		t.Error("the file says no resolver ran on it, though its own package was checked")
	}
}

// TestAFunctionSaysWhatItReturns holds a function's result to what it writes, a tuple where it answers several,
// which is Go's own shape and the one every (T, error) reading starts from.
func TestAFunctionSaysWhatItReturns(t *testing.T) {
	root := module(t, map[string]string{
		"go.mod":  "module example.com/shop\n",
		"find.go": "package shop\n\nimport \"errors\"\n\nfunc find(id int) (string, error) {\n\tif id == 0 {\n\t\treturn \"\", errors.New(\"no\")\n\t}\n\n\treturn \"one\", nil\n}\n\nfunc count() int { return 1 }\n",
	})

	var written bytes.Buffer
	if err := gotree.Write(&written, []string{root}, nil); err != nil {
		t.Fatal(err)
	}
	stream, err := contract.ReadAll(bytes.NewReader(written.Bytes()))
	if err != nil {
		t.Fatalf("the stream breaks the contract: %v", err)
	}

	file := fileNamed(t, stream, "find.go")
	results := map[string]string{}
	for _, node := range file.Nodes() {
		if node.Kind == "FuncDecl" && node.Returns != nil {
			results[node.Name] = node.Returns.Kind + " " + node.Returns.Text
		}
	}
	if results["find"] != "tuple (string, error)" {
		t.Errorf("a function of two results says it returns %q", results["find"])
	}
	if results["count"] != "keyword int" {
		t.Errorf("a function of one result says it returns %q", results["count"])
	}
}

// typeNamed is the type of that text the file's nodes carry. The reader puts each of a file's types back on the
// nodes that named it and clears the list, so the nodes are where a test reads them.
func typeNamed(t *testing.T, file *contract.File, text string) *contract.Type {
	t.Helper()
	for _, node := range file.Nodes() {
		for _, held := range []*contract.Type{node.Resolved, node.Declared, node.Returns} {
			if held != nil && held.Text == text {
				return held
			}
		}
	}
	t.Fatalf("no node of %s carries the type %q, of %v", file.Path, text, textsIn(file))

	return nil
}

// textsIn are the texts of every type the file's nodes carry.
func textsIn(file *contract.File) []string {
	var texts []string
	seen := map[string]bool{}
	for _, node := range file.Nodes() {
		for _, held := range []*contract.Type{node.Resolved, node.Declared, node.Returns} {
			if held == nil || seen[held.Text] {
				continue
			}
			seen[held.Text] = true
			texts = append(texts, held.Text)
		}
	}

	return texts
}

// keys are a map's keys, for a failure to read.
func keys[V any](held map[string]V) []string {
	var names []string
	for name := range held {
		names = append(names, name)
	}

	return names
}

// TestAFileThatDoesNotTypeCheckKeepsTheFactsAroundIt holds a type error to what it costs: the line the checker
// cannot follow, and nothing else. The file beside it is typed, and so is the rest of its own file.
//
// The errors are not reported, and this is where the measuring shows why: an import outside the module is
// answered with an empty package, so every file that touches the standard library raises one. What a reader
// needs is in the stream per file, where the resolver says whether one ran at all.
func TestAFileThatDoesNotTypeCheckKeepsTheFactsAroundIt(t *testing.T) {
	root := module(t, map[string]string{
		"go.mod":  "module example.com/shop\n",
		"bad.go":  "package shop\n\nfunc total() int { return \"not a number\" }\n",
		"good.go": "package shop\n\nfunc rate() int { return 2 }\n",
	})

	var written bytes.Buffer
	if err := gotree.Write(&written, []string{root}, nil); err != nil {
		t.Fatalf("a type error stopped the whole stream: %v", err)
	}
	stream, err := contract.ReadAll(bytes.NewReader(written.Bytes()))
	if err != nil {
		t.Fatalf("the stream breaks the contract: %v", err)
	}

	good := fileNamed(t, stream, "good.go")
	if good.Resolver == nil || !good.Resolver.Ran {
		t.Error("the file beside the bad one says no resolver ran on it")
	}
	typeNamed(t, good, "int")
	typeNamed(t, fileNamed(t, stream, "bad.go"), "int")
}

// TestTheWholeModuleAlwaysWritesTheSameStream holds a run of several packages to one answer. The checker keeps
// what it learned in maps, which Go walks in a different order every run, so a stream built from them in that
// order would differ between two runs of the same unchanged tree, and the engine's own recordings and memoised
// readings would read that as a change in the code.
func TestTheWholeModuleAlwaysWritesTheSameStream(t *testing.T) {
	root := module(t, typedModule)

	var once, again bytes.Buffer
	if err := gotree.Write(&once, []string{root}, nil); err != nil {
		t.Fatal(err)
	}
	if err := gotree.Write(&again, []string{root}, nil); err != nil {
		t.Fatal(err)
	}
	if !bytes.Equal(once.Bytes(), again.Bytes()) {
		t.Error("two runs over the same module wrote two different streams")
	}
}
