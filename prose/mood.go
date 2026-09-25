package prose

import (
	"slices"
	"strings"
	"unicode"
)

// questionWords is what makes a name read as a question: the only mood besides the imperative a method may wear,
// and then only when it answers bool. The list stops at the modals and auxiliaries: `needs` or `requires` is a
// third-person verb.
var questionWords = []string{
	"is", "are", "was", "were", "has", "have", "had", "can", "could", "should", "must", "may",
	"might", "will", "would", "does", "do", "did", "awaits",
}

// verbs is the stems whose third-person form narrates where a command belongs. Curated, and read as an allow-list:
// a stem outside it stays silent, which keeps plural nouns (`names`) and imperatives ending in s (`process`) out.
var verbs = []string{
	// appearance / lifecycle
	"hide", "reveal", "show", "display", "render", "draw", "paint", "open", "close", "expand",
	"collapse", "enter", "exit", "leave", "start", "stop", "begin", "end", "pause", "resume",
	"mount", "unmount", "boot", "shutdown", "spin", "animate", "fade", "flash", "blink",
	// data / persistence
	"write", "read", "save", "store", "persist", "load", "fetch", "pull", "push", "send", "receive",
	"publish", "emit", "announce", "broadcast", "dispatch", "queue", "flush", "sync", "import",
	"export", "upload", "download", "cache", "log", "record", "report", "track", "count",
	// mutation
	"add", "remove", "delete", "drop", "clear", "reset", "update", "create", "make", "build",
	"register", "unregister", "bind", "unbind", "attach", "detach", "link", "unlink", "connect",
	"disconnect", "assign", "apply", "set", "put", "insert", "append", "prepend", "replace",
	"rename", "move", "copy", "merge", "split", "sort", "filter", "reorder", "toggle", "swap",
	// behaviour / control
	"run", "execute", "perform", "handle", "process", "resolve", "reject", "accept", "approve",
	"cancel", "abort", "retry", "refresh", "reload", "redirect", "forward", "route", "call",
	"invoke", "trigger", "fire", "notify", "warn", "fail", "throw", "catch", "guard", "protect",
	"validate", "verify", "check", "test", "assert", "ensure", "require", "expect", "wait",
	// domain-ish, still verbs
	"pay", "charge", "refund", "ship", "deliver", "pick", "pack", "print", "scan", "quote",
	"book", "reserve", "release", "lock", "unlock", "grant", "revoke", "invite", "join", "follow",
	"own", "hold", "carry", "cover", "wrap", "unwrap", "mark", "tag", "label", "name", "title",
	"describe", "explain", "answer", "ask", "reply", "respond", "echo", "dump",
}

// prepositions turn a name into a relation, `startsWith`: a fluent method relating its receiver to what it is
// handed states a constraint, and the third person is the right English for it.
var prepositions = []string{
	"with", "without", "for", "on", "at", "in", "into", "to", "from", "by", "of", "off", "over",
	"under", "above", "below", "between", "against", "through", "across", "after", "before",
	"during", "until", "toward", "towards", "upon", "via", "per", "like", "as",
}

// IsRelationalCompound says whether the name relates its receiver to something: a verb then a preposition, as in
// `startsWith` or `complies_with`.
func IsRelationalCompound(name string) bool {
	rest := afterLeadingWord(name)

	return rest != "" && slices.Contains(prepositions, strings.ToLower(leadingToken(lowerFirst(rest))))
}

// IsThirdPerson says whether the name's first word narrates a verb the lexicon knows: `hides`, `entersTestMode`.
func IsThirdPerson(name string) bool {
	_, ok := stemOf(name)

	return ok
}

// ReadsAsQuestion says whether the name opens with a question word.
func ReadsAsQuestion(name string) bool {
	token := strings.ToLower(leadingWord(name))

	return token != "" && slices.Contains(questionWords, token)
}

// stemOf is the verb behind a third-person first word: it ends in s, and what remains once that ending comes
// off is a verb the lexicon knows.
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

// leadingWord is the name's first word in any convention, leading underscores aside: `hides` in `hidesPanel`,
// `hides_panel` and `HidesPanel`.
func leadingWord(name string) string {
	bare := strings.TrimLeft(name, "_")
	if at := strings.Index(bare, "_"); at >= 0 {
		bare = bare[:at]
	}

	return leadingToken(lowerFirst(bare))
}

// afterLeadingWord is what follows the name's first word: `ForUser` in `hidesForUser`, `for_user` in
// `hides_for_user`.
func afterLeadingWord(name string) string {
	bare := strings.TrimLeft(name, "_")
	if at := strings.Index(bare, "_"); at >= 0 {
		return bare[at+1:]
	}
	lowered := lowerFirst(bare)

	return lowered[len(leadingToken(lowered)):]
}

// leadingToken is the name up to its first capital.
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
