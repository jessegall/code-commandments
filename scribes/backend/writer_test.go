package backend

import (
	"testing"

	"github.com/jessegall/code-commandments/scribes"
)

// stripped is the source with its first method's docblock removed.
func stripped(t *testing.T, source string) string {
	t.Helper()
	methods := codebaseOf(t, source).WhereKind("Stmt_ClassMethod").Get()
	if len(methods) == 0 {
		t.Fatal("the source must declare a method to strip")
	}
	draft := scribes.NewDraft()
	For(draft, methods[0]).RemoveDocblock(methods[0])

	return draft.Rewrites().Content(methods[0].File())
}

func TestADocblockOnItsOwnLinesGoesWithItsLine(t *testing.T) {
	source := "<?php\n\nclass Report\n{\n    /**\n     * A sentence the code already says.\n     */\n    public function render(): void {}\n}\n"

	if got := stripped(t, source); got != "<?php\n\nclass Report\n{\n    public function render(): void {}\n}\n" {
		t.Fatalf("got %q", got)
	}
}

func TestADeclarationSharingTheDocblocksLineKeepsIt(t *testing.T) {
	source := "<?php\n\nclass Report\n{\n    /** one line */ public function render(): void {}\n}\n"

	if got := stripped(t, source); got != "<?php\n\nclass Report\n{\n     public function render(): void {}\n}\n" {
		t.Fatalf("got %q", got)
	}
}
