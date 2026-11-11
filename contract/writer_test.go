package contract

import (
	"bytes"
	"testing"
)

func TestAStreamWrittenBackReadsTheSameWithItsTypesStillShared(t *testing.T) {
	written := `{"header":{"contract":"tree","version":4,"language":"python","bridge":{"name":"t","version":"1"},"roots":["/p"]}}
{"file":{"path":"/p/a.py","language":"python","errors":0,"comments":[],"types":[{"text":"int","kind":"named","name":"int","origin":"compiler"}],"root":{"id":0,"kind":"Module","role":"other","span":[0,1,1],"children":[{"id":1,"kind":"Name","role":"expression","span":[0,1,1],"field":"a","resolvedType":0},{"id":2,"kind":"Name","role":"expression","span":[0,1,1],"field":"b","resolvedType":0}]}}}
{"trailer":{"files":1}}
`

	read, err := ReadAll(bytes.NewBufferString(written))
	if err != nil {
		t.Fatal(err)
	}

	var again bytes.Buffer
	if err := Write(&again, read); err != nil {
		t.Fatal(err)
	}

	back, err := ReadAll(&again)
	if err != nil {
		t.Fatalf("what was written back does not read: %v", err)
	}

	children := back.Files[0].Root.Children
	if children[0].Resolved == nil || children[0].Resolved != children[1].Resolved {
		t.Errorf("the two names no longer share their type: %v and %v", children[0].Resolved, children[1].Resolved)
	}
}

func TestAStreamWrittenBackLeavesOutWhatTheEngineFills(t *testing.T) {
	written := `{"header":{"contract":"tree","version":1,"language":"php","bridge":{"name":"t","version":"1"},"roots":["/p"]}}
{"file":{"path":"/p/a.php","language":"php","errors":0,"comments":[{"id":0,"kind":"doc","text":"x","span":[0,1,1],"refs":[{"text":"Foo"}]}],"root":{"id":0,"kind":"Stmt_Class","role":"other","span":[0,1,1]}}}
{"trailer":{"files":1}}
`

	read, err := ReadAll(bytes.NewBufferString(written))
	if err != nil {
		t.Fatal(err)
	}

	read.Files[0].Root.Facts = &Facts{Resolves: "App\\\\Foo", Constant: true}
	read.Files[0].Comments[0].Refs[0].Symbol = "App\\\\Foo"

	var again bytes.Buffer
	if err := Write(&again, read); err != nil {
		t.Fatal(err)
	}

	if _, err := ReadAll(&again); err != nil {
		t.Errorf("what the engine filled went out with the stream: %v", err)
	}

	if read.Files[0].Root.Resolves == "" || read.Files[0].Comments[0].Refs[0].Symbol == "" {
		t.Error("writing a stream changed the tree still being held")
	}
}

func TestATupleWithNoMembersWrittenBackStillReads(t *testing.T) {
	written := `{"header":{"contract":"tree","version":4,"language":"typescript","bridge":{"name":"t","version":"1"},"roots":["/p"]}}
{"file":{"path":"/p/a.ts","language":"typescript","errors":0,"comments":[],"types":[{"text":"[]","kind":"tuple","members":[],"origin":"compiler"}],"root":{"id":0,"kind":"SourceFile","role":"other","span":[0,1,1],"children":[{"id":1,"kind":"Identifier","role":"expression","span":[0,1,1],"field":"a","resolvedType":0}]}}}
{"trailer":{"files":1}}
`

	read, err := ReadAll(bytes.NewBufferString(written))
	if err != nil {
		t.Fatal(err)
	}

	var again bytes.Buffer
	if err := Write(&again, read); err != nil {
		t.Fatal(err)
	}

	if _, err := ReadAll(&again); err != nil {
		t.Errorf("a tuple of no members was written back as something that does not read: %v", err)
	}
}
