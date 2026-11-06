package python_test

import (
	"strings"
	"testing"

	detectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func found(t *testing.T, sources map[string]string) string {
	t.Helper()
	codebase := pythontest.FromSource(t, sources)

	var lines []string
	for _, match := range (detectors.InventedDefaultDetector{}).Find(codebase) {
		lines = append(lines, match.Location())
	}

	return strings.Join(lines, " ")
}

func TestADefaultAtAParseIsTheParsesOwnReading(t *testing.T) {
	said := found(t, map[string]string{
		"shop/__init__.py": "",
		"shop/parse.py": `import re

WRITTEN = re.compile(r"(\d+)(?::(\d+))?(am|pm)")


def minute_of(written: str) -> int:
    match = WRITTEN.match(written)
    if match is None:
        raise ValueError(written)

    return int(match.group(2) or 0)


`,
	})

	if said != "" {
		t.Errorf("a default at a parse was flagged as invented: %s", said)
	}
}

func TestADefaultOnAPayloadTheCodeHasNarrowedIsStillInvented(t *testing.T) {
	said := found(t, map[string]string{
		"shop/__init__.py": "",
		"shop/rows.py": `import json


def cost_of(line: str) -> float:
    fields: object = json.loads(line)
    if not isinstance(fields, dict):
        raise ValueError(line)

    return float(fields.get("total_cost_usd") or 0.0)
`,
	})

	if said == "" {
		t.Error("a blank read back from a payload the code has narrowed to a dict was not flagged")
	}
}

func TestAnInventedDefaultIsStillFound(t *testing.T) {
	said := found(t, map[string]string{
		"shop/__init__.py": "",
		"shop/orders.py": `from dataclasses import dataclass


@dataclass(frozen=True)
class Customer:
    name: str
    note: str | None


def greet(customer: Customer) -> str:
    return welcome(customer.note or "")


def welcome(note: str) -> str:
    return note
`,
	})

	if said == "" {
		t.Error("a blank invented for a field of the project's own data was not flagged")
	}
}
