package python_test

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func TestAQuotedAnnotationIsTheTypeItSpells(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/till.py": `class Money:
    pass


def quoted() -> "Money | None":
    return None


def plain() -> Money | None:
    return None


def listed() -> "list[Money]":
    return []


def nonsense() -> "not a type at all":
    return None
`,
	})

	types := map[string]string{}
	for _, function := range codebase.WhereFunction().Get() {
		written, err := json.Marshal(function.Typed())
		if err != nil {
			t.Fatal(err)
		}
		types[function.Name()] = string(written)
	}

	if types["quoted"] == "" || types["plain"] == "" {
		t.Fatalf("the functions are not in the codebase: %v", types)
	}

	if quoted, plain := without(types["quoted"], `"text":"\"Money | None\"",`), without(types["plain"], `"text":"Money | None",`); quoted != plain {
		t.Errorf("a quoted annotation reads as\n %s\nand the plain one as\n %s", quoted, plain)
	}

	if !has(types["quoted"], `"nullable":true`) || !has(types["quoted"], `"kind":"union"`) {
		t.Errorf("a quoted Money | None is %s", types["quoted"])
	}

	if !has(types["listed"], `"name":"list"`) || !has(types["listed"], `shop.till.Money`) {
		t.Errorf("a quoted list[Money] is %s", types["listed"])
	}

	if !has(types["nonsense"], `"kind":"named"`) {
		t.Errorf("a quoted phrase that is no type is %s", types["nonsense"])
	}
}

func without(read, part string) string {
	for at := 0; at+len(part) <= len(read); at++ {
		if read[at:at+len(part)] == part {
			return read[:at] + read[at+len(part):]
		}
	}

	return read
}

func has(read, part string) bool {
	return without(read, part) != read
}
