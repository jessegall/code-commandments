package python_test

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// A type in quotes is how Python writes one that is not defined yet, which is ordinary wherever two modules would
// import each other, and the bridge read the quotes' contents as ONE class name: `"Money | None"` was a class
// called `Money | None`, neither a union nor nullable, so every rule about absence, about a type's shape and
// about its element missed every forward reference in silence. It reads as the type it spells now.

// TestAQuotedAnnotationIsTheTypeItSpells holds the quoted form to answering exactly what the plain form answers,
// its own text aside, which is the only difference a reader should see.
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

	// The two differ only in the text, which is what the source wrote.
	if quoted, plain := without(types["quoted"], `"text":"\"Money | None\"",`), without(types["plain"], `"text":"Money | None",`); quoted != plain {
		t.Errorf("a quoted annotation reads as\n %s\nand the plain one as\n %s", quoted, plain)
	}

	if !has(types["quoted"], `"nullable":true`) || !has(types["quoted"], `"kind":"union"`) {
		t.Errorf("a quoted Money | None is %s", types["quoted"])
	}

	// A subscripted forward reference keeps its argument, since the whole annotation is parsed and not only its
	// first name.
	if !has(types["listed"], `"name":"list"`) || !has(types["listed"], `shop.till.Money`) {
		t.Errorf("a quoted list[Money] is %s", types["listed"])
	}

	// And a reference that parses as nothing stays the name it looks like, rather than failing the read.
	if !has(types["nonsense"], `"kind":"named"`) {
		t.Errorf("a quoted phrase that is no type is %s", types["nonsense"])
	}
}

// without is the text with that part taken out, so two readings can be compared by everything else.
func without(read, part string) string {
	for at := 0; at+len(part) <= len(read); at++ {
		if read[at:at+len(part)] == part {
			return read[:at] + read[at+len(part):]
		}
	}

	return read
}

// has says whether the reading holds that part.
func has(read, part string) bool {
	return without(read, part) != read
}
