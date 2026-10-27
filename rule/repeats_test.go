package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// A missing funnel is one question asked in many places: a field of the project's own data compared with the same
// value, the same way, in function after function, where the type that holds the field should name the question
// once. These hold the checks that say it — the repeat, whose field it is, and whether the type already names it —
// and the two things that make the count honest: a question asked either way round is one question, and the method
// that NAMES the question is not one of the places asking it.

// tasks is a project whose state is asked about from everywhere. Task and Plan both declare `state`, which is what
// tells a reading by text from a reading by owner, and Task already names one of its own questions.
var tasks = map[string]string{
	"shop/__init__.py": "",
	"shop/models.py": `from dataclasses import dataclass
from enum import Enum


class TaskState(Enum):
    OPEN = "open"
    STARTED = "started"


class PlanState(Enum):
    OPEN = "open"
    STARTED = "started"


@dataclass(frozen=True)
class Task:
    state: TaskState
    worker: int | None
    title: str

    def is_started(self) -> bool:
        return self.state == TaskState.STARTED


@dataclass(frozen=True)
class Plan:
    state: PlanState
`,
	"shop/reads.py": `from shop.models import Plan, PlanState, Task, TaskState


def working(task: Task) -> bool:
    return task.state == TaskState.STARTED


def idle(task: Task) -> bool:
    return task.state != TaskState.STARTED


def planning(plan: Plan) -> bool:
    return plan.state == PlanState.STARTED


def named(task: Task) -> bool:
    return task.title == "open"
`,
	"shop/writes.py": `from shop.models import Plan, PlanState, Task, TaskState


def start(task: Task) -> str:
    if task.state == TaskState.STARTED:
        return "already"

    return "started"


def unworked(task: Task) -> bool:
    return task.worker is None


def held(task: Task) -> bool:
    return task.worker is None


def free(task: Task) -> bool:
    return task.worker is not None


def planned(plan: Plan) -> bool:
    return plan.state == PlanState.STARTED


def titled(task: Task) -> bool:
    return task.title == "started"
`,
}

// TestARepeatedQuestionIsFoundWhereverItIsAsked holds the count itself: the sites of one question, in functions and
// in files, with the method that names it left out of them.
func TestARepeatedQuestionIsFoundWhereverItIsAsked(t *testing.T) {
	project := pythontest.FromSource(t, tasks)

	for query, want := range map[string]string{
		// `task.state == TaskState.STARTED` is asked in reads.py and writes.py, and `!=` is the same question
		// asked the other way round: three places, two files. Task.is_started names it and is not one of them.
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 3, "files": 2, "eitherWay": true, "by": "owner"}}]}`: "[reads.py:5 reads.py:9 writes.py:5]",
		// Without eitherWay the `!=` is a question of its own, and neither reaches three.
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 3, "files": 2, "by": "owner"}}]}`: "[]",
		// Plan.state is asked twice, so it is under the floor however it is read.
		`{"select": "comparison", "where": [{"declaredBy": "*Plan"}, {"repeated": {"atLeast": 3, "by": "owner"}}]}`: "[]",
		// `task.worker is None` is asked twice and `is not None` once: one question either way, in one file, so
		// the spread is what keeps it out.
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 3, "eitherWay": true, "by": "owner"}}, {"of": "child:left", "name": "worker"}]}`:             "[writes.py:12 writes.py:16 writes.py:20]",
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 3, "files": 2, "eitherWay": true, "by": "owner"}}, {"of": "child:left", "name": "worker"}]}`: "[]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestAQuestionIsGroupedByWhoseFieldItIsAndNotByItsWords holds the reason the owner matters. Task and Plan both
// declare `state` and both are asked about a member called STARTED; read by what is written, with the receiver
// blanked, those are one question of five sites, and read by owner they are two questions, of three and of two. A
// rule that cannot tell them apart names the wrong class as the place the method belongs.
func TestAQuestionIsGroupedByWhoseFieldItIsAndNotByItsWords(t *testing.T) {
	project := pythontest.FromSource(t, tasks)

	byOwner := `{"select": "comparison", "where": [{"repeated": {"atLeast": 5, "eitherWay": true, "by": "owner"}}]}`
	if got := foundAt(t, "python", byOwner, project); got != "[]" {
		t.Errorf("read by owner, five sites of one question were found: %s", got)
	}

	byWords := `{"select": "comparison", "where": [{"repeated": {"atLeast": 5, "eitherWay": true}}]}`
	if got := foundAt(t, "python", byWords, project); got == "[]" {
		t.Error("read by what is written, the two classes' questions were not counted as one, so nothing tells the two readings apart")
	}
}

// TestTheMethodThatNamesAQuestionIsNotOneOfThePlacesAskingIt holds the rule that keeps a funnel from counting among
// the repeats it exists to remove: Task.is_started asks `self.state == TaskState.STARTED`, and with it counted
// there would be four sites where three places ask.
func TestTheMethodThatNamesAQuestionIsNotOneOfThePlacesAskingIt(t *testing.T) {
	project := pythontest.FromSource(t, tasks)

	query := `{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 4, "eitherWay": true, "by": "owner"}}]}`
	if got := foundAt(t, "python", query, project); got != "[]" {
		t.Errorf("the method that names the question was counted among the places asking it: %s", got)
	}
}

// TestARuleKnowsWhetherTheTypeAlreadyNamesTheQuestion holds the difference between the two things such a finding
// has to say: use the name that exists, or give it one.
func TestARuleKnowsWhetherTheTypeAlreadyNamesTheQuestion(t *testing.T) {
	project := pythontest.FromSource(t, tasks)

	for query, want := range map[string]string{
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"answeredBy": true}]}`:                                          "[reads.py:5 reads.py:9 writes.py:5]",
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"answeredBy": false}, {"of": "child:left", "name": "worker"}]}`: "[writes.py:12 writes.py:16 writes.py:20]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestWhoseFieldItIsIsReadFromTheTypeAndNeverFromTheName holds `declaredBy` to answering what a name cannot: the
// class whose own line declares the member. It answers nothing where the scan gives the receiver no type and where
// no declaration of it declares that member, which is what keeps a rule about the project's own data off every
// other object in the file.
func TestWhoseFieldItIsIsReadFromTheTypeAndNeverFromTheName(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/kinds.py": `from dataclasses import dataclass


@dataclass(frozen=True)
class Card:
    state: str


class Outside:
    pass


def mine(card: Card) -> bool:
    return card.state == "open"


def theirs(other: Outside) -> bool:
    return other.state == "open"


def untyped(whatever) -> bool:
    return whatever.state == "open"
`,
	})

	for query, want := range map[string]string{
		`{"select": "comparison", "where": [{"declaredBy": "*Card"}]}`: "[kinds.py:14]",
		`{"select": "comparison", "where": [{"declaredBy": "*"}]}`:     "[kinds.py:14]",
		// The target steps onto that declaration, so every check composes on whose field it is.
		`{"select": "comparison", "where": [{"of": "declaredBy", "hasAnnotation": "dataclass"}]}`: "[kinds.py:14]",
		`{"select": "comparison", "where": [{"of": "declaredBy", "nameLike": "Outside"}]}`:        "[]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestARepeatedLiteralNeedsTheExactReading holds the one choice that is not about comparisons, and the reason it
// exists: normalising blanks the text of a data literal, which is what lets two functions differing only in their
// strings read as one body, and would read every string of a codebase as one repeat.
func TestARepeatedLiteralNeedsTheExactReading(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/keys.py": `def one(row: dict) -> object:
    return row["open_work"]


def two(row: dict) -> object:
    return row["open_work"]
`,
		"shop/more.py": `def three(row: dict) -> object:
    return row["open_work"]


def four(row: dict) -> object:
    return row["closed_work"]
`,
	})

	for query, want := range map[string]string{
		// The same key in three functions across two files; the fourth string is its own.
		`{"select": "literal", "where": [{"repeated": {"atLeast": 3, "files": 2, "exact": true}}]}`: "[keys.py:2 keys.py:6 more.py:2]",
		// Read as `duplicated` reads a body, every string is the same datum, so all four count as one repeat.
		`{"select": "literal", "where": [{"repeated": {"atLeast": 4, "files": 2}}]}`: "[keys.py:2 keys.py:6 more.py:2 more.py:6]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestARepeatIsCountedForAnythingTheTreeHolds holds the step to being about any node and not only a comparison: the
// same reach through a chain, written in function after function, is a missing helper exactly as a repeated
// question is a missing predicate.
func TestARepeatIsCountedForAnythingTheTreeHolds(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/listeners.py": `from shop.events import Event


def first(event: Event) -> int:
    return len(event.data.part().tasks)


def second(event: Event) -> int:
    return len(event.data.part().tasks)
`,
		"shop/more_listeners.py": `from shop.events import Event


def third(event: Event) -> int:
    return len(event.data.part().tasks)
`,
		"shop/events.py": `from dataclasses import dataclass


@dataclass(frozen=True)
class Part:
    tasks: list


@dataclass(frozen=True)
class Data:
    def part(self) -> Part:
        return Part(tasks=[])


@dataclass(frozen=True)
class Event:
    data: Data
`,
	})

	query := `{"select": "member-access", "where": [{"name": "tasks"}, {"repeated": {"atLeast": 3, "files": 2}}]}`
	if got := foundAt(t, "python", query, project); got != "[listeners.py:5 listeners.py:9 more_listeners.py:5]" {
		t.Errorf("the reach written in three listeners was counted as %s", got)
	}
}

// TestHowFarAClassLineRuns holds the one size a class cannot be read for from its own file. A project that allows
// two levels of its own could not say so: `extendsAny` asks whether a type is somewhere in the line, and nothing
// asked how far the line runs. `within` is what keeps the answer about the project's own levels, since the
// framework class underneath them is nobody's to flatten.
func TestHowFarAClassLineRuns(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/models.py": `class Model:
    pass


class Scoped(Model):
    pass


class Task(Scoped):
    pass


class Deep(Task):
    pass


class Loose:
    pass
`,
	})

	for query, want := range map[string]string{
		// Task stands on Scoped and Model: two levels, which is the limit.
		`{"select": "type-declaration", "where": [{"ancestors": {"atMost": 2, "within": "shop.*"}}]}`: "[models.py:1 models.py:17 models.py:5 models.py:9]",
		// Deep stands on three, which is past it.
		`{"select": "type-declaration", "where": [{"ancestors": {"atLeast": 3, "within": "shop.*"}}]}`: "[models.py:13]",
		// A class whose line holds nothing of the project's own: Model and Loose, each standing on nothing.
		`{"select": "type-declaration", "where": [{"ancestors": {"atMost": 0, "within": "shop.*"}}]}`: "[models.py:1 models.py:17]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestAFieldSetFromOutsideItsOwnFile holds the question a frozen model asks of everyone else: its changes go
// through the methods it wrote for them, and a named argument that sets one of its fields from another file has
// gone around those methods. Three shapes name the same field — a copy function taking the object, a method of its
// own, and a construction — and the look-alikes are a keyword that is no field of anything and the model's own
// file, where setting its fields is its own business.
func TestAFieldSetFromOutsideItsOwnFile(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/models.py": `from dataclasses import dataclass, replace


@dataclass(frozen=True)
class Task:
    state: str
    worker: int | None

    def started(self) -> "Task":
        return replace(self, state="started")
`,
		"shop/handlers.py": `from dataclasses import replace

from shop.models import Task


def start(task: Task) -> Task:
    return replace(task, state="started")


def take(task: Task) -> Task:
    return replace(task, worker=1)


def fresh() -> Task:
    return Task(state="open", worker=None)
`,
		"shop/other.py": `def log(message: str, urgent: bool = False) -> None:
    print(message, urgent)


def shout(message: str) -> None:
    log(message, urgent=True)
`,
	})

	for query, want := range map[string]string{
		// A field of Task set by name on a task the call was handed, from a file that is not Task's own. The
		// construction on line 15 names fields too and is not among them: it holds no task to change.
		`{"select": "kind:keyword", "where": [{"declaredBy": "*Task"}, {"declaredElsewhere": true}]}`: "[handlers.py:11 handlers.py:7]",
		// The model's own file sets its own fields, which is what its methods are for.
		`{"select": "kind:keyword", "where": [{"declaredBy": "*Task"}, {"declaredElsewhere": false}]}`: "[models.py:10]",
		// A keyword that names a field of nothing is nobody's business: other.py is not among these.
		`{"select": "kind:keyword", "where": [{"declaredBy": "*"}]}`: "[handlers.py:11 handlers.py:7 models.py:10]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestAThingReferredToFromOneSideOfTheProject holds the question `calledFrom` could only ask of a FOLDER: which
// side of the project refers to a thing. An event nobody listens for is raised and never heard, and the shape that
// says so is a reference that passes a step rather than a reference in a path — a listener names an event in the
// type of a parameter, wherever its file happens to sit.
func TestAThingReferredToFromOneSideOfTheProject(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/events.py": `from dataclasses import dataclass


@dataclass(frozen=True)
class TaskStarted:
    number: int


@dataclass(frozen=True)
class TaskForgotten:
    number: int
`,
		"shop/listeners.py": `from shop.events import TaskStarted


def on_task_started(event: TaskStarted) -> None:
    print(event.number)
`,
		"shop/work.py": `from shop.events import TaskForgotten, TaskStarted


def start(number: int) -> TaskStarted:
    return TaskStarted(number=number)


def forget(number: int) -> TaskForgotten:
    return TaskForgotten(number=number)
`,
	})

	for query, want := range map[string]string{
		// An event some parameter is typed as is heard by something.
		// The reference is the NAME in the annotation; the parameter is what holds it, which is why the step
		// steps up to the parent before asking what it is.
		`{"select": "type-declaration", "where": [{"referredFrom": {"of": "parent", "is": "parameter"}}]}`: "[events.py:4]",
		// One that nothing names in a parameter is raised and never heard, which is the finding.
		`{"select": "type-declaration", "where": [{"not": {"referredFrom": {"of": "parent", "is": "parameter"}}}]}`: "[events.py:9]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}
