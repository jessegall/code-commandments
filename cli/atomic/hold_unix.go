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
		unlock(file)
		file.Close()
	}
}

func tryLock(file *os.File) bool {
	return syscall.Flock(int(file.Fd()), syscall.LOCK_EX|syscall.LOCK_NB) == nil
}

func unlock(file *os.File) {
	syscall.Flock(int(file.Fd()), syscall.LOCK_UN)
}

const turnPolled = 250 * time.Millisecond

func HoldInTurn(path string, waiting func(), patience time.Duration) func() {
	os.MkdirAll(filepath.Dir(path), 0o777)

	file, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0o666)
	if err != nil {
		return func() {}
	}

	if !tryLock(file) {
		waiting()
		for until := time.Now().Add(patience); !tryLock(file); time.Sleep(turnPolled) {
			if time.Now().After(until) {
				file.Close()

				return func() {}
			}
		}
	}

	return func() {
		unlock(file)
		file.Close()
	}
}
