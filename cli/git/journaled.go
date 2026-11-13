package git

import (
	"os"

	"github.com/jessegall/code-commandments/walk"
)

// journaled is what the journal says about the checkout a run was started in, from the variables it hands every alias run: the checkout, the project it belongs to, and the commit it was cut from.
type journaled struct {
	checkout string
	project  string
	base     string
}

func journalSaysOf(path string) (journaled, bool) {
	said := journaled{
		checkout: os.Getenv("JOURNAL_CHECKOUT_ROOT"),
		project:  os.Getenv("JOURNAL_PROJECT_ROOT"),
		base:     os.Getenv("JOURNAL_BASE_COMMIT"),
	}

	if said.checkout == "" || said.project == "" {
		return journaled{}, false
	}

	top := Root(path)
	if top == "" && walk.Within(walk.Real(said.checkout), walk.Real(path)) {
		return said, true
	}

	if top == "" || walk.Real(top) != walk.Real(said.checkout) {
		return journaled{}, false
	}

	return said, true
}

func (j journaled) isTheProject() bool {
	return walk.Real(j.checkout) == walk.Real(j.project)
}
