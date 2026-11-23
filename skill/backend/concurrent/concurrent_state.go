package concurrent

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed concurrent_state.md
	concurrentStateBody string
)

// ConcurrentState teaches: state shared across requests/workers (`::for($id): Concurrent<self>`).
type ConcurrentState struct{}

func init() {
	skill.Register(catalog.PHP, ConcurrentState{})
}

func (ConcurrentState) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "php/concurrent-state",
		Tier:      skill.KeepInMind,
		Order:     14,
		Title:     "Concurrent state — a plain object behind `::for()`",
		Trigger:   "How to model state shared across processes (web request ↔ queue worker ↔ cron) — a plain domain class with behaviour methods plus a static `::for($id): Concurrent<self>` factory that owns the cache key, default, and TTL (jessegall/concurrent). Read this FIRST whenever you reach for `Cache::get/put` with a hand-built key, a static/global for cross-request state, a polled status / progress / counter / pointer shared between a request and a worker, or `new Concurrent(...)`.",
		Summary:   "state shared across requests/workers (`::for($id): Concurrent<self>`).",
		Body:      concurrentStateBody,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "shared/exceptions", Note: "\"the handle/instance *for* this identity\"."},
		},
	}
}
