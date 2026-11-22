package contract

import (
	"encoding/json"
	"fmt"
	"runtime"
	"strings"
	"testing"
)

func TestADeepTreeIsDecodedInOnePass(t *testing.T) {
	shallow, deep := bytesAllocatedDecoding(t, 100), bytesAllocatedDecoding(t, 400)

	if deep > 6*shallow {
		t.Errorf("a tree four times as deep allocated %d bytes against %d: its decoding grows with depth times size, not with size", deep, shallow)
	}
}

func TestADecodedTreeKeepsEveryRoleInItsOrder(t *testing.T) {
	raw := `{"id":0,"kind":"if","span":[0,9,1],"name":"guard","parts":{"condition":{"id":1,"kind":"name","span":[3,4,1],"name":"x"},"then":[{"id":2,"kind":"return","span":[5,6,1]},{"id":3,"kind":"return","span":[7,8,1]}],"else":null}}`
	var node Node
	if err := json.Unmarshal([]byte(raw), &node); err != nil {
		t.Fatal(err)
	}

	var roles []string
	for _, part := range node.Parts {
		roles = append(roles, fmt.Sprintf("%s:%d:%v", part.Role, len(part.Nodes), part.List))
	}
	if got := strings.Join(roles, " "); got != "condition:1:false then:2:true else:1:false" {
		t.Errorf("the roles decoded as %s", got)
	}
	if node.Name != "guard" || node.Parts[0].Nodes[0].Name != "x" || node.Parts[1].Nodes[1].ID != 3 || node.Span.End != 9 {
		t.Errorf("the facts decoded as %+v", node)
	}
	if err := json.Unmarshal([]byte(`{"id":0,"kind":"if","span":[0,1,1],"unknown":true}`), &node); err == nil {
		t.Errorf("a node with a fact the contract does not declare decoded without an error")
	}
}

func bytesAllocatedDecoding(t *testing.T, depth int) uint64 {
	t.Helper()
	var raw strings.Builder
	for level := range depth {
		fmt.Fprintf(&raw, `{"id":%d,"kind":"block","span":[0,1,1],"name":"level","parts":{"body":`, level)
	}
	raw.WriteString(`{"id":-1,"kind":"return","span":[0,1,1]}`)
	raw.WriteString(strings.Repeat("}}", depth))
	data := []byte(raw.String())

	var before, after runtime.MemStats
	runtime.GC()
	runtime.ReadMemStats(&before)
	var node Node
	if err := json.Unmarshal(data, &node); err != nil {
		t.Fatal(err)
	}
	runtime.ReadMemStats(&after)

	return after.TotalAlloc - before.TotalAlloc
}
