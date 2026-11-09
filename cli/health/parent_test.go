package health

import (
	"os"
	"sync/atomic"
	"testing"
	"time"
)

func TestAServiceStopsWhenItsParentIsGone(t *testing.T) {
	var parent atomic.Int64
	parent.Store(4242)

	stopped := make(chan struct{})
	asked := make(chan struct{}, 1)
	go untilOrphaned(func() int {
		select {
		case asked <- struct{}{}:
		default:
		}

		return int(parent.Load())
	}, time.Millisecond, func() { close(stopped) })

	<-asked
	time.Sleep(10 * time.Millisecond)
	parent.Store(1)

	select {
	case <-stopped:
	case <-time.After(2 * time.Second):
		t.Fatal("the service kept running after its parent was gone")
	}
}

func TestAServiceStopsWhenItsPluginFolderIsGone(t *testing.T) {
	folder := t.TempDir()
	stopped := make(chan struct{})
	go untilRemoved(folder, time.Millisecond, func() { close(stopped) })

	select {
	case <-stopped:
		t.Fatal("the service stopped with its plugin folder still there")
	case <-time.After(50 * time.Millisecond):
	}

	if err := os.RemoveAll(folder); err != nil {
		t.Fatal(err)
	}

	select {
	case <-stopped:
	case <-time.After(2 * time.Second):
		t.Fatal("the service kept running after its plugin folder was gone")
	}
}

func TestThePluginFolderIsTheOneTheJournalNames(t *testing.T) {
	t.Setenv(pluginDir, "/plugins/commandments")

	if got := pluginFolder(); got != "/plugins/commandments" {
		t.Errorf("the plugin folder is %q, not the one $%s names", got, pluginDir)
	}
}

func TestAServiceKeepsRunningWhileItsParentStays(t *testing.T) {
	stopped := make(chan struct{})
	go untilOrphaned(func() int { return 4242 }, time.Millisecond, func() { close(stopped) })

	select {
	case <-stopped:
		t.Fatal("the service stopped with its parent still there")
	case <-time.After(50 * time.Millisecond):
	}
}

func TestAServiceStartedAfterItsParentDiedHasNoParent(t *testing.T) {
	if !hasNoParent(1, false) {
		t.Error("a service whose parent was already gone at its start is taken to have one")
	}

	if hasNoParent(4242, false) {
		t.Error("a service with a parent is taken to have none")
	}

	if hasNoParent(1, true) {
		t.Error("in a container, where the first process may be the journal itself, a service is taken to have no parent")
	}
}

func TestAParentThatWasAlreadyInitIsNoChangeOfParent(t *testing.T) {
	stopped := make(chan struct{})
	go untilOrphaned(func() int { return 1 }, time.Millisecond, func() { close(stopped) })

	select {
	case <-stopped:
		t.Fatal("a process whose parent was already init was stopped")
	case <-time.After(50 * time.Millisecond):
	}
}
