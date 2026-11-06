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

const moved = 1

const unreachable = 0

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

func (u *unanswered) Error() string {
	return u.said
}

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

func (c Command) followEach(root, gh string, rows []reported.Row, console cli.Console) int {
	var findings []finding
	asked, unreached := 0, 0

	for _, row := range rows {
		if row.Accepted != "" {
			continue
		}

		state, err := ask(gh, row)

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

var notFound = regexp.MustCompile(`(?i)could not resolve to an issue|no issue found|not found`)

func gone(err error) bool {
	said := &unanswered{}

	return errors.As(err, &said) && notFound.MatchString(said.said)
}

var listing = func(gh, repository string) (string, error) {
	stdout, stderr, err := filing(gh, "issue", "list", "--repo", repository, "--author", "@me", "--state", "all",
		"--limit", "100", "--json", "number,title,url")
	if err != nil {
		return "", &unanswered{said: strings.TrimSpace(stderr + stdout)}
	}

	return stdout, nil
}

var titled = []string{"[detector-report]", "[bug-report]"}

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

func ours(title string) bool {
	for _, prefix := range titled {
		if strings.HasPrefix(title, prefix) {
			return true
		}
	}

	return false
}

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

func answers(state issueState, owner string) int {
	said := 0
	for _, comment := range state.Comments {
		if strings.EqualFold(comment.Author.Login, owner) {
			said++
		}
	}

	return said
}

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

func isVersion(text string) bool {
	return release.MatchString(text)
}

func atLeast(running, named string) bool {
	return !earlier(parts(running), parts(named))
}

func earlier(one, other [3]int) bool {
	for at := range one {
		if one[at] != other[at] {
			return one[at] < other[at]
		}
	}

	return false
}

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
