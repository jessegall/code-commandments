package scan

import (
	"os"
	"path/filepath"
	"slices"
	"sync"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
)

// TestTheBridgesRunTwoAtATime holds the load to running every bridge with files to read, never more than two at
// once, and telling each one's name as it finishes.
func TestTheBridgesRunTwoAtATime(t *testing.T) {
	root := t.TempDir()
	for _, file := range []string{"Order.php", "order.py", "Order.cs"} {
		if err := os.WriteFile(filepath.Join(root, file), []byte("\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	var lock sync.Mutex
	running, most := 0, 0
	bridge := func(_, _ []string) (*contract.Stream, error) {
		lock.Lock()
		running++
		most = max(most, running)
		lock.Unlock()
		time.Sleep(50 * time.Millisecond)
		lock.Lock()
		running--
		lock.Unlock()

		return nil, nil
	}
	whole := readers
	readers = []reader{
		{name: "PHP", languages: []source.Language{source.PHP}, stream: bridge},
		{name: "Python", languages: []source.Language{source.Python}, stream: bridge},
		{name: "C#", languages: []source.Language{source.CSharp}, stream: bridge},
	}
	t.Cleanup(func() { readers = whole })

	var told []string
	if _, err := Walk([]string{root}, source.Excluded{}).Reporting(func(name string) { told = append(told, name) }).Load(); err != nil {
		t.Fatal(err)
	}

	if most != bridgesAtOnce {
		t.Errorf("%d bridges ran at once, want %d", most, bridgesAtOnce)
	}
	slices.Sort(told)
	if !slices.Equal(told, []string{"C#", "PHP", "Python"}) {
		t.Errorf("the load told %v", told)
	}
}
