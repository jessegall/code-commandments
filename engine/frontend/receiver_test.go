package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

func TestWhatANodeDecidesByIsAnsweredForTheFrontend(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/decide.ts": `export function decide(ready: boolean, lines: string[]): number {
    if (ready) {
        return 1;
    }

    while (ready) {
        return 2;
    }

    for (const line of lines) {
        void line;
    }

    return ready ? 3 : 4;
}
`,
	})

	for kind, want := range map[string]string{
		"IfStatement":           "ready",
		"WhileStatement":        "ready",
		"ConditionalExpression": "ready",
	} {
		for _, node := range codebase.WhereKind(kind).Get() {
			if decided := node.Condition(); decided.Written() != want {
				t.Errorf("a %s decides by %q", kind, decided.Written())
			}

			break
		}
	}

	for _, node := range codebase.WhereKind("ForOfStatement").Get() {
		if decided := node.Condition(); decided.Exists() {
			t.Errorf("a for-of decides by %q, and it decides nothing", decided.Written())
		}
	}
}
