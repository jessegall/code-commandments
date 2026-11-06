package prose

import (
	"slices"
	"strings"
	"unicode"
)

var questionWords = []string{
	"is", "are", "was", "were", "has", "have", "had", "can", "could", "should", "must", "may",
	"might", "will", "would", "does", "do", "did", "awaits",
}

var verbs = []string{
	"hide", "reveal", "show", "display", "render", "draw", "paint", "open", "close", "expand",
	"collapse", "enter", "exit", "leave", "start", "stop", "begin", "end", "pause", "resume",
	"mount", "unmount", "boot", "shutdown", "spin", "animate", "fade", "flash", "blink",
	"write", "read", "save", "store", "persist", "load", "fetch", "pull", "push", "send", "receive",
	"publish", "emit", "announce", "broadcast", "dispatch", "queue", "flush", "sync", "import",
	"export", "upload", "download", "cache", "log", "record", "report", "track", "count",
	"add", "remove", "delete", "drop", "clear", "reset", "update", "create", "make", "build",
	"register", "unregister", "bind", "unbind", "attach", "detach", "link", "unlink", "connect",
	"disconnect", "assign", "apply", "set", "put", "insert", "append", "prepend", "replace",
	"rename", "move", "copy", "merge", "split", "sort", "filter", "reorder", "toggle", "swap",
	"run", "execute", "perform", "handle", "process", "resolve", "reject", "accept", "approve",
	"cancel", "abort", "retry", "refresh", "reload", "redirect", "forward", "route", "call",
	"invoke", "trigger", "fire", "notify", "warn", "fail", "throw", "catch", "guard", "protect",
	"validate", "verify", "check", "test", "assert", "ensure", "require", "expect", "wait",
	"pay", "charge", "refund", "ship", "deliver", "pick", "pack", "print", "scan", "quote",
	"book", "reserve", "release", "lock", "unlock", "grant", "revoke", "invite", "join", "follow",
	"own", "hold", "carry", "cover", "wrap", "unwrap", "mark", "tag", "label", "name", "title",
	"describe", "explain", "answer", "ask", "reply", "respond", "echo", "dump",
}

var prepositions = []string{
	"with", "without", "for", "on", "at", "in", "into", "to", "from", "by", "of", "off", "over",
	"under", "above", "below", "between", "against", "through", "across", "after", "before",
	"during", "until", "toward", "towards", "upon", "via", "per", "like", "as",
}

func IsRelationalCompound(name string) bool {
	rest := afterLeadingWord(name)

	return rest != "" && slices.Contains(prepositions, strings.ToLower(leadingToken(lowerFirst(rest))))
}

func IsBareVerb(name string) bool {
	if !IsThirdPerson(name) {
		return false
	}

	return afterLeadingWord(name) == ""
}

func IsThirdPerson(name string) bool {
	_, ok := stemOf(name)

	return ok
}

func ReadsAsQuestion(name string) bool {
	token := strings.ToLower(leadingWord(name))

	return token != "" && slices.Contains(questionWords, token)
}

func stemOf(name string) (string, bool) {
	token := strings.ToLower(leadingWord(name))
	if !strings.HasSuffix(token, "s") {
		return "", false
	}
	candidates := []string{token[:len(token)-1]}
	if strings.HasSuffix(token, "es") {
		candidates = append(candidates, token[:len(token)-2])
	}
	if strings.HasSuffix(token, "ies") {
		candidates = append(candidates, token[:len(token)-3]+"y")
	}
	for _, candidate := range candidates {
		if slices.Contains(verbs, candidate) {
			return candidate, true
		}
	}

	return "", false
}

func leadingWord(name string) string {
	bare := strings.TrimLeft(name, "_")
	if at := strings.Index(bare, "_"); at >= 0 {
		bare = bare[:at]
	}

	return leadingToken(lowerFirst(bare))
}

func afterLeadingWord(name string) string {
	bare := strings.TrimLeft(name, "_")
	if at := strings.Index(bare, "_"); at >= 0 {
		return bare[at+1:]
	}
	lowered := lowerFirst(bare)

	return lowered[len(leadingToken(lowered)):]
}

func leadingToken(name string) string {
	for at, char := range name {
		if char <= unicode.MaxASCII && unicode.IsUpper(char) {
			return name[:at]
		}
	}

	return name
}

func lowerFirst(name string) string {
	if name == "" {
		return name
	}

	return strings.ToLower(name[:1]) + name[1:]
}

var nonEntities = []string{
	"is", "are", "was", "be", "been", "has", "have", "had", "can", "could", "should", "would",
	"will", "shall", "may", "might", "must", "do", "does", "did",
	"no", "all", "any", "some", "each", "every", "none", "total", "sum", "count", "num", "min",
	"max", "first", "last", "next", "prev", "only",
	"add", "remove", "delete", "close", "open", "move", "copy", "import", "export", "discard",
	"confirm", "cancel", "save", "load", "run", "sort", "filter", "toggle", "show", "hide", "get",
	"set", "fetch", "send", "submit", "reset", "clear", "apply", "select", "edit", "update", "create",
	"connect", "disconnect", "replay", "zoom", "scroll", "refresh", "pick",
	"running", "booting", "loading", "pending", "unsaved", "empty", "used", "auto", "required",
	"active", "enabled", "disabled", "dynamic", "advisory", "quick", "current",
	"to", "of", "in", "on", "at", "by", "for", "with", "from",
}

func IsNonEntity(token string) bool {
	return slices.Contains(nonEntities, strings.ToLower(token))
}

func LeadingToken(name string) string {
	return leadingToken(name)
}

func AfterLeadingToken(name string) string {
	return name[len(leadingToken(name)):]
}
