package python_test

import (
	"strings"
	"testing"

	detectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// An invented default fills a required slot with a value nobody chose and loses the question. A default at a PARSE
// is the opposite: it is the parse's own reading of what the text leaves out, which the absence discipline calls a
// choice rather than an invention — a time written `3pm` has no minutes, and minute 0 is what `3pm` means. Reports
// #816 and #817 were both of that kind, so these hold the rule to firing on the invention and not on the parse.

// found are the lines the invented-default rule flags in the sources.
func found(t *testing.T, sources map[string]string) string {
	t.Helper()
	codebase := pythontest.FromSource(t, sources)

	var lines []string
	for _, match := range (detectors.InventedDefaultDetector{}).Find(codebase) {
		lines = append(lines, match.Location())
	}

	return strings.Join(lines, " ")
}

// TestADefaultAtAParseIsTheParsesOwnReading holds the shape report #816 named: a group of a regular expression
// that the text leaves out. It is no invention, because there was no value to lose — the pattern itself says the
// group may be absent, and what the parse reads for it is the meaning of the text.
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

// TestADefaultOnAPayloadTheCodeHasNarrowedIsStillInvented holds the line between the two. Once the code has said
// what the payload IS, it is holding a dict like any other, and a missing field read as 0.0 is a fact nobody
// reported told apart from nothing at all — which is the question the discipline asks it to keep. That was report
// #817, and the answer it is owed is the cleaner design: a cost the provider did not report is `float | None`,
// said once where the row is built.
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

// TestAnInventedDefaultIsStillFound holds the rule itself, so the exemption above cannot be read as a way out of
// it: a value the project's own types say is there, filled with a blank nobody chose, is the sin whatever else is
// in the file.
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
