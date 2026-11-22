package rule

import "testing"

// overTheOneHandler is what a where, written over constants alone, finds and leaves undecided on the one handler of
// the bare-except sample: found 1 when it holds, 0 when it fails, undecided 1 when it is unknown.
func overTheOneHandler(t *testing.T, where string) (found, undecided int) {
	t.Helper()
	probe := parsed(t, written(`"find":{"select":"handler","as":"site","where":`+where+`}`))
	judged := probe.Judge(expected(t, "bare-except-pass", "python"))

	return len(judged.Found), len(judged.Undecided)
}

func TestTheNewValueOperatorsAnswerOverKnownAndUnknownValues(t *testing.T) {
	for where, wanted := range map[string][2]int{
		`["has",["list",1,2,3],2]`:                               {1, 0},
		`["has",["list",1,2,3],4]`:                               {0, 0},
		`["has",["list",1,["mood","zzqx"]],4]`:                   {0, 1},
		`["=",["text",3],"3"]`:                                   {1, 0},
		`["=",["concat","found ",["text",2]],"found 2"]`:         {1, 0},
		`["=",["toNumber","0x1F"],31]`:                           {1, 0},
		`["=",["toNumber","1_000"],1000]`:                        {1, 0},
		`["exists",["toNumber","ten"]]`:                          {0, 1},
		`["=",["length","café"],4]`:                              {1, 0},
		`["=",["first",["list",7,8]],7]`:                         {1, 0},
		`["exists",["first",["list"]]]`:                          {0, 0},
		`["exists",["last",["list"]]]`:                           {0, 0},
		`["exists",["at",["list",1],3]]`:                         {0, 0},
		`["=",["at",["list",1,2],1],2]`:                          {1, 0},
		`["isOdd",3]`:                                            {1, 0},
		`["isOdd",-3]`:                                           {1, 0},
		`["isOdd",2.5]`:                                          {0, 1},
		`["encloses","App","App·Ui"]`:                            {1, 0},
		`["encloses","App","Appé"]`:                              {0, 0},
		`["=",["list",1,2],["list",1,2]]`:                        {1, 0},
		`["=",["list",1,2],["list",1,3]]`:                        {0, 0},
		`["=",["list",1,"$site.binding.resolved"],["list",2,3]]`: {0, 0},
		`["=",["list",1,["mood","zzqx"]],["list",1,3]]`:          {0, 1},
	} {
		found, undecided := overTheOneHandler(t, where)
		if found != wanted[0] || undecided != wanted[1] {
			t.Errorf("%s found %d and left %d undecided, where %d and %d are right", where, found, undecided, wanted[0], wanted[1])
		}
	}
}

func TestANumberLiteralComparesAsANumberInEveryLanguage(t *testing.T) {
	for _, language := range []string{"csharp", "go", "php", "python", "typescript"} {
		codebase := expected(t, "elif", language)
		above := parsed(t, written(`"find":{"select":"literal","as":"l","where":["and",["literalIs","$l","integer"],[">","$l.value",50]]}`))
		equals := parsed(t, written(`"find":{"select":"literal","as":"l","where":["literalEquals","$l",100]}`))
		if found := above.Find(codebase); len(found) != 1 {
			t.Errorf("in %s an integer literal above 50 is found %d times, where 100 is the one", language, len(found))
		}
		if found := equals.Find(codebase); len(found) != 1 {
			t.Errorf("in %s the literal equal to the number 100 is found %d times", language, len(found))
		}
	}
}

func TestAnObligationIsReadAsARecord(t *testing.T) {
	probe := parsed(t, written(`"find":{"select":"function","as":"f","where":["some","$f.obligations","o",["and",["=","$o.origin","external"],["contains","$o.symbol","ToString"]]]}`))
	if found := probe.Find(expected(t, "flags", "csharp")); len(found) != 1 {
		t.Errorf("the override of ToString is found %d times by its obligation's fields", len(found))
	}
}

func TestDescendantsAndSiblingsOfARecoveredNodeAreUnknown(t *testing.T) {
	probe := parsed(t, written(`"find":{"select":"function","as":"f","where":["none",["ownDescendants","$f"],"d",["=","$d.kind","throw"]]}`))
	judged := probe.Judge(expected(t, "recovery-node", "typescript"))
	if len(judged.Undecided) == 0 {
		t.Errorf("a function the parser recovered from has known descendants, so nothing in it is left undecided")
	}
}

func TestAnAbsentNodeHasNoSiblingsAndNoPosition(t *testing.T) {
	found, undecided := overTheOneHandler(t, `["and",["=",["count",["siblings","$site.binding"]],0],["not",["exists",["position","$site.binding"]]]]`)
	if found != 1 || undecided != 0 {
		t.Errorf("an absent node's siblings and position found %d and left %d undecided", found, undecided)
	}
}

func TestValidateRefusesARelationThatReadsItself(t *testing.T) {
	refused(t, written(`"find":{"from":"function","as":"f","relations":{"a":{"from":"b"},"b":{"from":"a"}},"pipeline":[{"join":"a","as":"x","on":{"symbol":"$f.symbol"}},{"emit":"$f"}]}`),
		"find.relations", "a reads b reads a")
}

// found is how many nodes a find over one sample tree reports.
func found(t *testing.T, sample, tree, find string) int {
	t.Helper()

	return len(parsed(t, written(`"find":`+find)).Find(expected(t, sample, tree)))
}

func TestTheValueAndTextOperatorsAnswerFromTheirOperands(t *testing.T) {
	for where, wanted := range map[string][2]int{
		`["versionAtLeast","10.2.1","10.2"]`:                                                 {1, 0},
		`["versionAtLeast","v9.9","10"]`:                                                     {0, 0},
		`["=",["valueKind",["record","a",1]],"record"]`:                                      {1, 0},
		`["=",["coalesce","$site.binding.resolved",["list"],2],["list"]]`:                    {1, 0},
		`["possible",["assignable","$site.binding.resolved","X"]]`:                           {1, 0},
		`["isUnknown",["mood","zzqx"]]`:                                                      {1, 0},
		`["=",["get",["record","a",1,"b",2],"b"],2]`:                                         {1, 0},
		`["=",["paths",["record","app",["record","name",1]],"."],["list","app","app.name"]]`: {1, 0},
		`["=",["add",1,2,3],6]`:                                                              {1, 0},
		`["exists",["divide",1,0]]`:                                                          {0, 1},
		`["=",["remainder",7,3],1]`:                                                          {1, 0},
		`["=",["round",2.5],3]`:                                                              {1, 0},
		`["between",5,1,5]`:                                                                  {1, 0},
		`["=",["range",1,3],["list",1,2,3]]`:                                                 {1, 0},
		`["=",["words","HTTPServerError2go"],["list","http","server","error2go"]]`:           {1, 0},
		`["=",["words","postal_code-line"],["list","postal","code","line"]]`:                 {1, 0},
		`["=",["caseStyle","MAX_SIZE"],"screaming-snake"]`:                                   {1, 0},
		`["=",["caseStyle","postalCode"],"camel"]`:                                           {1, 0},
		`["isCase","total","camel"]`:                                                         {1, 0},
		`["=",["toCase","postalCode","snake"],"postal_code"]`:                                {1, 0},
		`["=",["commonPrefix",["list","addressLine","addressCity"]],"address"]`:              {1, 0},
		`["=",["distance","kitten","sitting"],3]`:                                            {1, 0},
		`["=",["pathSegments","a/./b/../c"],["list","a","c"]]`:                               {1, 0},
		`["=",["cleanPath",["list","a","c"]],"a/c"]`:                                         {1, 0},
		`["=",["extension","src/app.test.ts"],"ts"]`:                                         {1, 0},
		`["=",["dirName","src/app.ts"],"src"]`:                                               {1, 0},
		`["pathFits","/users/7/posts","/users/{id}/posts"]`:                                  {1, 0},
		`["matchesText","getTotal","^get[A-Z]"]`:                                             {1, 0},
		`["=",["stripSuffix","OrderDetector","Detector"],"Order"]`:                           {1, 0},
		`["=",["split","a.b","."],["list","a","b"]]`:                                         {1, 0},
		`["exists",["mood","sends"]]`:                                                        {1, 0},
	} {
		found, undecided := overTheOneHandler(t, where)
		if found != wanted[0] || undecided != wanted[1] {
			t.Errorf("%s found %d and left %d undecided, where %d and %d are right", where, found, undecided, wanted[0], wanted[1])
		}
	}
}

func TestValidateRefusesAPatternThatDoesNotCompile(t *testing.T) {
	refused(t, written(`"find":{"select":"handler","as":"site","where":["matchesText","x","("]}`), "does not compile")
}

func TestTheTreeOperatorsWalkAndCompareTheSampleTrees(t *testing.T) {
	for _, each := range []struct {
		sample, tree, find string
		wanted             int
	}{
		{"elif", "python", `{"select":"if","as":"i","where":["isElseIf","$i"]}`, 1},
		{"elif", "python", `{"select":"return","as":"r","where":["=",["count",["pathConditions","$r"]],2]}`, 2},
		{"elif", "python", `{"select":"return","as":"r","where":["exclusive","$r",["at",["filter",["descendants",["enclosing","$r","function"],"return"],"x",["!=","$x","$r"]],0]]}`, 3},
		{"elif", "python", `{"select":"function","as":"f","where":["and",["=",["complexity","$f","cyclomatic"],3],["=",["count",["dispatchArms",["first",["descendants","$f","if"]]]],3]]}`, 1},
		{"elif", "python", `{"select":"function","as":"f","where":["not",["completes","$f.body"]]}`, 1},
		{"elif", "python", `{"select":"function","as":"f","where":["=",["count",["exitsOf","$f.body"]],3]}`, 1},
		{"elif", "python", `{"select":"function","as":"f","where":["=",["lineCount","$f"],10]}`, 1},
		{"elif", "python", `{"select":"literal","as":"l","where":["and",["=","$l.span.line",6],["=","$l.span.endLine",6],["=","$l.span.column",15]]}`, 1},
		{"return-before-else", "python", `{"select":"if","as":"i","where":["and",["not",["completes","$i"]],["not",["completes","$i.then"]]]}`, 1},
		{"selectors", "python", `{"select":"access","as":"a","where":["and",["isSelf","$a.subject"],["=","$a.member.name","total"]]}`, 4},
		{"selectors", "python", `{"select":"handler","as":"h","where":["some",["ownDescendants","$h","throw"],"t",["carriesCause","$t"]]}`, 1},
		{"selectors", "python", `{"select":"loop","as":"l","where":["=",["count",["exitsOf","$l.body"]],2]}`, 1},
		{"selectors", "python", `{"select":"call","as":"c","where":["=",["get",["consumer","$c"],"use"],"discarded"]}`, 1},
		{"selectors", "python", `{"select":"if","as":"i","where":["exists",["matchesShape","$i",{"kind":"if","condition":{"kind":"logical","operator":"and","left":"?a","right":"?b"}}]]}`, 1},
		{"selectors", "python", `{"select":"if","as":"i","where":["dominates","$i.condition",["first","$i.then.statements"]]}`, 1},
		{"selectors", "python", `{"select":"if","as":"i","where":["not",["dominates",["first","$i.then.statements"],"$i.condition"]]}`, 1},
		{"selectors", "python", `{"select":"function","as":"f","where":["=",["fingerprint","$f","renamed"],["fingerprint","$f","renamed"]]}`, 2},
		{"selectors", "python", `{"select":"function","as":"f","where":["occurs","$f",["first",["filter",["descendants","$f","access"],"x",["=","$x.member.name","total"]]],"exact"]}`, 1},
	} {
		if got := found(t, each.sample, each.tree, each.find); got != each.wanted {
			t.Errorf("%s/%s: %s found %d, where %d is right", each.sample, each.tree, each.find, got, each.wanted)
		}
	}
}

func TestTheListSetAndGraphOperatorsAnswerFromTheirOperands(t *testing.T) {
	edges := `["list",["record","from","a","to","b"],["record","from","b","to","c"],["record","from","c","to","a"],["record","from","c","to","d"]]`
	for where, wanted := range map[string][2]int{
		`["=",["rest",["list",1,2,3]],["list",2,3]]`:                                  {1, 0},
		`["=",["take",["list",1,2,3],2],["list",1,2]]`:                                {1, 0},
		`["=",["drop",["list",1,2,3],5],["list"]]`:                                    {1, 0},
		`["=",["slice","abcdef",1,-1],"bcde"]`:                                        {1, 0},
		`["=",["append",["list",1],["list",2]],["list",1,2]]`:                         {1, 0},
		`["=",["distinct",["list",1,2,1]],["list",1,2]]`:                              {1, 0},
		`["=",["distinctBy",["list",1,2,3],"x",["remainder","$x",2]],["list",1,2]]`:   {1, 0},
		`["=",["count",["groupItems",["list",1,2,3],"x",["remainder","$x",2]]],2]`:    {1, 0},
		`["=",["sortBy",["list",3,1,2],"x","$x"],["list",1,2,3]]`:                     {1, 0},
		`["=",["maxBy",["list",3,9,2],"x","$x"],9]`:                                   {1, 0},
		`["exists",["minBy",["list"],"x","$x"]]`:                                      {0, 0},
		`["=",["union",["list",1,2],["list",2,3]],["list",1,2,3]]`:                    {1, 0},
		`["=",["intersect",["list",1,2],["list",2,3]],["list",2]]`:                    {1, 0},
		`["=",["difference",["list",1,2],["list",2,3]],["list",1]]`:                   {1, 0},
		`["subset",["list",2,1],["list",1,2,3]]`:                                      {1, 0},
		`["disjoint",["list",1],["list",2]]`:                                          {1, 0},
		`["sameItems",["list",1,2,2],["list",2,1]]`:                                   {1, 0},
		`["=",["count",["zip",["list",1,2,3],["list",4]]],1]`:                         {1, 0},
		`["=",["indexOf",["list","a","b"],"b"],1]`:                                    {1, 0},
		`["=",["indexOf","café au lait","au"],5]`:                                     {1, 0},
		`["=",["max",3,7,5],7]`:                                                       {1, 0},
		`["=",["median",["list",4,1,3,2]],2.5]`:                                       {1, 0},
		`["exists",["average",["list"]]]`:                                             {0, 1},
		`["=",["dominant",["list","a","a","b"],0.6],"a"]`:                             {1, 0},
		`["=",["count",["windows",["list",1,2,3,4],2]],3]`:                            {1, 0},
		`["=",["jaccard",["list",1,2],["list",2,3]],["divide",1,3]]`:                  {1, 0},
		`["reaches",` + edges + `,"a","d"]`:                                           {1, 0},
		`["not",["reaches",` + edges + `,"d","a"]]`:                                   {1, 0},
		`["=",["reachableFrom",` + edges + `,"a",1],["list","b"]]`:                    {1, 0},
		`["=",["components",` + edges + `],["list",["list","a","b","c"]]]`:            {1, 0},
		`["and",["inCycle",` + edges + `,"b"],["not",["inCycle",` + edges + `,"d"]]]`: {1, 0},
	} {
		found, undecided := overTheOneHandler(t, where)
		if found != wanted[0] || undecided != wanted[1] {
			t.Errorf("%s found %d and left %d undecided, where %d and %d are right", where, found, undecided, wanted[0], wanted[1])
		}
	}
}

func TestTheTypeAndProgramOperatorsReadTheSampleTrees(t *testing.T) {
	for _, each := range []struct {
		sample, tree, find string
		wanted             int
	}{
		{"flags", "csharp", `{"select":"function","as":"f","where":["has",["functionRole","$f"],"override"]}`, 1},
		{"shadowed-exception", "python", `{"select":"type","as":"t","where":["and",["=",["typeKind","$t"],"class"],["isSubtype","$t","builtins.Exception"]]}`, 1},
		{"selectors", "python", `{"from":"declarations","as":"d","pipeline":[{"where":["=","$d.kind","type"]},{"emit":"$d.node"}]}`, 1},
		{"selectors", "python", `{"from":"function","as":"f","pipeline":[{"join":"calls","as":"c","mode":"anti","on":{"callerNode":"$f"}},{"emit":"$f"}]}`, 0},
		{"selectors", "python", `{"from":"function","as":"f","pipeline":[{"join":"calls","as":"c","mode":"all","on":{"callerNode":"$f"}},{"distinct":"$f"},{"emit":"$f"}]}`, 2},
		{"selectors", "python", `{"select":"function","as":"f","where":[">",["countOf","calls",["record","callerNode","$f"]],0]}`, 2},
	} {
		if got := found(t, each.sample, each.tree, each.find); got != each.wanted {
			t.Errorf("%s/%s: %s found %d, where %d is right", each.sample, each.tree, each.find, got, each.wanted)
		}
	}
}

func TestValidateRefusesRowsOfARelationTheFindLacks(t *testing.T) {
	refused(t, written(`"find":{"select":"function","as":"f","where":["hasRow","callz",["record","caller","$f"]]}`), "callz", "no relation of the find")
}

func TestValidateRefusesAJoinModeItDoesNotKnow(t *testing.T) {
	refused(t, written(`"find":{"from":"function","as":"f","pipeline":[{"join":"calls","as":"c","mode":"outer","on":{"callerNode":"$f"}},{"emit":"$f"}]}`), "mode", "outer")
}
