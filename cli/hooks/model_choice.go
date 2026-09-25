package hooks

import "fmt"

// modelScale is what the choice of model is between, cheapest first, each with the work it is for.
const modelScale = "haiku for mechanical work with a known answer (run this, read that file, list what matches); " +
	"sonnet for ordinary work that needs care but no invention; " +
	"opus only where the task turns on judgement — a design call, a review, an ambiguous failure."

// ModelChoiceReminder asks for an explicit model when an agent is dispatched without one, since an unnamed
// model inherits the dispatcher's.
type ModelChoiceReminder struct{}

func (ModelChoiceReminder) Class() string { return "ModelChoiceReminder" }
func (ModelChoiceReminder) Summary() string {
	return "Asks for an explicit model when an agent is dispatched without one, since an unnamed model inherits the dispatcher's."
}
func (ModelChoiceReminder) Bindings() []Binding { return []Binding{{"PreToolUse", "Agent"}} }

func (ModelChoiceReminder) Handle(event Event) Response {
	if event.Name() != "PreToolUse" || !event.SeesDispatch() || event.ModelRequested() != "" {
		return Silent()
	}

	agent := event.AgentTypeRequested()
	if agent == "" {
		agent = "the agent"
	}

	return Injecting(event.Name(), fmt.Sprintf("Code Commandments — this dispatch names no model, so `%s` will run on YOURS. Say what the "+
		"task actually demands and pass the cheapest model that meets it: %s If you have already "+
		"judged this one and your model is the right one, carry on — the point is that it be a "+
		"decision rather than a default.", agent, modelScale), true)
}
