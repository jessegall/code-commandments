package health

import (
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

func TestAServiceKeepsRunningWhileItsParentStays(t *testing.T) {
	stopped := make(chan struct{})
	go untilOrphaned(func() int { return 4242 }, time.Millisecond, func() { close(stopped) })

	select {
	case <-stopped:
		t.Fatal("the service stopped with its parent still there")
	case <-time.After(50 * time.Millisecond):
	}
}

func TestAServiceStartedWithoutAParentIsLeftAlone(t *testing.T) {
	stopped := make(chan struct{})
	go untilOrphaned(func() int { return 1 }, time.Millisecond, func() { close(stopped) })

	select {
	case <-stopped:
		t.Fatal("a process whose parent was already init was stopped")
	case <-time.After(50 * time.Millisecond):
	}
}
