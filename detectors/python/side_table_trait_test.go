package python

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func TestASideTableOfInstanceNamesIsFound(t *testing.T) {
	for name, source := range map[string]string{
		"a membership test": `from dataclasses import dataclass


@dataclass(frozen=True)
class Tag:
    name: str
    runs: str


TAGS = (Tag("reply", "a"), Tag("log", "b"), Tag("todo", "c"))
QUIET = {"reply", "log"}


def said(name: str) -> bool:
    return name not in QUIET
`,
	} {
		t.Run(name, func(t *testing.T) {
			found := SideTableTraitDetector{}.Find(pythontest.FromSource(t, map[string]string{"shop/mod.py": source}))

			if len(found) != 1 {
				t.Fatalf("found %d side tables, want 1: %v", len(found), locations(found))
			}
		})
	}
}

func TestACollectionThatIsNotASideTableIsLeftAlone(t *testing.T) {
	for name, source := range map[string]string{
		"ordinary vocabulary naming no instance": `from dataclasses import dataclass


@dataclass(frozen=True)
class Tag:
    name: str
    runs: str


TAGS = (Tag("reply", "a"), Tag("log", "b"))
SHELL_TOOLS = ("exec", "shell", "bash")


def shelled(name: str) -> bool:
    return name in SHELL_TOOLS
`,
		"derived from the registry, which is the fix": `from dataclasses import dataclass


@dataclass(frozen=True)
class Tag:
    name: str
    runs: str
    said_in_chat: bool = True


TAGS = (Tag("reply", "a", said_in_chat=False), Tag("todo", "c"))
QUIET = {tag.name for tag in TAGS if not tag.said_in_chat}


def said(name: str) -> bool:
    return name not in QUIET
`,
		"walked to act on each, which a caller choosing what to act on also does": `from dataclasses import dataclass


@dataclass(frozen=True)
class Resource:
    name: str
    title: str


RESOURCES = (Resource("rule", "Rules"), Resource("doc", "Docs"), Resource("tool", "Tools"))
HELD = ("rule", "doc")


def shown(board) -> list:
    rows = []
    for name in HELD:
        rows.append(board.rows(name))
    return rows
`,
		"held but never used as a handle": `from dataclasses import dataclass


@dataclass(frozen=True)
class Tag:
    name: str
    runs: str


TAGS = (Tag("reply", "a"), Tag("log", "b"))
ORDER = ("reply", "log")
`,
	} {
		t.Run(name, func(t *testing.T) {
			found := SideTableTraitDetector{}.Find(pythontest.FromSource(t, map[string]string{"shop/mod.py": source}))

			if len(found) != 0 {
				t.Errorf("flagged %d, want none: %v", len(found), locations(found))
			}
		})
	}
}

func locations(found []engine.Match) []string {
	where := make([]string, 0, len(found))
	for _, match := range found {
		where = append(where, match.Location())
	}

	return where
}
