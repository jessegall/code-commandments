package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

func TestAdviceIsQueuedAsThePHPToolQueuesIt(t *testing.T) {
	if _, err := exec.LookPath("php"); err != nil {
		t.Fatal("no php to queue with the PHP tool")
	}

	whisper := "Code Commandments — the edit: it breaks a rule that is described at such length that its first line runs well past eighty characters.\n  • it's here\n\n    and here"
	php, golang := filepath.Join(t.TempDir(), "queue"), filepath.Join(t.TempDir(), "queue")

	repo, _ := filepath.Abs("../..")
	script := `require '` + repo + `/vendor/autoload.php';
putenv('JOURNAL_QUEUE=' . $argv[1]);
$queue = \JesseGall\CodeCommandments\Cli\Hooks\JournalQueue::fromEnvironment()->unwrap();
$advice = (new \JesseGall\CodeCommandments\Cli\Hooks\JournalAnswer(whisper: $argv[2]))->raising(
    new \JesseGall\CodeCommandments\Cli\Hooks\JournalRaise('sin-found', "array-bag at src/A.php:3", 'sins/x'),
    new \JesseGall\CodeCommandments\Cli\Hooks\JournalRaise('sin-resolved', "a\nb"),
);
$queue->tell($advice, \JesseGall\CodeCommandments\Cli\Hooks\JournalMoment::fromPayload(['event' => 'hook.PostToolUse', 'env' => "o'brien"]));`

	if out, err := exec.Command("php", "-r", script, "--", php, whisper).CombinedOutput(); err != nil {
		t.Fatalf("php: %v\n%s", err, out)
	}

	answer := JournalAnswer{Whisper: &whisper, Raises: []Raise{{"sin-found", "array-bag at src/A.php:3", "sins/x"}, {Event: "sin-resolved", Brief: "a\nb"}}}

	if err := (Queue{golang}).Tell(answer, MomentOf(map[string]any{"event": "hook.PostToolUse", "env": "o'brien"})); err != nil {
		t.Fatal(err)
	}

	want, _ := os.ReadFile(php)
	got, _ := os.ReadFile(golang)

	if string(got) != string(want) {
		t.Errorf("queued\n%s\nthe PHP tool queued\n%s", got, want)
	}
}
