package hooks

import (
	"fmt"
	"io"
	"os"
	"runtime/debug"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/bridge"
)

var serviceLog io.Writer = os.Stderr

func init() {
	bridge.Launching = func(command []string, arguments int) {
		logStarted(fmt.Sprintf("bridge | %s | arguments=%d", strings.Join(command, " "), arguments))
	}
}

func logFromTheService(text string) {
	if serving.Load() {
		fmt.Fprintln(serviceLog, text)
	}
}

func logStarted(what string) {
	logFromTheService(fmt.Sprintf("%s | started | %s", time.Now().Format(time.DateTime), what))
}

func logServiceStart(root string) {
	logStarted(fmt.Sprintf("hooks service | pid=%d | project=%s | release=%s | memory limit=%d MiB",
		os.Getpid(), root, bridge.Release, debug.SetMemoryLimit(-1)>>20))
}

func logRuleFailure(rule string, failure any) {
	logFromTheService(fmt.Sprintf("%s | rule failed | rule=%s | %v\n%s", time.Now().Format(time.DateTime), rule, failure, debug.Stack()))
}
