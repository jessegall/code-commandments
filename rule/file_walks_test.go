package rule

import (
	"errors"
	"testing"
)

func TestARuleThatGoesOverOneFileAgainAndAgainIsStopped(t *testing.T) {
	codebase := expected(t, "flags", "python")
	again := parsed(t, []byte(`{
		"$schema": "commandments:neutral-rule/v2",
		"engine": ["python"],
		"sin": {"name": "walks-again", "skill": "probe", "description": "probe", "rule": "probe"},
		"find": {
			"select": "function",
			"as": "f",
			"let": [["all", ["descendants", ["last", ["ancestors", "$f"]]]]],
			"having": ["some", "$all", "a", ["some", "$all", "b", ["some", "$all", "c", ["some", "$all", "d", false]]]],
			"emit": "$f"
		}
	}`))

	var stopped WentOverAFileAgain
	func() {
		defer func() {
			if failure, isError := recover().(error); isError {
				errors.As(failure, &stopped)
			}
		}()
		again.Judge(codebase)
	}()

	if stopped.Path == "" {
		t.Fatal("a rule that walks every node of a file once for every three other nodes of it ran to its end, where it is stopped for going over the file again and again")
	}
}

func TestARuleThatGoesOverAFileOnceIsNotStopped(t *testing.T) {
	codebase := expected(t, "flags", "python")
	once := parsed(t, []byte(`{
		"$schema": "commandments:neutral-rule/v2",
		"engine": ["python"],
		"sin": {"name": "walks-once", "skill": "probe", "description": "probe", "rule": "probe"},
		"find": {
			"select": "function",
			"as": "f",
			"having": ["some", ["descendants", "$f"], "a", false],
			"emit": "$f"
		}
	}`))

	once.Judge(codebase)
}
