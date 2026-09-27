package contract

import (
	"strings"
	"testing"
)

const phpFile = `{"file": {"path": "/abs/src/Cart.php", "language": "php", "errors": 0, "root": {"id": 0, "kind": "Stmt_Class", "role": "member", "is": ["type-declaration"], "span": [6, 80, 3], "name": "Cart", "symbol": "Shop\\Cart", "children": [{"id": 1, "kind": "Identifier", "role": "other", "span": [12, 16, 3], "field": "name", "name": "Cart"}]}, "comments": [{"id": 0, "kind": "doc", "text": "/** A cart. */", "span": [0, 5, 2], "attached": 0}]}}`

func stream(language string, lines ...string) string {
	header := `{"header": {"contract": "tree", "version": 1, "language": "` + language + `", "bridge": {"name": "test", "version": "1"}, "roots": ["/abs/src"]}}`

	return strings.Join(append([]string{header}, lines...), "\n") + "\n"
}

func trailer(files string) string {
	return `{"trailer": {"files": ` + files + `}}`
}

func TestReadsAValidStreamPerLanguage(t *testing.T) {
	files := map[string]string{
		"php":        phpFile,
		"python":     `{"file": {"path": "/abs/shop/cart.py", "language": "python", "errors": 0, "module": "shop.cart", "resolver": {"tool": "mypy", "ran": true}, "root": {"id": 0, "kind": "Module", "role": "other", "span": [0, 20, 1], "children": [{"id": 1, "kind": "Compare", "role": "expression", "is": ["comparison"], "span": [0, 9, 1], "field": "body", "extras": {"python": {"operators": ["<", "<="]}}, "resolved": {"text": "builtins.bool", "kind": "named", "name": "builtins.bool", "origin": "compiler"}}]}, "comments": []}}`,
		"csharp":     `{"file": {"path": "/abs/Shop/Cart.cs", "language": "csharp", "errors": 0, "test": true, "root": {"id": 0, "kind": "CompilationUnit", "role": "other", "span": [0, 40, 1], "children": [{"id": 1, "kind": "InvocationExpression", "role": "expression", "is": ["call"], "span": [10, 20, 2], "field": "Members", "target": {"symbol": "global::Shop.Cart.Add(global::System.Int32)", "type": "global::Shop.Cart", "name": "Add"}}]}, "comments": [{"id": 0, "kind": "line", "text": "// x = 1;", "span": [0, 9, 1], "extras": {"csharp": {"code": true}}}]}}`,
		"typescript": `{"file": {"path": "/abs/src/cart.ts", "language": "typescript", "errors": 0, "root": {"id": 0, "kind": "SourceFile", "role": "other", "span": [0, 30, 1], "children": [{"id": 1, "kind": "ImportDeclaration", "role": "statement", "is": ["import"], "span": [0, 29, 1], "field": "statements", "resolves": "/abs/src/money.ts", "extras": {"typescript": {"typeOnly": true}}}]}, "comments": []}}`,
		"vue":        `{"file": {"path": "/abs/src/Cart.vue", "language": "vue", "errors": 0, "root": {"id": 0, "kind": "Component", "role": "markup", "span": [0, 60, 1], "children": [{"id": 1, "kind": "Directive", "role": "markup", "span": [20, 40, 2], "field": "attributes", "flags": ["shorthand"], "extras": {"vue": {"directive": {"name": "bind", "modifiers": []}}}}]}, "comments": []}}`,
	}
	for language, file := range files {
		t.Run(language, func(t *testing.T) {
			read, err := ReadAll(strings.NewReader(stream(language, file, trailer("1"))))
			if err != nil {
				t.Fatalf("a valid %s stream is refused: %v", language, err)
			}
			if len(read.Files) != 1 {
				t.Fatalf("read %d files, want 1", len(read.Files))
			}
		})
	}
}

func TestLinksParents(t *testing.T) {
	read, err := ReadAll(strings.NewReader(stream("php", phpFile, trailer("1"))))
	if err != nil {
		t.Fatal(err)
	}
	name, ok := read.Files[0].Node(1)
	if !ok {
		t.Fatal("node 1 is missing")
	}
	parent, ok := name.Parent()
	if !ok || parent.ID != 0 {
		t.Fatalf("node 1's parent is not the root")
	}
	if !parent.Answers("type-declaration") {
		t.Fatal("the class does not answer type-declaration")
	}
}

func TestRefuses(t *testing.T) {
	cases := map[string]string{
		"an unknown key":                                stream("php", strings.Replace(phpFile, `"errors": 0`, `"errors": 0, "size": 9`, 1), trailer("1")),
		"a stream with no trailer":                      stream("php", phpFile),
		"a wrong trailer count":                         stream("php", phpFile, trailer("2")),
		"an unknown version":                            strings.Replace(stream("php", trailer("0")), `"version": 1`, `"version": 9`, 1),
		"ids out of pre-order":                          stream("php", strings.Replace(phpFile, `"id": 1,`, `"id": 5,`, 1), trailer("1")),
		"a child with no field":                         stream("php", strings.Replace(phpFile, `"field": "name", `, ``, 1), trailer("1")),
		"a comment attached to no node":                 stream("php", strings.Replace(phpFile, `"attached": 0`, `"attached": 7`, 1), trailer("1")),
		"an engine fact in a php stream":                stream("php", strings.Replace(phpFile, `"name": "Cart", "symbol"`, `"name": "Cart", "constant": true, "symbol"`, 1), trailer("1")),
		"an unknown extras key":                         stream("php", strings.Replace(phpFile, `"name": "Cart", "symbol"`, `"name": "Cart", "extras": {"php": {"x": true}}, "symbol"`, 1), trailer("1")),
		"a neutral kind outside the set":                stream("php", strings.Replace(phpFile, `["type-declaration"]`, `["thing"]`, 1), trailer("1")),
		"a line before the header":                      trailer("0") + "\n",
		"a file after the program line":                 stream("php", `{"program": {}}`, phpFile, trailer("1")),
		"a program fact the language has no source for": stream("php", `{"program": {"packages": ["/abs/src"]}}`, trailer("0")),
		"a number as a literal value":                   stream("php", strings.Replace(phpFile, `"name": "Cart", "symbol"`, `"name": "Cart", "value": 3, "symbol"`, 1), trailer("1")),
		"a span of two numbers":                         stream("php", strings.Replace(phpFile, `[6, 80, 3]`, `[6, 80]`, 1), trailer("1")),
	}
	for name, input := range cases {
		t.Run(name, func(t *testing.T) {
			if _, err := ReadAll(strings.NewReader(input)); err == nil {
				t.Fatalf("%s is read without complaint", name)
			}
		})
	}
}

func TestTheValidatorRefusesAKeywordItWouldIgnore(t *testing.T) {
	schema := map[string]any{"properties": map[string]any{"x": map[string]any{"format": "uri"}}}
	if err := known(schema, ""); err == nil {
		t.Fatal("a schema using format loads, though nothing applies it")
	}
}

func TestTheSchemaAloneRefusesWhatDecodingWouldAccept(t *testing.T) {
	lines := map[string]string{
		"a neutral kind outside the set": strings.Replace(phpFile, `["type-declaration"]`, `["thing"]`, 1),
		"a literal kind outside the set": strings.Replace(phpFile, `"name": "Cart", "symbol"`, `"name": "Cart", "literal": "decimal", "symbol"`, 1),
		"a relative path":                strings.Replace(phpFile, `"/abs/src/Cart.php"`, `"src/Cart.php"`, 1),
		"a line with two keys":           `{"trailer": {"files": 0}, "program": {}}`,
		"a missing required key":         strings.Replace(phpFile, `"errors": 0, `, ``, 1),
	}
	for name, line := range lines {
		t.Run(name, func(t *testing.T) {
			if err := Validate([]byte(line)); err == nil {
				t.Fatalf("the schema accepts %s", name)
			}
		})
	}
	if err := Validate([]byte(phpFile)); err != nil {
		t.Fatalf("the schema refuses a valid file line: %v", err)
	}
}

// TestATypeSaidOnceIsPutBackOnEveryNodeThatNamesIt holds the reader to a file's type table: each node naming a type
// by resolvedType resolves to it as if written inline, and an index past the table is refused.
func TestATypeSaidOnceIsPutBackOnEveryNodeThatNamesIt(t *testing.T) {
	file := `{"file": {"path": "/abs/src/cart.ts", "language": "typescript", "errors": 0, "root": {"id": 0, "kind": "SourceFile", "role": "other", "span": [0, 30, 1], "children": [` +
		`{"id": 1, "kind": "Identifier", "role": "expression", "span": [0, 4, 1], "field": "statements", "resolvedType": 0},` +
		`{"id": 2, "kind": "Identifier", "role": "expression", "span": [5, 9, 1], "field": "statements", "resolvedType": 0}]}, "comments": [],` +
		`"types": [{"text": "string", "kind": "keyword", "name": "string", "origin": "compiler"}]}}`
	read, err := ReadAll(strings.NewReader(stream("typescript", file, trailer("1"))))
	if err != nil {
		t.Fatal(err)
	}
	for _, id := range []int{1, 2} {
		node, _ := read.Files[0].Node(id)
		if node.Resolved == nil || node.Resolved.Text != "string" || node.ResolvedType != nil {
			t.Errorf("node %d resolves to %+v", id, node.Resolved)
		}
	}
	if _, err := ReadAll(strings.NewReader(stream("typescript", strings.Replace(file, `"resolvedType": 0}]`, `"resolvedType": 1}]`, 1), trailer("1")))); err == nil {
		t.Error("a node naming a type the file does not hold is read")
	}
}
