package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// BlockedAwait is a blocking call made inside an async def — the thread it stops is the event loop, so everything else waiting on it stops too.
type BlockedAwait struct{}

func init() {
	sins.Register(catalog.Python, BlockedAwait{})
}

// Definition is what the sin states about itself.
func (BlockedAwait) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-blocked-await",
		Skill:       skills.FixAtTheSource{},
		Description: "`time.sleep`, `requests.get` or `subprocess.run` inside an `async def` — the thread it stops is the event loop, so every coroutine waiting on it stops too",
		Rule:        "A coroutine never stops its own thread: await the asynchronous counterpart, or hand the blocking work to a thread.",
		Suggestion:  "Use the asynchronous form and `await` it (`asyncio.sleep`, an async client), or `await asyncio.to_thread(...)` when only a blocking call exists. Stopping the loop here is what makes the slowdown appear under load and nowhere else.",
	}
}
