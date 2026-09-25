package hooks

import (
	"bytes"
	"encoding/json"
	"slices"
	"strings"
)

// injectable are the events whose response may carry context for the agent.
var injectable = []string{
	"SessionStart", "Setup", "SubagentStart", "UserPromptSubmit", "UserPromptExpansion",
	"PreToolUse", "PostToolUse", "PostToolUseFailure", "PostToolBatch", "Stop", "SubagentStop",
}

// Response is what a hook answers the harness: nothing, a refusal, context for the agent, or the raw
// instructions a compaction reads.
type Response struct {
	blockReason  *string
	context      *string
	quietly      bool
	instructions *string
}

// Silent says nothing.
func Silent() Response {
	return Response{}
}

// Blocking refuses the moment, with the reason the agent is shown.
func Blocking(reason string) Response {
	return Response{blockReason: &reason}
}

// Injecting hands the agent context on the event; quietly keeps it out of what the user reads. An event
// that carries no context gets nothing.
func Injecting(event, context string, quietly bool) Response {
	if !slices.Contains(injectable, event) {
		return Silent()
	}

	return Response{context: &context, quietly: quietly}
}

// Instructing is the text a compaction is told to keep; nothing when it is empty.
func Instructing(text string) Response {
	text = strings.TrimSpace(text)
	if text == "" {
		return Silent()
	}

	return Response{instructions: &text}
}

// Merge is several hooks' responses as one. A refusal does not silence the rest: the context every other
// hook had is kept beside it.
func Merge(responses []Response) Response {
	var reasons, contexts, instructions []string
	quietly := true

	for _, response := range responses {
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

// IsSilent says whether the response says nothing.
func (r Response) IsSilent() bool {
	return r.blockReason == nil && r.context == nil && r.instructions == nil
}

// JSON is the response as the harness reads it for the event: a refusal, the raw instructions, or the
// context under the event's name.
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

// quote is a string as PHP encodes it with slashes and unicode unescaped.
func quote(text string) string {
	var out bytes.Buffer

	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(text)

	return strings.TrimSuffix(out.String(), "\n")
}
