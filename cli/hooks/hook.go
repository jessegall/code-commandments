package hooks

// Binding is one moment a hook listens for: an event, and the tool it narrows to when it names one.
type Binding struct {
	Event   string
	Matcher string
}

// Label is the binding as a person names it: Stop, or PreToolUse/Bash.
func (b Binding) Label() string {
	if b.Matcher == "" {
		return b.Event
	}

	return b.Event + "/" + b.Matcher
}

// Hook is one handler of the suite.
type Hook interface {
	// Class is the hook's name as a config turns it off by, and as `hook <Class>` runs it.
	Class() string
	// Bindings are the moments it listens for.
	Bindings() []Binding
	// Summary is what it does, in a line, for its help.
	Summary() string
	// Handle answers one moment it listens for.
	Handle(event Event) Response
}

// Discipline is a hook that speaks to subagents too: the disciplines hold inside a worker as they hold in
// the session that spawned it.
type Discipline interface {
	Hook
	SpeaksToSubagents()
}

// QuietWhileWorkPends is a hook that stays quiet at a stop while background work is still running.
type QuietWhileWorkPends interface {
	Hook
	QuietWhileWorkPends()
}

// Answer is the hook's response to the event, once the moment is one it speaks at: a subagent hears only a
// Discipline, a hook bound to tools hears only those, and a stop in plan mode, or while background work a
// hook waits on is running, hears nothing.
func Answer(hook Hook, event Event) Response {
	if _, speaks := hook.(Discipline); event.IsSubagent() && !speaks {
		return Silent()
	}

	if !boundToTool(hook, event) {
		return Silent()
	}

	if event.Name() == "Stop" && staysQuietAt(hook, event) {
		return Silent()
	}

	return hook.Handle(event)
}

func boundToTool(hook Hook, event Event) bool {
	var matchers []string

	for _, binding := range hook.Bindings() {
		if binding.Event != event.Name() {
			continue
		}

		if binding.Matcher == "" {
			return true
		}

		matchers = append(matchers, binding.Matcher)
	}

	if len(matchers) == 0 {
		return true
	}

	for _, matcher := range matchers {
		if matcher == event.Tool() {
			return true
		}
	}

	return false
}

func staysQuietAt(hook Hook, event Event) bool {
	_, waits := hook.(QuietWhileWorkPends)

	return event.IsPlanMode() || (event.HasPendingBackgroundWork() && waits)
}
