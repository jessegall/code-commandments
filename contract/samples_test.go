package contract

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"
)

// fixtures is where a sample's /fixtures/ paths live in this repository.
const fixtures = "../tests/Fixtures/"

func TestEverySampleReadsAndPointsIntoItsFixture(t *testing.T) {
	samples, err := filepath.Glob("samples/*.jsonl")
	if err != nil || len(samples) == 0 {
		t.Fatalf("no samples found: %v", err)
	}
	for _, sample := range samples {
		t.Run(filepath.Base(sample), func(t *testing.T) {
			handle, err := os.Open(sample)
			if err != nil {
				t.Fatal(err)
			}
			defer handle.Close()
			stream, err := ReadAll(handle)
			if err != nil {
				t.Fatalf("the sample breaks the contract: %v", err)
			}
			for _, file := range stream.Files {
				source, err := os.ReadFile(fixtures + strings.TrimPrefix(file.Path, "/fixtures/"))
				if err != nil {
					t.Fatalf("%s names no fixture: %v", file.Path, err)
				}
				pointsInto(t, file, source)
			}
		})
	}
}

func pointsInto(t *testing.T, file *File, source []byte) {
	t.Helper()
	for _, node := range file.Nodes() {
		if node.Span.End > len(source) {
			t.Errorf("node %d ends at %d, past the file's %d bytes", node.ID, node.Span.End, len(source))
		}
		if line := lineAt(source, node.Span.Start); line != node.Span.Line {
			t.Errorf("node %d starts on line %d, not %d", node.ID, line, node.Span.Line)
		}
		parent, ok := node.Parent()
		if ok && (node.Span.Start < parent.Span.Start || node.Span.End > parent.Span.End) {
			t.Errorf("node %d %v lies outside its parent %d %v", node.ID, node.Span, parent.ID, parent.Span)
		}
	}
	for _, comment := range file.Comments {
		if comment.Span.End > len(source) {
			t.Fatalf("comment %d ends past the file", comment.ID)
		}
		if written := string(source[comment.Span.Start:comment.Span.End]); written != comment.Text {
			t.Errorf("comment %d's span holds %q, not its text %q", comment.ID, written, comment.Text)
		}
	}
}

func lineAt(source []byte, offset int) int {
	return strings.Count(string(source[:offset]), "\n") + 1
}

// shows are facts COVERAGE.md says a sample carries, each as a test over one node.
var shows = map[string]map[string]func(*Node) bool{
	"php.jsonl": {
		"a foreach loop":    func(n *Node) bool { return n.Kind == "Stmt_Foreach" && n.Answers("loop") },
		"a for loop's step": func(n *Node) bool { return slices.Contains(n.Flags, "step") && n.Field == "loop" },
		"a try/catch":       func(n *Node) bool { return n.Kind == "Stmt_TryCatch" },
		"a catch and its type": func(n *Node) bool {
			return n.Kind == "Stmt_Catch" && n.Declared != nil && n.Declared.Name == "Throwable"
		},
		"an assignment":         func(n *Node) bool { return n.Kind == "Expr_Assign" && n.Operator == "=" },
		"a compound assignment": func(n *Node) bool { return n.Kind == "Expr_AssignOp_Plus" && n.Operator == "+=" },
		"a promoted parameter":  func(n *Node) bool { return n.Kind == "Param" && slices.Contains(n.Flags, "promoted") },
		"a resolved class name": func(n *Node) bool { return n.Kind == "Name_FullyQualified" && n.Refers != "" },
		"a nullable-sugar type": func(n *Node) bool { return n.Kind == "NullableType" && slices.Contains(n.Flags, "nullable-sugar") },
		"a property hook":       func(n *Node) bool { return n.Kind == "PropertyHook" },
		"a class constant":      func(n *Node) bool { return n.Kind == "Stmt_ClassConst" },
		"a trait use":           func(n *Node) bool { return n.Kind == "Stmt_TraitUse" },
		"a ternary":             func(n *Node) bool { return n.Kind == "Expr_Ternary" && n.Answers("branch") },
		"a match":               func(n *Node) bool { return n.Kind == "Expr_Match" && n.Answers("branch") },
		"an elseif":             func(n *Node) bool { return n.Kind == "Stmt_ElseIf" },
		"a switch":              func(n *Node) bool { return n.Kind == "Stmt_Switch" && n.Answers("branch") },
		"a self name":           func(n *Node) bool { return n.Kind == "Name" && n.Name == "self" && n.Refers == "" },
	},
	"python.jsonl": {
		"an import": func(n *Node) bool { return n.Kind == "Import" && n.Answers("import") },
		"a relative import": func(n *Node) bool {
			return n.Kind == "ImportFrom" && n.Extras != nil && n.Extras.Python != nil && n.Extras.Python.Level > 0
		},
		"an if":                func(n *Node) bool { return n.Kind == "If" && n.Answers("branch") },
		"a for loop":           func(n *Node) bool { return n.Kind == "For" && n.Answers("loop") },
		"a while loop":         func(n *Node) bool { return n.Kind == "While" && n.Answers("loop") },
		"a match":              func(n *Node) bool { return n.Kind == "Match" && n.Answers("branch") },
		"a raise":              func(n *Node) bool { return n.Kind == "Raise" && n.Answers("bail-out") },
		"a mypy-resolved type": func(n *Node) bool { return n.Resolved != nil && n.Resolved.Origin == "compiler" },
		"an f-string":          func(n *Node) bool { return n.Kind == "JoinedStr" && n.Literal == "interpolated" },
		"a decorator":          func(n *Node) bool { return n.Field == "decorator_list" },
		"an import alias": func(n *Node) bool {
			return n.Kind == "alias" && n.Extras != nil && n.Extras.Python != nil && n.Extras.Python.As != ""
		},
		"a global": func(n *Node) bool {
			return n.Kind == "Global" && n.Extras != nil && n.Extras.Python != nil && len(n.Extras.Python.Names) > 0
		},
		"a named except":       func(n *Node) bool { return n.Kind == "ExceptHandler" && n.Name != "" },
		"a from-import module": func(n *Node) bool { return n.Kind == "ImportFrom" && n.Name != "" },
	},
	"csharp.jsonl": {
		"a forgiven nullable":     func(n *Node) bool { return n.Extras != nil && n.Extras.CSharp != nil && n.Extras.CSharp.ForgivesNull },
		"an inherited member":     func(n *Node) bool { return n.Inherited && n.Symbol != "" },
		"a resolved call target":  func(n *Node) bool { return n.Target != nil && n.Target.Symbol != "" },
		"a compile-time constant": func(n *Node) bool { return n.Constant },
		"a for loop's step":       func(n *Node) bool { return slices.Contains(n.Flags, "step") },
		"a written base list":     func(n *Node) bool { return n.Kind == "BaseList" },
	},
	"typescript.jsonl": {
		"a resolved call target": func(n *Node) bool { return n.Target != nil && n.Target.Symbol != "" },
		"a declared return type": func(n *Node) bool { return n.Returns != nil },
		"an optional field":      func(n *Node) bool { return slices.Contains(n.Flags, "optional") },
	},
	"vue.jsonl": {
		"a v-for alias":         func(n *Node) bool { return n.Field == "alias" },
		"a v-for iterable":      func(n *Node) bool { return n.Field == "iterable" },
		"a v-if":                func(n *Node) bool { return directive(n) == "if" },
		"a v-else-if":           func(n *Node) bool { return directive(n) == "else-if" },
		"a v-else":              func(n *Node) bool { return directive(n) == "else" },
		"a shorthand directive": func(n *Node) bool { return n.Kind == "Directive" && slices.Contains(n.Flags, "shorthand") },
		"an interpolation":      func(n *Node) bool { return n.Kind == "Interpolation" },
		"a script block":        func(n *Node) bool { return n.Kind == "Block" && n.Name == "script" },
	},
}

func directive(n *Node) string {
	if n.Kind != "Directive" || n.Extras == nil || n.Extras.Vue == nil || n.Extras.Vue.Directive == nil {
		return ""
	}

	return n.Extras.Vue.Directive.Name
}

func TestThePythonSampleMarksAFileThatOnlyInforms(t *testing.T) {
	handle, err := os.Open("samples/python.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	defer handle.Close()
	stream, err := ReadAll(handle)
	if err != nil {
		t.Fatal(err)
	}
	if !slices.ContainsFunc(stream.Files, func(file *File) bool { return file.Context }) {
		t.Fatal("no file in the Python sample is marked context")
	}
}

func TestEverySampleShowsWhatCoverageSaysItShows(t *testing.T) {
	for sample, facts := range shows {
		handle, err := os.Open("samples/" + sample)
		if err != nil {
			t.Fatal(err)
		}
		stream, err := ReadAll(handle)
		handle.Close()
		if err != nil {
			t.Fatalf("%s: %v", sample, err)
		}
		for fact, holds := range facts {
			if !anyNode(stream, holds) {
				t.Errorf("%s shows no %s", sample, fact)
			}
		}
	}
}

func anyNode(stream *Stream, holds func(*Node) bool) bool {
	for _, file := range stream.Files {
		if slices.ContainsFunc(file.Nodes(), holds) {
			return true
		}
	}

	return false
}
