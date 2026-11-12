//go:build unix

package atomic

import (
	"os"
	"path/filepath"
	"syscall"
	"time"
)

func Hold(path string) func() {
	os.MkdirAll(filepath.Dir(path), 0o777)

	file, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0o666)
	if err != nil {
		return func() {}
	}

	syscall.Flock(int(file.Fd()), syscall.LOCK_EX)

	return func() {
		syscall.Flock(int(file.Fd()), syscall.LOCK_UN)
		file.Close()
	}
}

const turnPolled = 250 * time.Millisecond

func HoldInTurn(path string, waiting func(), patience time.Duration) func() {
	os.MkdirAll(filepath.Dir(path), 0o777)

	file, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0o666)
	if err != nil {
		return func() {}
	}

	taken := func() bool { return syscall.Flock(int(file.Fd()), syscall.LOCK_EX|syscall.LOCK_NB) == nil }
	if !taken() {
		waiting()
		for until := time.Now().Add(patience); !taken(); time.Sleep(turnPolled) {
			if time.Now().After(until) {
				file.Close()

				return func() {}
			}
		}
	}

	return func() {
		syscall.Flock(int(file.Fd()), syscall.LOCK_UN)
		file.Close()
	}
}
