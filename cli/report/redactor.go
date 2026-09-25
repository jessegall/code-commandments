// Package report is `report` and `feature-request`: file a GitHub issue about the tool through `gh`,
// with the code it concerns read in and any secret in it masked.
package report

import (
	"regexp"
	"strings"
	"unicode/utf8"
)

// sensitive are the words a secret's key holds.
const sensitive = `(?:passw(?:ord|d)?|pwd|secret|secrets|api[_-]?key|apikey|access[_-]?key|` +
	`client[_-]?secret|private[_-]?key|credentials?|authorization|auth[_-]?token|bearer|token|dsn|` +
	`app[_-]?key|encryption[_-]?key|session[_-]?secret|webhook[_-]?secret|salt|signature|passphrase)`

var (
	// assignedSecret opens a quoted value assigned to a sensitive key: `'password' => '`, `token: "`.
	assignedSecret = regexp.MustCompile(`(?i)(["']?` + sensitive + `["']?\s*(?:=>|:|=)\s*)(["'])`)

	// defaultedSecret opens a quoted default an env or config lookup of a sensitive key falls back to.
	defaultedSecret = regexp.MustCompile(`(?i)((?:env|getenv|config)\(\s*["'][^"']*` + sensitive + `[^"']*["']\s*,\s*)(["'])`)

	// exportedSecret is a sensitive variable set to an unquoted value, as in a .env or shell file.
	exportedSecret = regexp.MustCompile(`(?i)^(\s*(?:export\s+)?[A-Za-z_][A-Za-z0-9_]*` + sensitive + `[A-Za-z0-9_]*\s*=\s*)(\S.*)$`)

	// urlCredentials are the user and password in a URL.
	urlCredentials = regexp.MustCompile(`(?i)([a-z][a-z0-9+.\-]*://)([^\s:@/]+:[^\s:@/]+)@`)

	// tokens are secrets recognisable by their shape alone.
	tokens = []*regexp.Regexp{
		regexp.MustCompile(`A(?:KIA|SIA|GPA|IDA|ROA|IPA|NPA|NVA)[0-9A-Z]{16}`),
		regexp.MustCompile(`gh[pousr]_[A-Za-z0-9]{20,}`),
		regexp.MustCompile(`github_pat_[A-Za-z0-9_]{20,}`),
		regexp.MustCompile(`AIza[0-9A-Za-z_\-]{20,}`),
		regexp.MustCompile(`xox[baprs]-[0-9A-Za-z\-]{10,}`),
		regexp.MustCompile(`(?:sk|pk|rk)_(?:live|test)_[0-9A-Za-z]{16,}`),
		regexp.MustCompile(`eyJ[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}`),
		regexp.MustCompile(`-----BEGIN[A-Z ]*PRIVATE KEY-----`),
	}

	// quotedRandom is a long quoted run of key characters.
	quotedRandom = regexp.MustCompile(`"([A-Za-z0-9+/=_\-]{32,})"|'([A-Za-z0-9+/=_\-]{32,})'`)

	letter = regexp.MustCompile(`[A-Za-z]`)
	digit  = regexp.MustCompile(`[0-9]`)
)

// Redact masks every secret in one line of code, keeping its shape so the line still reads.
func Redact(line string) string {
	line = maskQuoted(line, assignedSecret)
	line = maskQuoted(line, defaultedSecret)

	if match := exportedSecret.FindStringSubmatchIndex(line); match != nil && !strings.ContainsAny(line[match[4]:match[4]+1], `'"`) {
		line = line[:match[4]] + mask(line[match[4]:match[5]])
	}

	line = urlCredentials.ReplaceAllStringFunc(line, func(found string) string {
		parts := urlCredentials.FindStringSubmatch(found)

		return parts[1] + mask(parts[2]) + "@"
	})

	for _, token := range tokens {
		line = token.ReplaceAllStringFunc(line, mask)
	}

	return quotedRandom.ReplaceAllStringFunc(line, func(found string) string {
		value := found[1 : len(found)-1]

		if letter.MatchString(value) && digit.MatchString(value) {
			return found[:1] + mask(value) + found[len(found)-1:]
		}

		return found
	})
}

// maskQuoted masks the quoted value after each opening the pattern finds, the value read up to its
// matching quote with backslash escapes, as the PCRE pattern `(["'])((?:\\.|(?!\2).)*)(\2)` reads it.
func maskQuoted(line string, opening *regexp.Regexp) string {
	var out strings.Builder
	from := 0

	for from <= len(line) {
		match := opening.FindStringSubmatchIndex(line[from:])
		if match == nil {
			break
		}

		quoteAt := from + match[4]
		end, closed := closingQuote(line, quoteAt+1, line[quoteAt])

		if !closed {
			out.WriteString(line[from : from+match[0]+1])
			from += match[0] + 1

			continue
		}

		out.WriteString(line[from:quoteAt+1] + mask(line[quoteAt+1:end]) + line[end:end+1])
		from = end + 1
	}

	if from < len(line) {
		out.WriteString(line[from:])
	}

	return out.String()
}

// closingQuote is where the value opened before start closes: the greedy reading PCRE's backtracking finds,
// each step an escape pair or a byte that is not the quote, then the quote.
func closingQuote(line string, start int, quote byte) (int, bool) {
	failed := map[int]bool{}

	var from func(at int) (int, bool)

	from = func(at int) (int, bool) {
		if failed[at] {
			return 0, false
		}

		if at+1 < len(line) && line[at] == '\\' && line[at+1] != '\n' {
			if end, found := from(at + 2); found {
				return end, true
			}
		}

		if at < len(line) && line[at] != quote && line[at] != '\n' {
			if end, found := from(at + 1); found {
				return end, true
			}
		}

		if at < len(line) && line[at] == quote {
			return at, true
		}

		failed[at] = true

		return 0, false
	}

	return from(start)
}

// mask is a run of blocks as long as the secret, counted in characters.
func mask(secret string) string {
	return strings.Repeat("█", max(1, utf8.RuneCountInString(secret)))
}
