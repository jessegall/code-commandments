package shared

import (
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// QueryInLoop finds a database query that runs on each iteration.
type QueryInLoop struct{}

func (QueryInLoop) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereCall().Where(engine.Match.ExecutesQueryInLoop).Get()
}

func (QueryInLoop) Sin() Text {
	return Text{Name: "query-in-loop", Skill: skills.Flow{}, Description: "A database query runs on each iteration", Rule: "Load the rows before iterating over them.", Suggestion: "Fetch the required rows in one query before the loop."}
}

func (QueryInLoop) CrossFile() {}
