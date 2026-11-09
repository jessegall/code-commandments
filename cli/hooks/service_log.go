package hooks

import (
	"fmt"
	"io"
	"os"
	"runtime/debug"
	"time"
)

var serviceLog io.Writer = os.Stderr

func logFromTheService(text string) {
	if serving.Load() {
		fmt.Fprintln(serviceLog, text)
	}
}

func logRuleFailure(rule string, failure any) {
	logFromTheService(fmt.Sprintf("%s | rule failed | rule=%s | %v\n%s", time.Now().Format(time.DateTime), rule, failure, debug.Stack()))
}
