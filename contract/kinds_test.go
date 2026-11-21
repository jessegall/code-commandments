package contract

import (
	"os"
	"regexp"
	"slices"
	"strings"
	"testing"
)

func TestTheSchemaKindsAreEveryKindTheContractDocuments(t *testing.T) {
	written, err := os.ReadFile("CONTRACT.md")
	if err != nil {
		t.Fatal(err)
	}
	section := string(written)[strings.Index(string(written), "## Kinds"):strings.Index(string(written), "## Derived reads")]
	var documented []string
	for _, found := range regexp.MustCompile("(?m)^- \\*\\*`([a-z-]+)`\\*\\*(?: and \\*\\*`([a-z-]+)`\\*\\*)?").FindAllStringSubmatch(section, -1) {
		documented = append(documented, found[1])
		if found[2] != "" {
			documented = append(documented, found[2])
		}
	}
	slices.Sort(documented)

	if !slices.Equal(documented, Kinds()) {
		t.Errorf("CONTRACT.md documents %v\nand the schema declares %v", documented, Kinds())
	}
}

func TestTheSchemaRefusesARoleAKindDoesNotDeclareByName(t *testing.T) {
	if _, err := RoleArity("handler", "body"); err != nil {
		t.Fatalf("a handler's body was refused: %v", err)
	}

	_, err := RoleArity("handler", "then")
	if err == nil || !strings.Contains(err.Error(), `a handler has no role "then"`) || !strings.Contains(err.Error(), "caughtTypes") {
		t.Errorf("reading a handler's then was answered %v, want it refused by name with the roles a handler has", err)
	}
}

func TestTheSchemaKindsRefuseANodeThatFillsAnUndeclaredRole(t *testing.T) {
	line := `{"file": {"path": "/a.php", "language": "php", "errors": 0, "root": {"id": 0, "kind": "file", "span": [0, 9, 1],
		"parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 9, 1], "parts": {"statements": [
			{"id": 2, "kind": "handler", "span": [0, 9, 1], "catchForm": "typed", "parts": {"body": {"id": 3, "kind": "block", "span": [0, 9, 1]}, "then": {"id": 4, "kind": "block", "span": [0, 9, 1]}}}]}}]}},
		"comments": []}}`

	err := Validate([]byte(strings.ReplaceAll(line, "\n", "")))
	if err == nil || !strings.Contains(err.Error(), `a handler has no role "then"`) {
		t.Errorf("a handler filling then was answered %v, want it refused by name", err)
	}
}

func TestTheSchemaKindsAcceptTheSwallowedCatchOfTheContract(t *testing.T) {
	line := `{"file": {"path": "/a.php", "language": "php", "errors": 0, "resolver": {"tool": "php-parser", "ran": true},
		"root": {"id": 0, "kind": "file", "span": [0, 40, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 40, 1], "parts": {"statements": [
			{"id": 2, "kind": "try", "span": [0, 40, 1], "parts": {
				"body": {"id": 3, "kind": "block", "span": [4, 10, 1], "parts": {"statements": []}},
				"handlers": [{"id": 4, "kind": "handler", "span": [11, 40, 1], "catchForm": "typed", "parts": {
					"caughtTypes": [{"id": 5, "kind": "type-expression", "span": [18, 29, 1], "form": "named", "refers": "App\\ReadFailure"}],
					"binding": {"id": 6, "kind": "binding", "span": [30, 32, 1], "name": "e"},
					"body": {"id": 7, "kind": "block", "span": [34, 40, 1], "parts": {"statements": []}}}}]}}]}}]}},
		"comments": []}}`

	if err := Validate([]byte(strings.ReplaceAll(strings.ReplaceAll(line, "\n", ""), "\t", ""))); err != nil {
		t.Errorf("the contract's swallowed catch was refused: %v", err)
	}
}

func TestTheSchemaKindsNeedARequiredFactAndRefuseAForeignOne(t *testing.T) {
	missing := `{"file": {"path": "/a.php", "language": "php", "errors": 0, "root": {"id": 0, "kind": "file", "span": [0, 9, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 9, 1], "parts": {"statements": [{"id": 2, "kind": "handler", "span": [0, 9, 1], "parts": {"body": {"id": 3, "kind": "block", "span": [0, 9, 1]}}}]}}]}}, "comments": []}}`
	if err := Validate([]byte(missing)); err == nil || !strings.Contains(err.Error(), `needs its "catchForm"`) {
		t.Errorf("a handler without its catchForm was answered %v", err)
	}

	foreign := `{"file": {"path": "/a.php", "language": "php", "errors": 0, "root": {"id": 0, "kind": "file", "span": [0, 9, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 9, 1], "parts": {"statements": [{"id": 2, "kind": "block", "span": [0, 9, 1], "catchForm": "typed"}]}}]}}, "comments": []}}`
	if err := Validate([]byte(foreign)); err == nil || !strings.Contains(err.Error(), `a block carries no fact "catchForm"`) {
		t.Errorf("a block carrying a catchForm was answered %v", err)
	}
}

func TestTheSchemaKindsCategoriesAreSetsOfKinds(t *testing.T) {
	if !InCategory("throw", "exit") || InCategory("call", "exit") {
		t.Errorf("the exit category reads %v", Category("exit"))
	}
	if Provenance("refers") != "compiler" || Provenance("name") != "parser" {
		t.Errorf("refers comes from %q and name from %q", Provenance("refers"), Provenance("name"))
	}
}
