package hooks

import "testing"

func TestEachAgentOfALineWithNoSessionKeepsAMemoryOfItsOwn(t *testing.T) {
	first, _ := Moment4Of(map[string]any{"v": float64(1), "event": "file_edited", "agent": float64(41), "data": map[string]any{"path": "/nowhere/a.py"}})
	second, _ := Moment4Of(map[string]any{"v": float64(1), "event": "file_edited", "agent": float64(42), "data": map[string]any{"path": "/nowhere/a.py"}})

	one, two := NewEvent(MomentOf(first).HookPayload(), "/nowhere"), NewEvent(MomentOf(second).HookPayload(), "/nowhere")
	if one.SessionID() == "" || one.SessionID() == two.SessionID() {
		t.Errorf("two agents whose lines carry no session share the memory of what each was told: %q and %q",
			one.SessionID(), two.SessionID())
	}
}
