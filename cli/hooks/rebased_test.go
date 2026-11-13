package hooks

import (
	"os/exec"
	"testing"
)

func commitIn(t *testing.T, root, message string) {
	t.Helper()

	for _, command := range [][]string{
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", message},
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}
}
