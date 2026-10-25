package report

import (
	"encoding/json"
	"errors"
	"os"
	"regexp"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// moved is the exit of a run that found a report has moved: the check that runs it then fails, which is how the
// answer reaches the agent.
const moved = 1

// unreachable is the exit of a run that could not ask GitHub at all. It is a pass: a check that failed every
// interval because `gh` is missing would be noise, and nothing about the reports has changed.
const unreachable = 0

// release is a version a maintainer names in a comment, such as "fixed in v6.10.2".
var release = regexp.MustCompile(`\bv(\d+)\.(\d+)\.(\d+)\b`)

// issueState is what the GitHub CLI answers about one issue.
type issueState struct {
	State    string `json:"state"`
	Reason   string `json:"stateReason"`
	Title    string `json:"title"`
	Comments []struct {
		Author struct {
			Login string `json:"login"`
		} `json:"author"`
		Body string `json:"body"`
	} `json:"comments"`
}

// viewing asks the GitHub CLI about one issue; a test puts its own in its place.
var viewing = func(gh, repository string, issue int) (string, error) {
	stdout, stderr, err := filing(gh, "issue", "view", strconv.Itoa(issue), "--repo", repository,
		"--json", "state,stateReason,title,comments")
	if err != nil {
		return "", &unanswered{said: strings.TrimSpace(stderr + stdout)}
	}

	return stdout, nil
}

// unanswered is the GitHub CLI failing to answer about an issue.
type unanswered struct {
	said string
}

// Error is what gh said.
func (u *unanswered) Error() string {
	return u.said
}

// follow asks GitHub what became of each report this project has filed, and says what to do about each answer.
//
// It asks the repository each row was filed to, never the one the project itself sits in: a consumer's reports go
// to this tool's repository, and asking about the consumer's own would ask the wrong GitHub entirely.
func (c Command) follow(console cli.Console) int {
	root := workspace.ProjectRoot(workingFolder())
	record, read := reported.Of(root)

	if !read {
		console.Warn("✗ " + reported.Path(root) + " could not be read, so there is nothing to follow. Put it back " +
			"as the record it was, or delete it and report what is still wrong again.")

		return moved
	}

	rows := record.Rows()
	if len(rows) == 0 {
		console.Write("No reports filed, so there is nothing to follow.\n")

		return unreachable
	}

	gh, err := looking()
	if err != nil {
		console.Warn("GitHub CLI (`gh`) is not here, so what became of " + natural.Counted(len(rows), "report") +
			" cannot be asked. Install it and run `gh auth login`, or read them yourself:")

		for _, row := range rows {
			console.Warn("  " + row.Where())
		}

		return unreachable
	}

	return c.followEach(root, gh, rows, console)
}

// followEach asks about each report and says what it found; what it could not ask about is said once and changes
// nothing, since an answer nobody could read is not an answer.
func (c Command) followEach(root, gh string, rows []reported.Row, console cli.Console) int {
	var findings []finding
	asked, unreached := 0, 0

	for _, row := range rows {
		// A row the project has accepted is settled for it: the fix is upstream and out of reach, and nothing
		// GitHub could say would change what this project does about it.
		if row.Accepted != "" {
			continue
		}

		state, err := ask(gh, row)

		// An issue GitHub does not know is a stale row, not a failure to ask: it was deleted, moved, or the row
		// was written by hand. Either way nothing will ever answer it, so it is named rather than retried for ever.
		if gone(err) {
			asked++
			findings = append(findings, finding{
				Name:  "#" + strconv.Itoa(row.Issue),
				Where: row.Where(),
				Text: "is not an issue GitHub knows, so nothing will answer it: `" + binary.Here() + " report forget " +
					strconv.Itoa(row.Issue) + "` and report the finding again if it is still wrong.",
				Group: "Recorded but not on GitHub",
			})

			continue
		}

		if err != nil {
			unreached++
			console.Warn("Could not ask about " + row.Where() + ": " + err.Error())

			continue
		}

		asked++
		if said, is := whatMoved(row, state, c.Version); is {
			findings = append(findings, said)
		}

		if err := reported.Saw(root, row.Issue, seenOf(row, state)); err != nil {
			console.Warn("What was seen of " + row.Where() + " could not be remembered: " + err.Error())
		}
	}

	findings = append(findings, unrecorded(gh, rows)...)

	if space := workspace.At(root, ""); space.IsJournalDriven() {
		dashboard.Redraw(space)
	}

	writeReport(findings, asked, console)

	if len(findings) == 0 {
		console.Write("Every one of " + natural.Counted(asked, "report") + " still stands as it was filed.\n")

		return unreachable
	}

	for _, found := range findings {
		console.Write(found.Name + " " + found.Text + "\n")
	}

	return moved
}

// notFound is how the GitHub CLI says an issue is not there.
var notFound = regexp.MustCompile(`(?i)could not resolve to an issue|no issue found|not found`)

// gone says whether the error is GitHub answering that there is no such issue, rather than our failing to ask.
func gone(err error) bool {
	said := &unanswered{}

	return errors.As(err, &said) && notFound.MatchString(said.said)
}

// listing asks the GitHub CLI for the issues this account has filed about the tool; a test puts its own in its
// place.
var listing = func(gh, repository string) (string, error) {
	stdout, stderr, err := filing(gh, "issue", "list", "--repo", repository, "--author", "@me", "--state", "all",
		"--limit", "100", "--json", "number,title,url")
	if err != nil {
		return "", &unanswered{said: strings.TrimSpace(stderr + stdout)}
	}

	return stdout, nil
}

// titled is the two titles the tool files its own issues under, which is how one of ours is told from any other
// issue the same account opened.
var titled = []string{"[detector-report]", "[bug-report]"}

// unrecorded are the issues this account filed about the tool that no row of the record knows: a report filed by
// hand, or one whose row was lost. They are named and never adopted, because a row needs the file and the line a
// hand-filed issue has not got.
func unrecorded(gh string, rows []reported.Row) []finding {
	if len(rows) == 0 {
		return nil
	}

	answer, err := listing(gh, rows[0].Repository)
	if err != nil {
		return nil
	}

	var listed []struct {
		Number int    `json:"number"`
		Title  string `json:"title"`
		URL    string `json:"url"`
	}

	if json.Unmarshal([]byte(answer), &listed) != nil {
		return nil
	}

	known := map[int]bool{}
	for _, row := range rows {
		known[row.Issue] = true
	}

	var found []finding
	for _, issue := range listed {
		if known[issue.Number] || !ours(issue.Title) {
			continue
		}

		found = append(found, finding{
			Name:  "#" + strconv.Itoa(issue.Number),
			Where: issue.URL,
			Text: "was filed from this account and no row of the record knows it, so nothing judges its line as " +
				"reported: report the finding again through `" + binary.Here() + " report` if it still stands.",
			Group: "On GitHub but not recorded",
		})
	}

	return found
}

// ours says whether the issue's title is one this tool files.
func ours(title string) bool {
	for _, prefix := range titled {
		if strings.HasPrefix(title, prefix) {
			return true
		}
	}

	return false
}

// ask is what GitHub says about the report's issue.
func ask(gh string, row reported.Row) (issueState, error) {
	answer, err := viewing(gh, row.Repository, row.Issue)
	if err != nil {
		return issueState{}, err
	}

	var state issueState
	if err := json.Unmarshal([]byte(answer), &state); err != nil {
		return issueState{}, err
	}

	return state, nil
}

// whatMoved is what to do about the issue's answer, and whether it has moved at all since the row last saw it.
//
// A report is answered in one of three ways, and each asks something different of the reader. A fix names the
// release that carries it, and what to do then depends on the version running: a project already at or past it has
// the fix and only has to judge again and drop the row, while one behind it is told which release to take. A close
// with no release means the finding stands, so the row goes and the sin is the project's to fix. Anything the
// maintainer says while the issue is open is a question or an argument, and that is the agent's to answer.
func whatMoved(row reported.Row, state issueState, running string) (finding, bool) {
	owner, _, _ := strings.Cut(row.Repository, "/")

	if strings.EqualFold(state.State, "closed") {
		if named := releaseNamed(state, owner); named != "" {
			return finding{
				Name:  "#" + strconv.Itoa(row.Issue),
				Where: row.Where(),
				Text:  closedWithARelease(row, named, running),
				Group: "Answered with a fix",
			}, true
		}

		return finding{
			Name:  "#" + strconv.Itoa(row.Issue),
			Where: row.Where(),
			Text: "is closed with no release named, so the finding stands: fix " + row.Detector + " at " + row.File + ":" +
				strconv.Itoa(row.Line) + " and run `" + binary.Here() + " report forget " + strconv.Itoa(row.Issue) + "`.",
			Group: "Answered: the finding stands",
		}, true
	}

	if said := answers(state, owner); said > row.Seen.Comments {
		return finding{
			Name:  "#" + strconv.Itoa(row.Issue),
			Where: row.Where(),
			Text:  "is open and " + owner + " has said something: read it and answer, it is a question about this project's code.",
			Group: "Waiting on an answer from this project",
		}, true
	}

	return finding{}, false
}

// closedWithARelease is what to do about a fix, which depends on the version this project runs.
func closedWithARelease(row reported.Row, named, running string) string {
	issue := strconv.Itoa(row.Issue)

	switch {
	case !isVersion(running):
		return "is fixed in " + named + ", and this is a development build: judge again, then `" + binary.Here() +
			" report forget " + issue + "`."
	case atLeast(running, named):
		return "is fixed in " + named + ", which this project already runs: judge again, and `" + binary.Here() +
			" report forget " + issue + "` once the finding is gone."
	default:
		return "is fixed in " + named + ", and this project runs " + running + ": upgrade to " + named + ", judge again, then `" +
			binary.Here() + " report forget " + issue + "`."
	}
}

// releaseNamed is the release a maintainer named in the issue's own conversation, empty where none was.
func releaseNamed(state issueState, owner string) string {
	for _, comment := range state.Comments {
		if !strings.EqualFold(comment.Author.Login, owner) {
			continue
		}

		if named := release.FindString(comment.Body); named != "" {
			return named
		}
	}

	return ""
}

// answers is how many of the issue's comments are the maintainer's.
func answers(state issueState, owner string) int {
	said := 0
	for _, comment := range state.Comments {
		if strings.EqualFold(comment.Author.Login, owner) {
			said++
		}
	}

	return said
}

// seenOf is what this look found, as the row remembers it, so the same answer is told once rather than at every
// look.
func seenOf(row reported.Row, state issueState) reported.Seen {
	owner, _, _ := strings.Cut(row.Repository, "/")

	return reported.Seen{
		State:    strings.ToUpper(state.State),
		Comments: answers(state, owner),
		At:       time.Now().UTC().Format(time.RFC3339),
	}
}

// finding is one answer this run found, as the journal's check report names it.
type finding struct {
	Name  string `json:"name"`
	Where string `json:"where"`
	Text  string `json:"text"`
	Group string `json:"group"`
}

// checkReport is the report the journal reads when it runs this as a check.
type checkReport struct {
	Title    string    `json:"title"`
	Summary  string    `json:"summary"`
	Findings []finding `json:"findings"`
}

// writeReport writes what was found where the journal's check reads it. A run outside a check writes nothing and
// says so on the console instead.
func writeReport(findings []finding, asked int, console cli.Console) {
	path := os.Getenv(cli.JournalReport)
	if path == "" {
		return
	}

	if findings == nil {
		findings = []finding{}
	}

	content, err := json.Marshal(checkReport{
		Title:    "Reports",
		Summary:  strconv.Itoa(len(findings)) + " of " + natural.Counted(asked, "report") + " have moved",
		Findings: findings,
	})

	if err != nil {
		console.Warn("The check's report could not be written: " + err.Error())

		return
	}

	if err := os.WriteFile(path, content, 0o644); err != nil {
		console.Warn("The check's report could not be written: " + err.Error())
	}
}

// isVersion says whether the text names a release, which a development build does not.
func isVersion(text string) bool {
	return release.MatchString(text)
}

// atLeast says whether the running version is the named release or a later one.
func atLeast(running, named string) bool {
	return !earlier(parts(running), parts(named))
}

// earlier says whether one version comes before the other.
func earlier(one, other [3]int) bool {
	for at := range one {
		if one[at] != other[at] {
			return one[at] < other[at]
		}
	}

	return false
}

// parts are a version's three numbers, zeroes for anything that is no version.
func parts(text string) [3]int {
	named := release.FindStringSubmatch(text)
	if named == nil {
		return [3]int{}
	}

	var read [3]int
	for at := range read {
		read[at], _ = strconv.Atoi(named[at+1])
	}

	return read
}
