// Package triggers is `trigger-eval`: measure whether each skill's description pulls in its own prompts and
// leaves its neighbours' alone, by asking a model which skills it would consult for each.
package triggers

import (
	"encoding/json"
	"errors"
	"math"
	"os/exec"
	"slices"
	"sort"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/layout"
	"github.com/jessegall/code-commandments/skill"
	"github.com/jessegall/code-commandments/skills"
)

// floor is the share of its own prompts a description must pull.
const floor = 1.0

// Command is `trigger-eval`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"trigger-eval"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Measure whether skill descriptions pull their own skill in — and stay out of their neighbours'.").
		Form("trigger-eval", "score every skill that ships a trigger set").
		Form("trigger-eval --skill=NAME", "score one skill (lenient name match), still judged against every other").
		Option("--skill=NAME", "only score this skill").
		Option("--samples=N", "how many times to ask each query (default 3; a majority decides)").
		Option("--model=ID", "the model to ask — default is whatever `claude -p` uses").
		Note("Each skill's queries live in `skills/commandments/<slug>/evals/triggers.json`: `triggers` " +
			"are prompts it must answer, `not` are near-misses it must leave alone. Every query is judged " +
			"against EVERY measured skill, so a prompt that belongs to one is automatically a negative for " +
			"the rest — which is how a collision between two similar descriptions shows up at all.").
		Note("It shells out to `claude -p` once per query per sample, so it is slow and it is billed. Run it " +
			"deliberately, never as part of `composer sins`. Exit code 1 when a description misses one of its " +
			"own prompts or answers another skill's.")
}

// Run scores the selected skills' descriptions.
func (Command) Run(in *cli.Input, console cli.Console) (int, error) {
	query, _ := in.Option("skill")
	sets, err := selected(query)
	if err != nil {
		return 0, err
	}

	if len(sets) == 0 {
		console.Warn("No skill with a trigger set matched. Write one at skills/commandments/<slug>/evals/triggers.json.")

		return 2, nil
	}

	samples := 3
	if given, set := in.Option("samples"); set {
		samples = leadingInt(given)
	}

	samples = max(1, samples)
	model, _ := in.Option("model")

	console.Say("Asking `claude -p` " + strconv.Itoa(samples) + "× per query across " + strconv.Itoa(len(sets)) + " skills — this takes a while.")

	card := score(sets, Oracle{Model: model}, samples)
	report(card, console)

	if card.isClean() {
		return 0, nil
	}

	return 1, nil
}

// set is one skill's prompts: those it must answer, and near-misses it must leave alone.
type set struct {
	id       string
	triggers []string
	not      []string
}

// selected are the trigger sets of the skills the query matches, in the curriculum's order.
func selected(query string) ([]set, error) {
	var sets []set

	for _, teaching := range skill.Ordered() {
		definition := teaching.Definition()

		if query != "" && !definition.Matches(query) {
			continue
		}

		raw, err := skills.Files.ReadFile("commandments/" + definition.Slug + "/evals/triggers.json")
		if err != nil {
			continue
		}

		var decoded struct {
			Triggers []any `json:"triggers"`
			Not      []any `json:"not"`
		}

		if err := json.Unmarshal(raw, &decoded); err != nil {
			return nil, errors.New("the trigger set of " + definition.Slug + " is not JSON: " + err.Error())
		}

		sets = append(sets, set{definition.ID(), texts(decoded.Triggers), texts(decoded.Not)})
	}

	return sets, nil
}

func texts(values []any) []string {
	var kept []string

	for _, value := range values {
		if text, isText := value.(string); isText {
			kept = append(kept, text)
		}
	}

	return kept
}

// Oracle asks `claude -p` which skills it would consult for a request.
type Oracle struct {
	Model string
}

// Consulted are the skill ids the model's answer names.
func (o Oracle) Consulted(query string, ids []string, descriptions map[string]string) []string {
	var catalogue strings.Builder

	for _, id := range ids {
		catalogue.WriteString("- " + id + ": " + descriptions[id] + "\n")
	}

	prompt := "You are choosing which SKILLS to consult for a user's request. Here is the whole list you\n" +
		"can see, each as `id: description`:\n\n" + catalogue.String() + "\n" +
		"The user's request is:\n\n" + query + "\n\n" +
		"Answer with a JSON array of the ids you would consult, and nothing else. Answer `[]` if\n" +
		"none of them applies. Judge only from the descriptions above."

	args := []string{"-p"}
	if o.Model != "" {
		args = append(args, "--model", o.Model)
	}

	reply, _ := exec.Command("claude", append(args, prompt)...).Output()
	var found []string

	for _, id := range ids {
		if strings.Contains(string(reply), `"`+id+`"`) {
			found = append(found, id)
		}
	}

	return found
}

// row is how one measured skill fared.
type row struct {
	pulled, owned, stolen int
	misses                []string
}

type scorecard map[string]*row

func score(sets []set, oracle Oracle, samples int) scorecard {
	var ids []string
	descriptions := map[string]string{}

	for _, teaching := range skill.Ordered() {
		definition := teaching.Definition()
		ids = append(ids, definition.ID())
		descriptions[definition.ID()] = definition.Trigger
	}

	card := scorecard{}

	for _, each := range sets {
		for _, query := range queriesOf(each) {
			consulted := consensus(oracle, query.text, ids, descriptions, samples)

			for _, measured := range sets {
				card.record(query, measured.id, slices.Contains(consulted, measured.id))
			}
		}
	}

	return card
}

// query is one prompt, owned by the skill it must pull, or by none for a near-miss.
type query struct {
	text  string
	owner string
}

func queriesOf(each set) []query {
	var queries []query

	for _, text := range each.triggers {
		queries = append(queries, query{text, each.id})
	}

	for _, text := range each.not {
		queries = append(queries, query{text, ""})
	}

	return queries
}

// consensus are the skills a majority of the samples consulted, in the order they were first named.
func consensus(oracle Oracle, text string, ids []string, descriptions map[string]string, samples int) []string {
	tally := map[string]int{}
	var order []string

	for range samples {
		for _, id := range oracle.Consulted(text, ids, descriptions) {
			if tally[id] == 0 {
				order = append(order, id)
			}

			tally[id]++
		}
	}

	var agreed []string

	for _, id := range order {
		if tally[id] >= samples/2+1 {
			agreed = append(agreed, id)
		}
	}

	return agreed
}

func (c scorecard) record(q query, skill string, consulted bool) {
	each, known := c[skill]
	if !known {
		each = &row{}
		c[skill] = each
	}

	if q.owner == skill {
		each.owned++

		if consulted {
			each.pulled++
		}
	} else if consulted {
		each.stolen++
	}

	answered := consulted
	if q.owner != skill {
		answered = !consulted
	}

	if !answered {
		each.misses = append(each.misses, q.text)
	}
}

func (c scorecard) recall(skill string) float64 {
	each, known := c[skill]
	if !known || each.owned == 0 {
		return 0
	}

	return float64(each.pulled) / float64(each.owned)
}

func (c scorecard) skills() []string {
	var skills []string

	for name := range c {
		skills = append(skills, name)
	}

	sort.Strings(skills)

	return skills
}

func (c scorecard) isClean() bool {
	for _, name := range c.skills() {
		if each := c[name]; each.owned > 0 && (c.recall(name) < floor || each.stolen > 0) {
			return false
		}
	}

	return true
}

func report(card scorecard, console cli.Console) {
	console.Say("")

	for _, name := range card.skills() {
		each := card[name]

		if each.owned == 0 && each.stolen == 0 {
			continue
		}

		mark := "\033[31m✗\033[0m "
		if card.recall(name) >= floor && each.stolen == 0 {
			mark = "\033[32m✓\033[0m "
		}

		line := mark + layout.PadBytes(name, 44) + " pulled " + strconv.Itoa(each.pulled) + "/" + strconv.Itoa(each.owned) +
			" (" + strconv.Itoa(int(math.Round(card.recall(name)*100))) + "%)"

		if each.stolen > 0 {
			line += "  \033[33manswered " + strconv.Itoa(each.stolen) + " that were not its own\033[0m"
		}

		console.Say(line)

		for _, miss := range each.misses[:min(3, len(each.misses))] {
			console.Say("    \033[2m↳ " + miss[:min(96, len(miss))] + "\033[0m")
		}
	}

	console.Say("")

	if card.isClean() {
		console.Say("\033[32mEvery measured description pulled its own prompts and left the others alone.\033[0m")

		return
	}

	console.Say("\033[31mA description is not doing its job — rewrite it to name WHEN to reach for the skill, and what it is NOT.\033[0m")
}

// leadingInt reads a number as PHP's (int) cast does: its leading digits, else 0.
func leadingInt(text string) int {
	text = strings.TrimLeft(text, " \t\n\r\v\f")
	end := 0

	if end < len(text) && (text[end] == '-' || text[end] == '+') {
		end++
	}

	for end < len(text) && text[end] >= '0' && text[end] <= '9' {
		end++
	}

	number, _ := strconv.Atoi(text[:end])

	return number
}
