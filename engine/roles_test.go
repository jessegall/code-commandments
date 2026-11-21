package engine

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

const versionFiveHeader = `{"header": {"contract": "tree", "version": 5, "language": "python", "bridge": {"name": "python-bridge", "version": "8.0.0"}, "roots": ["/src"]}}`

// reader is a try with a handler that swallows, inside a function whose body also holds a closure binding a name.
const versionFiveReader = `{"file": {"path": "/src/tests/fixtures/reader.py", "language": "python", "errors": 0, "module": "app.reader",
"resolver": {"tool": "mypy", "ran": true},
"root": {"id": 0, "kind": "file", "span": [0, 90, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 90, 1], "name": "app.reader", "parts": {"statements": [
  {"id": 2, "kind": "function", "span": [0, 90, 1], "name": "read", "form": "declaration", "parts": {"body": {"id": 3, "kind": "block", "span": [10, 90, 2], "parts": {"statements": [
    {"id": 4, "kind": "try", "span": [10, 60, 2], "parts": {
      "body": {"id": 5, "kind": "block", "span": [14, 20, 3], "parts": {"statements": []}},
      "handlers": [{"id": 6, "kind": "handler", "span": [21, 60, 4], "catchForm": "catch-all", "incomplete": true, "parts": {
        "body": {"id": 7, "kind": "block", "span": [40, 60, 5], "parts": {"statements": []}}}}]}},
    {"id": 8, "kind": "expression-statement", "span": [61, 90, 6], "parts": {"expression":
      {"id": 9, "kind": "function", "span": [61, 90, 6], "form": "lambda", "parts": {"body":
        {"id": 10, "kind": "reference", "span": [80, 81, 6], "form": "name", "name": "x"}}}}}]}}}}]}}]}},
"comments": []}}`

const versionFiveTrailer = `{"trailer": {"files": 1}}`

func versionFiveCodebase(t *testing.T, file string) *Codebase {
	t.Helper()
	var lines []string
	for _, line := range []string{versionFiveHeader, file, versionFiveTrailer} {
		lines = append(lines, strings.Join(strings.Fields(line), " "))
	}
	codebase, err := FromString(strings.Join(lines, "\n")+"\n", map[string]string{"/src/tests/fixtures/reader.py": strings.Repeat(" ", 90)})
	if err != nil {
		t.Fatal(err)
	}

	return codebase
}

func TestARoleIsReadByNameAndAnUndeclaredRoleIsRefusedByName(t *testing.T) {
	codebase := versionFiveCodebase(t, versionFiveReader)
	tried := codebase.WhereKind("try").First()
	handlers, err := tried.List("handlers")
	if err != nil || len(handlers) != 1 || handlers[0].Kind() != "handler" {
		t.Fatalf("a try's handlers read %v, %v", handlers, err)
	}
	if body, err := handlers[0].One("body"); err != nil || body.Kind() != "block" {
		t.Errorf("a handler's body read %v, %v", body.Kind(), err)
	}
	if _, err := handlers[0].In("then"); err == nil || !strings.Contains(err.Error(), `a handler has no role "then"`) {
		t.Errorf("a handler's then was answered %v", err)
	}
	if _, err := tried.One("handlers"); err == nil {
		t.Error("a list role read as one node was not refused")
	}
}

func TestAnUnknownRoleStaysUnknownUnderNegation(t *testing.T) {
	codebase := versionFiveCodebase(t, versionFiveReader)
	handler := codebase.WhereKind("handler").First()
	filter, err := handler.In("filter")
	if err != nil {
		t.Fatal(err)
	}
	if filter.Filled() != Unknown || filter.Filled().Not() != Unknown {
		t.Errorf("an incomplete handler's filter is %v and its negation %v, want unknown both", filter.Filled(), filter.Filled().Not())
	}
	body, _ := handler.One("body")
	statements, _ := body.In("statements")
	if statements.Filled() != False || statements.Filled().Not() != True {
		t.Errorf("an empty body's statements are %v, want false", statements.Filled())
	}
}

func TestTruthIsThreeValued(t *testing.T) {
	for _, each := range []struct {
		got, want Truth
		says      string
	}{
		{False.And(Unknown), False, "proven false rules out a conjunction"},
		{True.And(Unknown), Unknown, "true and unknown is unknown"},
		{True.Or(Unknown), True, "proven true satisfies a disjunction"},
		{False.Or(Unknown), Unknown, "false or unknown is unknown"},
		{Unknown.Not(), Unknown, "negating unknown is unknown"},
		{TruthOf(true).And(TruthOf(true)), True, "true and true is true"},
	} {
		if each.got != each.want {
			t.Errorf("%s: got %v", each.says, each.got)
		}
	}
}

func TestTheIndexesFindByKindModuleAndScope(t *testing.T) {
	codebase := versionFiveCodebase(t, versionFiveReader)
	if count := codebase.WhereKind("function").Count(); count != 2 {
		t.Errorf("the kind index finds %d functions, want 2", count)
	}
	if files := codebase.InModule("app.reader"); len(files) != 1 {
		t.Errorf("the module index finds %d files for app.reader, want 1", len(files))
	}
	name := codebase.WhereKind("reference").First()
	if scope := name.EnclosingScope(); scope.Node() == nil || scope.Node().Form != "lambda" {
		t.Errorf("a name inside a closure is scoped to %v, want the closure", scope.Kind())
	}
	if module := name.EnclosingModule(); module.Name() != "app.reader" {
		t.Errorf("the enclosing module is %q", module.Name())
	}
	read := codebase.WhereKind("function").First()
	for _, below := range read.OwnDescendants() {
		if below.Kind() == "reference" {
			t.Error("a function's own descendants reach into the closure inside it")
		}
	}
}

func TestAFilePurposeIsDerivedOnceFromItsEvidence(t *testing.T) {
	codebase := versionFiveCodebase(t, versionFiveReader)
	file := codebase.Files()[0]
	if purpose := file.Purpose(); purpose != TestFixture {
		t.Errorf("a file under tests/fixtures is %q, want a test fixture", purpose)
	}
	for path, want := range map[string]Purpose{
		"app/reader.py":      Ordinary,
		"app/test_reader.py": Testing,
		"tests/reader.py":    Testing,
		"pkg/testdata/in.py": TestFixture,
		"app/conftest.py":    Testing,
	} {
		if got := purposeOf(contract.Python, "/src/"+path, path, false); got != want {
			t.Errorf("%s is %q, want %q", path, got, want)
		}
	}
	if got := purposeOf(contract.CSharp, "/src/Orders/Reader.cs", "Orders/Reader.cs", true); got != Testing {
		t.Errorf("a file the bridge marks as a test project's is %q, want a test", got)
	}
}
