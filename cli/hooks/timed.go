package hooks

import (
	"sync"
	"time"

	"github.com/jessegall/code-commandments/engine"
)

// Timings is what each rule cost over a session, kept only while the setting asks for it: the rules are where a
// judging's time goes once the tree is warm, and until they are split nobody can say which of them is worth
// changing. The split a judging cannot see on its own is what a rule spends building a whole-program analysis
// against walking the tree, which shows as the FIRST rule of a kind paying for every one after it.
type Timings struct {
	mu    sync.Mutex
	spent map[string]time.Duration
	work  map[string]engine.Work
	asked map[string]int
}

var timings = Timings{spent: map[string]time.Duration{}, work: map[string]engine.Work{}, asked: map[string]int{}}
