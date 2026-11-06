package hooks

import (
	"bytes"
	"encoding/json"
	"slices"
	"strings"
)

var injectable = []string{
	"SessionStart", "Setup", "SubagentStart", "UserPromptSubmit", "UserPromptExpansion",
	"PreToolUse", "PostToolUse", "PostToolUseFailure", "PostToolBatch", "Stop", "SubagentStop",
}

// Response is what a hook answers the harness: nothing, a refusal, context for the agent, or the raw instructions a compaction reads.
type Response struct {
	blockReason  *string
	context      *string
	quietly      bool
	instructions *string
	Activity     []SinMark
	Judged       []string
	Asked        Asked
	ReadMs       int64

	Requires []string
}

func Silent() Response {
	return Response{}
}

func Blocking(reason string) Response {
	return Response{blockReason: &reason}
}

func Injecting(event, context string, quietly bool) Response {
	if !slices.Contains(injectable, event) {
		return Silent()
	}

	return Response{context: &context, quietly: quietly}
}

func Instructing(text string) Response {
	text = strings.TrimSpace(text)
	if text == "" {
		return Silent()
	}

	return Response{instructions: &text}
}

func Merge(responses []Response) Response {
	var reasons, contexts, instructions []string
	var activity []SinMark
	var judged, requires []string
	quietly := true

	var read int64

	asked := Asked{}

	for _, response := range responses {
		activity = append(activity, response.Activity...)
		judged = append(judged, response.Judged...)
		read += response.ReadMs

		for file, sins := range response.Asked {
			asked[file] = sins
		}

		for _, id := range response.Requires {
			if !slices.Contains(requires, id) {
				requires = append(requires, id)
			}
		}

		if response.blockReason != nil {
			reasons = append(reasons, *response.blockReason)
		}

		if response.context != nil {
			contexts = append(contexts, *response.context)
			quietly = quietly && response.quietly
		}

		if response.instructions != nil {
			instructions = append(instructions, *response.instructions)
		}
	}

	merged := merge(reasons, contexts, instructions, quietly)
	merged.Activity = activity
	merged.Judged = judged
	merged.Asked = asked
	merged.Requires = requires
	merged.ReadMs = read

	return merged
}

func merge(reasons, contexts, instructions []string, quietly bool) Response {
	switch {
	case len(reasons) > 0:
		merged := Blocking(strings.Join(reasons, "\n\n"))

		if len(contexts) > 0 {
			context := strings.Join(contexts, "\n\n")
			merged.context = &context
		}

		return merged
	case len(instructions) > 0:
		return Instructing(strings.Join(instructions, "\n\n"))
	case len(contexts) == 0:
		return Silent()
	default:
		context := strings.Join(contexts, "\n\n")

		return Response{context: &context, quietly: quietly}
	}
}

func (r Response) IsSilent() bool {
	return r.blockReason == nil && r.context == nil && r.instructions == nil
}

func (r Response) JSON(event string) string {
	if r.blockReason != nil {
		return `{"decision":"block","reason":` + quote(*r.blockReason) + "}\n"
	}

	if r.instructions != nil {
		return *r.instructions
	}

	context := ""
	if r.context != nil {
		context = *r.context
	}

	injection := `{"hookSpecificOutput":{"hookEventName":` + quote(event) + `,"additionalContext":` + quote(context) + `}`

	if r.quietly {
		return injection + `,"suppressOutput":true}` + "\n"
	}

	return injection + "}\n"
}

func quote(text string) string {
	var out bytes.Buffer

	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(text)

	return strings.TrimSuffix(out.String(), "\n")
}
