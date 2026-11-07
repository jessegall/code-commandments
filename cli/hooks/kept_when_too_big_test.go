package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

func TestATreeIsKeptWhenAQuestionIsTooBigForIt(t *testing.T) {
	root := t.TempDir()

	for _, line := range [][]string{{"init", "-q", "-b", "main"}, {"config", "user.email", "t@t"}, {"config", "user.name", "t"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(filepath.Join(root, "src", "order.py"), []byte(olderSin), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, line := range [][]string{{"add", "-A"}, {"commit", "-qm", "first"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	nowServing(t)
	WarmsWhatItServes(root)

	held := false
	for at := 0; at < 100 && !held; at++ {
		if _, _, why := answering(root, root); why == "" {
			held = true

			break
		}
	}

	if !held {
		t.Skip("no tree could be held here, so this test would prove nothing about keeping one")
	}

	many := make([]string, 0, 5000)
	for at := range 5000 {
		many = append(many, filepath.Join(root, "src", "made"+itoaFiles(at)+".py"))
	}

	if _, _, why := AgainstTheProgramNarrowed(Marks(root), many, nil, nil, root); why == "" {
		t.Skip("a question of 5000 files was answered whole, so the ceiling was not reached")
	}

	if _, _, why := answering(root, root); why != "" {
		t.Errorf("after one question too big to splice, the tree is gone: %q. Dropping it means the next tick "+
			"reads the whole project again, and the question after that drops it again, which is a read loop", why)
	}
}

func itoaFiles(number int) string {
	if number == 0 {
		return "0"
	}

	digits := ""
	for number > 0 {
		digits = string(rune('0'+number%10)) + digits
		number /= 10
	}

	return digits
}
