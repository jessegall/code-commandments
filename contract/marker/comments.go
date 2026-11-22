package marker

import (
	"bytes"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Comment is one comment of a source file as its bytes hold it: where it stands, its text with the delimiters
// stripped, and whether code stands before it on its line.
type Comment struct {
	Start    int
	End      int
	Line     int
	Column   int
	Text     string
	Trailing bool
}

func Comments(language contract.Language, source []byte) []Comment {
	scanner := commentScanner{source: source}
	scanner.scan(0, len(source), contract.LexiconOf(language))

	return scanner.found
}

// commentScanner walks a file's bytes once, region by region, finding each comment its language writes and skipping
// every quoted text.
type commentScanner struct {
	source []byte
	found  []Comment
}

func (s *commentScanner) scan(from, to int, lexicon contract.Lexicon) {
	for at := from; at < to; {
		at = s.step(at, to, lexicon)
	}
}

func (s *commentScanner) step(at, to int, lexicon contract.Lexicon) int {
	rest := s.source[at:to]
	for _, region := range lexicon.Regions {
		if bytes.HasPrefix(rest, []byte(region.Open)) {
			return s.region(at, to, region)
		}
	}
	for _, quote := range lexicon.Quotes {
		if bytes.HasPrefix(rest, []byte(quote.Open)) {
			return s.quoted(at, to, quote, lexicon)
		}
	}
	for _, excluded := range lexicon.NotComments {
		if bytes.HasPrefix(rest, []byte(excluded)) {
			return at + len(excluded)
		}
	}
	for _, opener := range lexicon.LineComments {
		if bytes.HasPrefix(rest, []byte(opener)) {
			end := at + len(rest)
			if newline := bytes.IndexByte(rest, '\n'); newline >= 0 {
				end = at + newline
			}
			s.comment(at, end, string(s.source[at+len(opener):end]))

			return end
		}
	}
	if opener, closer := lexicon.BlockComment[0], lexicon.BlockComment[1]; opener != "" && bytes.HasPrefix(rest, []byte(opener)) {
		end := to
		if closing := bytes.Index(rest[len(opener):], []byte(closer)); closing >= 0 {
			end = at + len(opener) + closing + len(closer)
		}
		s.comment(at, end, strings.TrimSuffix(string(s.source[at+len(opener):end]), closer))

		return end
	}

	return at + 1
}

func (s *commentScanner) region(at, to int, region contract.Region) int {
	opened := bytes.IndexByte(s.source[at:to], '>')
	if opened < 0 {
		return to
	}
	start := at + opened + 1
	end := to
	if closing := bytes.Index(s.source[start:to], []byte(region.Close)); closing >= 0 {
		end = start + closing
	}
	s.scan(start, end, contract.LexiconOf(region.Language))

	return min(end+len(region.Close), to)
}

func (s *commentScanner) quoted(at, to int, quote contract.Quote, lexicon contract.Lexicon) int {
	if quote.Heredoc {
		return s.heredoc(at+len(quote.Open), to)
	}
	for inside := at + len(quote.Open); inside < to; {
		rest := s.source[inside:to]
		switch {
		case quote.Escapes && rest[0] == '\\':
			inside += 2
		case quote.Code[0] != "" && bytes.HasPrefix(rest, []byte(quote.Code[0])):
			inside = s.code(inside+len(quote.Code[0]), to, quote.Code[1], lexicon)
		case bytes.HasPrefix(rest, []byte(quote.Close)):
			return inside + len(quote.Close)
		default:
			inside++
		}
	}

	return to
}

func (s *commentScanner) code(at, to int, closer string, lexicon contract.Lexicon) int {
	depth := 0
	for at < to {
		switch rest := s.source[at:to]; {
		case bytes.HasPrefix(rest, []byte(closer)) && depth == 0:
			return at + len(closer)
		case rest[0] == '{':
			depth, at = depth+1, at+1
		case rest[0] == '}':
			depth, at = depth-1, at+1
		default:
			at = s.skipQuote(at, to, lexicon)
		}
	}

	return to
}

func (s *commentScanner) skipQuote(at, to int, lexicon contract.Lexicon) int {
	for _, quote := range lexicon.Quotes {
		if bytes.HasPrefix(s.source[at:to], []byte(quote.Open)) {
			return s.quoted(at, to, quote, lexicon)
		}
	}

	return at + 1
}

func (s *commentScanner) heredoc(at, to int) int {
	opening := to
	if newline := bytes.IndexByte(s.source[at:to], '\n'); newline >= 0 {
		opening = at + newline
	}
	word := strings.Trim(strings.TrimSpace(string(s.source[at:opening])), `'"`)
	if word == "" {
		return at
	}
	for line := at; line < to; {
		newline := bytes.IndexByte(s.source[line:to], '\n')
		if newline < 0 {
			return to
		}
		line += newline + 1
		trimmed := bytes.TrimLeft(s.source[line:to], " \t")
		if bytes.HasPrefix(trimmed, []byte(word)) && !continuesWord(trimmed[len(word):]) {
			return to - len(trimmed) + len(word)
		}
	}

	return to
}

func continuesWord(rest []byte) bool {
	return len(rest) > 0 && (rest[0] == '_' || rest[0] >= '0' && rest[0] <= '9' || rest[0]|0x20 >= 'a' && rest[0]|0x20 <= 'z')
}

func (s *commentScanner) comment(start, end int, text string) {
	lineStart := bytes.LastIndexByte(s.source[:start], '\n') + 1
	s.found = append(s.found, Comment{
		Start:    start,
		End:      end,
		Line:     bytes.Count(s.source[:start], []byte{'\n'}) + 1,
		Column:   start - lineStart + 1,
		Text:     strings.TrimSpace(text),
		Trailing: len(bytes.TrimSpace(s.source[lineStart:start])) > 0,
	})
}
