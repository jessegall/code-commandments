package shared

import (
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// ListMirrorsFolder finds a list that repeats the classes already declared in its folders.
type ListMirrorsFolder struct{}

func (ListMirrorsFolder) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.ListsEveryClassOfItsFolders).
		Reject(engine.Match.IsTest).
		Get()
}

func (ListMirrorsFolder) Sin() Text {
	return Text{
		Name:        "list-mirrors-folder",
		Skill:       skills.Duplication{},
		Description: "A hand-written class list repeats every concrete class of one type in its folders",
		Rule:        "Keep the declarations as the source of truth for the classes that belong to the group.",
		Suggestion:  "Discover the implementations from their shared type, or let each class register itself.",
	}
}

func (ListMirrorsFolder) CrossFile() {}
