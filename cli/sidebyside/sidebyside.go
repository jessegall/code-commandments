// Package sidebyside runs a count of jobs on a number of goroutines at once, the one pool every reading and judging
// loop shares.
package sidebyside

import "sync"

// Each runs work for every index below count, at most workers of them at once and at least one, and returns once all
// have run.
func Each(count, workers int, work func(at int)) {
	next := make(chan int)
	var running sync.WaitGroup
	for range max(1, min(workers, count)) {
		running.Add(1)
		go func() {
			defer running.Done()
			for at := range next {
				work(at)
			}
		}()
	}
	for at := range count {
		next <- at
	}
	close(next)
	running.Wait()
}
