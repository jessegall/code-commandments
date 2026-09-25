package prose

import (
	"slices"
	"strings"
)

// questionPrefixes are the leading words that make a name read as a question.
var questionPrefixes = []string{
	"is", "are", "was", "were", "has", "have", "had", "can", "could", "should", "must", "may",
	"might", "will", "would", "does", "do", "did", "awaits",
}

// verbs are the verbs a command's name is known to open with.
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
	"describe", "explain", "answer", "ask", "reply", "respond", "echo", "print", "dump",
}

// prepositions are the words that make a name's second word a relation to its argument.
var prepositions = []string{
	"with", "without", "for", "on", "at", "in", "into", "to", "from", "by", "of", "off", "over",
	"under", "above", "below", "between", "against", "through", "across", "after", "before",
	"during", "until", "toward", "towards", "upon", "via", "per", "like", "as",
}

// ReadsAsQuestion says whether a method name opens with a question word: isShown, hasParent, awaitsAnswer.
func ReadsAsQuestion(name string) bool {
	token := leadingWord(name)

	return token != "" && slices.Contains(questionPrefixes, strings.ToLower(token))
}

// IsThirdPerson says whether a method name opens with a known verb in the third person: hides, opensFor.
func IsThirdPerson(name string) bool {
	token := strings.ToLower(leadingWord(name))
	if !strings.HasSuffix(token, "s") {
		return false
	}
	candidates := []string{token[:len(token)-1]}
	if strings.HasSuffix(token, "es") {
		candidates = append(candidates, token[:len(token)-2])
	}
	if strings.HasSuffix(token, "ies") {
		candidates = append(candidates, token[:len(token)-3]+"y")
	}

	return slices.ContainsFunc(candidates, func(candidate string) bool { return slices.Contains(verbs, candidate) })
}

// IsRelationalCompound says whether a method name's second word is a preposition: opensFor, matchesWith.
func IsRelationalCompound(name string) bool {
	rest := afterLeadingWord(name)

	return rest != "" && slices.Contains(prepositions, strings.ToLower(leadingWord(lowerFirst(rest))))
}

// leadingWord is a name's first word: up to its first underscore, then up to its first capital.
func leadingWord(name string) string {
	first, _, _ := strings.Cut(strings.TrimLeft(name, "_"), "_")

	return leadingToken(lowerFirst(first))
}

// afterLeadingWord is what follows a name's first word.
func afterLeadingWord(name string) string {
	bare := strings.TrimLeft(name, "_")
	if _, rest, found := strings.Cut(bare, "_"); found {
		return rest
	}
	lowered := lowerFirst(bare)

	return lowered[len(leadingToken(lowered)):]
}

// leadingToken is a camel-case name's leading run before its first capital.
func leadingToken(name string) string {
	for at := 0; at < len(name); at++ {
		if name[at] >= 'A' && name[at] <= 'Z' {
			return name[:at]
		}
	}

	return name
}

func lowerFirst(name string) string {
	if name == "" || name[0] < 'A' || name[0] > 'Z' {
		return name
	}

	return string(name[0]+'a'-'A') + name[1:]
}

// nonEntities are the words that open a name as grammar — a verb, a modal, a quantifier, a state — not as the
// entity a group of fields describes.
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

// IsNonEntity says whether a name's leading word is grammar rather than an entity.
func IsNonEntity(token string) bool {
	return slices.Contains(nonEntities, strings.ToLower(token))
}

// LeadingToken is a camel-case name's leading run before its first capital.
func LeadingToken(name string) string {
	return leadingToken(name)
}

// AfterLeadingToken is what follows a camel-case name's leading run.
func AfterLeadingToken(name string) string {
	return name[len(leadingToken(name)):]
}
