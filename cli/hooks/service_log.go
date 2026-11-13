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

func logRuleFailure(rule string, failure any) {
	logFromTheService(fmt.Sprintf("%s | rule failed | rule=%s | %v\n%s", time.Now().Format(time.DateTime), rule, failure, debug.Stack()))
}
