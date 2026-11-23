package contract

import (
	"bytes"
	"errors"
	"strings"
	"testing"
)

const header = `{"header": {"contract": "tree", "version": 5, "language": "php", "bridge": {"name": "php-bridge", "version": "8.0.0", "reads": true}, "roots": ["/src"]}}`

const handler = `{"file": {"path": "/src/Reader.php", "language": "php", "errors": 0, "test": true, "module": "App", "reads": ["/src/composer.lock"],
"resolver": {"tool": "php-parser", "ran": true},
"root": {"id": 0, "kind": "file", "span": [0, 60, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 60, 1], "name": "App", "parts": {"statements": [
  {"id": 2, "kind": "try", "span": [6, 60, 1], "parts": {
    "body": {"id": 3, "kind": "block", "span": [10, 20, 1], "parts": {"statements": []}},
    "handlers": [{"id": 4, "kind": "handler", "span": [21, 60, 1], "catchForm": "typed", "parts": {
      "caughtTypes": [{"id": 5, "kind": "type-expression", "span": [28, 39, 1], "form": "named", "refers": "App\\ReadFailure", "resolvedType": 0}],
      "binding": {"id": 6, "kind": "binding", "span": [40, 42, 1], "name": "e"},
      "body": {"id": 7, "kind": "block", "span": [44, 60, 1], "parts": {"statements": []}}}}]}}]}}]}},
"comments": [{"id": 0, "form": "line", "text": "// read", "span": [0, 5, 1], "attached": 2}],
"types": [{"text": "App\\ReadFailure", "kind": "named", "name": "App\\ReadFailure", "origin": "compiler"}]}}`

const trailer = `{"trailer": {"files": 1}}`

func stream(lines ...string) string {
	var joined []string
	for _, line := range lines {
		joined = append(joined, strings.Join(strings.Fields(line), " "))
	}

	return strings.Join(joined, "\n") + "\n"
}

func TestAVersionFiveStreamReadsWithItsRolesFactsAndFileFacts(t *testing.T) {
	read, err := ReadAll(strings.NewReader(stream(header, handler, trailer)))
	if err != nil {
		t.Fatal(err)
	}
	file := read.Files[0]
	if !file.Test || file.Module != "App" || len(file.Reads) != 1 || file.Resolver.Tool != "php-parser" || !read.Header.Bridge.Reads {
		t.Errorf("the file facts read as test %v, module %q, reads %v, resolver %v, bridge reads %v", file.Test, file.Module, file.Reads, file.Resolver, read.Header.Bridge.Reads)
	}
	caught, _ := file.Node(4)
	types, answer := caught.Role("caughtTypes")
	if answer != Present || types[0].Refers != `App\ReadFailure` || types[0].Resolved == nil || types[0].Resolved.Name != `App\ReadFailure` {
		t.Errorf("the handler's caught types read %v, %v", types, answer)
	}
	body, _ := caught.Child("body")
	if statements, answer := body.Role("statements"); len(statements) != 0 || answer != KnownEmpty {
		t.Errorf("an empty body's statements answer %v, want known-empty", answer)
	}
	if _, answer := caught.Role("filter"); answer != KnownEmpty {
		t.Errorf("an absent filter answers %v, want known-empty", answer)
	}
	if _, answer := caught.Role("then"); answer != Inapplicable {
		t.Errorf("a handler's then answers %v, want inapplicable", answer)
	}
	if parent, _ := types[0].Parent(); parent != caught || types[0].InRole() != "caughtTypes" {
		t.Errorf("a caught type's parent is %v in role %q", parent, types[0].InRole())
	}
	if !caught.Is("handler") || !file.Root.Is("file") {
		t.Error("a node is not of its own kind")
	}
}

func TestAnIncompleteNodeAnswersUnknownAndItsSiblingsStayReadable(t *testing.T) {
	recovered := strings.Replace(handler, `"kind": "handler", "span": [21, 60, 1], "catchForm": "typed"`, `"kind": "handler", "span": [21, 60, 1], "catchForm": "typed", "incomplete": true`, 1)
	read, err := ReadAll(strings.NewReader(stream(header, recovered, trailer)))
	if err != nil {
		t.Fatal(err)
	}
	caught, _ := read.Files[0].Node(4)
	if _, answer := caught.Role("filter"); answer != Unknown {
		t.Errorf("an incomplete handler's absent filter answers %v, want unknown", answer)
	}
	tried, _ := read.Files[0].Node(3)
	if _, answer := tried.Role("statements"); answer != KnownEmpty {
		t.Errorf("the try body beside it answers %v, want known-empty", answer)
	}
}

func TestAFileWhoseResolverDidNotRunKnowsNoCompilerFact(t *testing.T) {
	unrun := strings.Replace(handler, `"resolver": {"tool": "php-parser", "ran": true}`, `"resolver": {"tool": "php-parser", "ran": false}`, 1)
	read, err := ReadAll(strings.NewReader(stream(header, unrun, trailer)))
	if err != nil {
		t.Fatal(err)
	}
	if file := read.Files[0]; file.Knows("refers") || file.Knows("resolvedType") || !file.Knows("name") {
		t.Errorf("a file whose resolver did not run knows refers %v, resolvedType %v, name %v", file.Knows("refers"), file.Knows("resolvedType"), file.Knows("name"))
	}
}

func TestAStreamOfAnotherVersionIsRefusedNamingTheStreamAndBothVersions(t *testing.T) {
	old := strings.Replace(header, `"version": 5`, `"version": 4`, 1)
	_, err := ReadAll(strings.NewReader(stream(old, handler, trailer)))
	var refused *VersionRefused
	if !errors.As(err, &refused) || !strings.Contains(err.Error(), "php-bridge stream is written in contract version 4, and this reader reads only version 5") {
		t.Errorf("a version 4 stream was answered %v", err)
	}
}

func TestRefusesAStreamThatBreaksTheContract(t *testing.T) {
	for name, lines := range map[string][]string{
		"no header":            {handler, trailer},
		"no trailer":           {header, handler},
		"a short trailer":      {header, handler, `{"trailer": {"files": 2}}`},
		"a field it never had": {header, strings.Replace(handler, `"kind": "binding",`, `"kind": "binding", "field": "var",`, 1), trailer},
		"a skipped number":     {header, strings.Replace(handler, `"id": 7,`, `"id": 9,`, 1), trailer},
		"a root not a file":    {header, strings.Replace(handler, `"root": {"id": 0, "kind": "file"`, `"root": {"id": 0, "kind": "module"`, 1), trailer},
	} {
		if _, err := ReadAll(strings.NewReader(stream(lines...))); err == nil {
			t.Errorf("%s was read", name)
		}
	}
}

func TestTheValidatorRefusesAKeywordItWouldIgnore(t *testing.T) {
	if err := known(map[string]any{"format": "uri"}, ""); err == nil {
		t.Error("a schema keyword the validator does not apply was accepted")
	}
}

func TestAStreamWrittenBackReadsTheSameWithItsTypesStillShared(t *testing.T) {
	read, err := ReadAll(strings.NewReader(stream(header, handler, trailer)))
	if err != nil {
		t.Fatal(err)
	}
	var written bytes.Buffer
	if err := Write(&written, read); err != nil {
		t.Fatal(err)
	}
	again, err := ReadAll(&written)
	if err != nil {
		t.Fatalf("the stream written back does not read: %v\n%s", err, written.String())
	}
	if len(again.Files[0].Nodes()) != 8 {
		t.Errorf("the stream written back holds %d nodes, want 8", len(again.Files[0].Nodes()))
	}
	caught, _ := again.Files[0].Node(5)
	if caught.Resolved == nil || caught.Resolved.Name != `App\ReadFailure` {
		t.Errorf("the caught type's resolved type came back as %v", caught.Resolved)
	}
}

func TestALineTheSchemaRefusesEndsTheReadNamingItsFileKindAndFact(t *testing.T) {
	refused := strings.Replace(handler, `"kind": "try",`, `"kind": "try", "form": "guarded",`, 1)

	_, err := ReadAll(strings.NewReader(stream(header, refused, trailer)))

	var line *RefusedLine
	if !errors.As(err, &line) {
		t.Fatalf("a refused line reads as %v, not as a refused line", err)
	}
	for _, named := range []string{"/src/Reader.php", "try", `"form"`} {
		if !strings.Contains(err.Error(), named) {
			t.Errorf("the refusal %q does not name %s", err, named)
		}
	}
}
