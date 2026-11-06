package gotree_test

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge/gotree"
	"github.com/jessegall/code-commandments/contract"
)

const cart = `package shop

import "fmt"

// Cart holds what a customer means to buy.
type Cart struct {
	lines []Line
	note  string
}

// Total is what the cart costs, in cents.
func (c *Cart) Total(discount int) int {
	total := 0
	for _, line := range c.lines {
		if line.Price > 0 && line.Quantity > 0 {
			total += line.Price * line.Quantity // total += line.Price
		}
	}

	return total - discount
}

func describe(c *Cart) string {
	fmt.Println(c.note)

	return fmt.Sprintf("%d lines, note %q, mark %c, rate %f", len(c.lines), "none", 'x', 1.5)
}
`

func streamOf(t *testing.T, files map[string]string) *contract.Stream {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range files {
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
		t.Fatalf("the stream breaks the contract: %v\n%s", err, written.String())
	}

	return stream
}

func TestTheTreeIsTheContractsOwnShape(t *testing.T) {
	stream := streamOf(t, map[string]string{"cart.go": cart})

	if stream.Header.Language != contract.Go {
		t.Fatalf("the stream says it is %s", stream.Header.Language)
	}
	if len(stream.Files) != 1 {
		t.Fatalf("the stream holds %d files", len(stream.Files))
	}

	file := stream.Files[0]
	if file.Errors != 0 {
		t.Errorf("a file that parses whole reports %d errors", file.Errors)
	}
	if file.Root.Kind != "File" || file.Root.Name != "shop" {
		t.Errorf("the root is %s named %q", file.Root.Kind, file.Root.Name)
	}

	source, err := os.ReadFile(file.Path)
	if err != nil {
		t.Fatal(err)
	}
	for _, node := range file.Nodes() {
		if node.Span.Start < 0 || node.Span.End > len(source) || node.Span.Start > node.Span.End {
			t.Fatalf("%s at %d has the span [%d, %d) of a file %d bytes long", node.Kind, node.ID, node.Span.Start, node.Span.End, len(source))
		}
		if node.Span.Line < 1 {
			t.Fatalf("%s at %d says it is on line %d", node.Kind, node.ID, node.Span.Line)
		}
		if node.ID != 0 && node.Field == "" {
			t.Fatalf("%s at %d fills no field of its parent", node.Kind, node.ID)
		}
	}
}

func TestEveryNodeIsNamedAsGoNamesIt(t *testing.T) {
	file := streamOf(t, map[string]string{"cart.go": cart}).Files[0]

	method := firstOf(t, file, "FuncDecl")
	if method.Name != "Cart" && method.Name != "Total" && method.Name != "describe" {
		t.Errorf("the first function is named %q", method.Name)
	}
	if receiver := childIn(method, "recv"); receiver == nil {
		t.Error("a method carries no receiver, which is the one fact a Go method's type is read through")
	}

	call := firstOf(t, file, "CallExpr")
	callee := childIn(call, "fun")
	if callee == nil {
		t.Fatal("a call says nothing about what it calls")
	}
	if callee.Name == "" && childIn(callee, "sel") == nil {
		t.Error("a call's callee carries neither a name nor the member it selects")
	}
}

func TestTheNeutralQuestionsAnswerForGo(t *testing.T) {
	file := streamOf(t, map[string]string{"cart.go": cart}).Files[0]

	for _, want := range []string{"function", "loop", "branch", "comparison", "conjunction", "call", "member-access", "assignment", "literal", "import", "block", "return", "bail-out", "identifier", "expression-statement", "type-declaration"} {
		if !answers(file, want) {
			t.Errorf("no node answers %q", want)
		}
	}
}

func TestALiteralCarriesWhatItDecodesTo(t *testing.T) {
	file := streamOf(t, map[string]string{"values.go": "package shop\n\nvar (\n\tname = \"a \\\"quoted\\\" name\"\n\thex  = 0x1f\n\tmark = 'x'\n\trate = 1.5\n)\n"}).Files[0]

	values := map[string]string{}
	for _, node := range file.Nodes() {
		if node.Literal == "" {
			continue
		}
		text, _ := node.Value.Text()
		values[node.Literal] = text
	}

	if values["string"] != `a "quoted" name` {
		t.Errorf("a string decodes to %q", values["string"])
	}
	if values["int"] != "31" && values["int"] != "120" {
		t.Errorf("an integer decodes to %q, where 0x1f is 31 and 'x' is 120", values["int"])
	}
	if values["float"] != "1.5" {
		t.Errorf("a float decodes to %q", values["float"])
	}
}

func TestACommentSaysWhereItBelongs(t *testing.T) {
	file := streamOf(t, map[string]string{"cart.go": cart}).Files[0]

	var doc, trailing, code int
	for _, comment := range file.Comments {
		if comment.Kind == "doc" {
			doc++
		}
		if comment.Trailing {
			trailing++
		}
		if comment.Extras != nil && comment.Extras.Go != nil && comment.Extras.Go.Code {
			code++
		}
		if comment.Attached == nil {
			t.Errorf("comment %d (%q) belongs to no node", comment.ID, comment.Text)
		}
	}
	if doc < 2 {
		t.Errorf("%d comments lead a declaration, where the file documents a type and a method", doc)
	}
	if trailing != 1 {
		t.Errorf("%d comments trail code, where the file has one", trailing)
	}
	if code != 1 {
		t.Errorf("%d comments read as code, where the file has one (`total += line.Price`)", code)
	}
	for _, comment := range file.Comments {
		if !strings.HasPrefix(comment.Text, "//") && !strings.HasPrefix(comment.Text, "/*") {
			t.Errorf("comment %d reads %q, with its markers stripped", comment.ID, comment.Text)
		}
	}
}

func TestAFileThatDoesNotCompileIsStillATree(t *testing.T) {
	stream := streamOf(t, map[string]string{"broken.go": "package shop\n\nfunc total(\n", "fine.go": "package shop\n\nfunc fine() {}\n"})

	if len(stream.Files) != 2 {
		t.Fatalf("the stream holds %d files, where one of two does not parse", len(stream.Files))
	}
	broken := fileNamed(t, stream, "broken.go")
	if broken.Errors == 0 {
		t.Error("a file that does not parse reports no errors")
	}
	if broken.Root == nil {
		t.Error("a file that does not parse carries no tree at all")
	}
	if fine := fileNamed(t, stream, "fine.go"); fine.Errors != 0 {
		t.Error("a file that parses whole was blamed for its neighbour")
	}
}

func TestTheSameFileAlwaysWritesTheSameStream(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "cart.go"), []byte(cart), 0o644); err != nil {
		t.Fatal(err)
	}

	var once, again bytes.Buffer
	if err := gotree.Write(&once, []string{root}, nil); err != nil {
		t.Fatal(err)
	}
	if err := gotree.Write(&again, []string{root}, nil); err != nil {
		t.Fatal(err)
	}
	if !bytes.Equal(once.Bytes(), again.Bytes()) {
		t.Error("two runs over the same file wrote two different streams")
	}
}

func firstOf(t *testing.T, file *contract.File, kind string) *contract.Node {
	t.Helper()
	for _, node := range file.Nodes() {
		if node.Kind == kind {
			return node
		}
	}
	t.Fatalf("the file holds no %s", kind)

	return nil
}

func childIn(node *contract.Node, field string) *contract.Node {
	for _, child := range node.Children {
		if child.Field == field {
			return child
		}
	}

	return nil
}

func answers(file *contract.File, neutral string) bool {
	for _, node := range file.Nodes() {
		for _, is := range node.Is {
			if is == neutral {
				return true
			}
		}
	}

	return false
}

func fileNamed(t *testing.T, stream *contract.Stream, name string) *contract.File {
	t.Helper()
	for _, file := range stream.Files {
		if strings.HasSuffix(file.Path, "/"+name) {
			return file
		}
	}
	t.Fatalf("the stream holds no %s", name)

	return nil
}
