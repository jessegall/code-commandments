package git

import "testing"

func TestADiffCoversExactlyTheLinesItChanged(t *testing.T) {
	for _, each := range []struct {
		name    string
		hunk    string
		covered []int
		left    []int
	}{
		{"one changed line", "@@ -10 +10 @@", []int{10}, []int{9, 11}},
		{"several changed lines", "@@ -10,2 +10,3 @@", []int{10, 11, 12}, []int{9, 13}},
		{"a pure deletion", "@@ -9,2 +8,0 @@", nil, []int{7, 8, 9}},
	} {
		changed := FromDiff(each.hunk + "\n")

		for _, line := range each.covered {
			if !changed.Covers(line) {
				t.Errorf("%s does not cover line %d", each.name, line)
			}
		}

		for _, line := range each.left {
			if changed.Covers(line) {
				t.Errorf("%s covers line %d, which it did not change", each.name, line)
			}
		}
	}
}
