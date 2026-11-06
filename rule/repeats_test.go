package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

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

func TestARepeatedQuestionIsFoundWhereverItIsAsked(t *testing.T) {
	project := pythontest.FromSource(t, tasks)

	for query, want := range map[string]string{
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 3, "files": 2, "eitherWay": true, "by": "owner"}}]}`:                                         "[reads.py:5 reads.py:9 writes.py:5]",
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 3, "files": 2, "by": "owner"}}]}`:                                                            "[]",
		`{"select": "comparison", "where": [{"declaredBy": "*Plan"}, {"repeated": {"atLeast": 3, "by": "owner"}}]}`:                                                                        "[]",
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 3, "eitherWay": true, "by": "owner"}}, {"of": "child:left", "name": "worker"}]}`:             "[writes.py:12 writes.py:16 writes.py:20]",
		`{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 3, "files": 2, "eitherWay": true, "by": "owner"}}, {"of": "child:left", "name": "worker"}]}`: "[]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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

func TestTheMethodThatNamesAQuestionIsNotOneOfThePlacesAskingIt(t *testing.T) {
	project := pythontest.FromSource(t, tasks)

	query := `{"select": "comparison", "where": [{"declaredBy": "*Task"}, {"repeated": {"atLeast": 4, "eitherWay": true, "by": "owner"}}]}`
	if got := foundAt(t, "python", query, project); got != "[]" {
		t.Errorf("the method that names the question was counted among the places asking it: %s", got)
	}
}

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
		`{"select": "comparison", "where": [{"declaredBy": "*Card"}]}`:                            "[kinds.py:14]",
		`{"select": "comparison", "where": [{"declaredBy": "*"}]}`:                                "[kinds.py:14]",
		`{"select": "comparison", "where": [{"of": "declaredBy", "hasAnnotation": "dataclass"}]}`: "[kinds.py:14]",
		`{"select": "comparison", "where": [{"of": "declaredBy", "nameLike": "Outside"}]}`:        "[]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "literal", "where": [{"repeated": {"atLeast": 3, "files": 2, "exact": true}}]}`: "[keys.py:2 keys.py:6 more.py:2]",
		`{"select": "literal", "where": [{"repeated": {"atLeast": 4, "files": 2}}]}`:                "[keys.py:2 keys.py:6 more.py:2 more.py:6]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "type-declaration", "where": [{"ancestors": {"atMost": 2, "within": "shop.*"}}]}`:  "[models.py:1 models.py:17 models.py:5 models.py:9]",
		`{"select": "type-declaration", "where": [{"ancestors": {"atLeast": 3, "within": "shop.*"}}]}`: "[models.py:13]",
		`{"select": "type-declaration", "where": [{"ancestors": {"atMost": 0, "within": "shop.*"}}]}`:  "[models.py:1 models.py:17]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "kind:keyword", "where": [{"declaredBy": "*Task"}, {"declaredElsewhere": true}]}`:  "[handlers.py:11 handlers.py:7]",
		`{"select": "kind:keyword", "where": [{"declaredBy": "*Task"}, {"declaredElsewhere": false}]}`: "[models.py:10]",
		`{"select": "kind:keyword", "where": [{"declaredBy": "*"}]}`:                                   "[handlers.py:11 handlers.py:7 models.py:10]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "type-declaration", "where": [{"referredFrom": {"of": "parent", "is": "parameter"}}]}`:          "[events.py:4]",
		`{"select": "type-declaration", "where": [{"not": {"referredFrom": {"of": "parent", "is": "parameter"}}}]}`: "[events.py:9]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

func TestAValueTwoClassesDeclareIsARepeat(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/mark.py": `class MarkListener:
    event = "process.saved"

    def handle(self, run: object) -> None:
        pass
`,
		"shop/dock.py": `class DockListener:
    event = "process.saved"

    def handle(self, run: object) -> None:
        pass
`,
		"shop/label.py": `class LabelListener:
    event = "label.printed"

    def handle(self, run: object) -> None:
        pass
`,
	})

	for query, want := range map[string]string{
		`{"select": "literal", "where": [{"of": "parent", "name": "event"}, {"repeated": {"atLeast": 2, "files": 2, "exact": true}}]}`: "[dock.py:2 mark.py:2]",
		`{"select": "literal", "where": [{"of": "parent", "name": "event"}, {"repeated": {"atMost": 1, "exact": true}}]}`:              "[label.py:2]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("python %s:\n got %s\nwant %s", query, got, want)
		}
	}
}

func TestARepeatedQuestionAnswersForGo(t *testing.T) {
	project := goProject(t, map[string]string{
		"go.mod": "module example.com/shop\n",
		"shop/mark.go": `package shop

func Marked(run *Run) bool {
	return run.State == "saved"
}

type Run struct {
	State string
}
`,
		"shop/dock.go": `package shop

func Docked(run *Run) bool {
	return run.State == "saved"
}
`,
		"shop/label.go": `package shop

func Labelled(run *Run) bool {
	return run.State == "printed"
}
`,
	})

	for query, want := range map[string]string{
		`{"select": "comparison", "where": [{"repeated": {"atLeast": 3, "files": 3}}]}`:    "[dock.go:4 label.go:4 mark.go:4]",
		`{"select": "comparison", "where": [{"repeated": {"atLeast": 2, "exact": true}}]}`: "[dock.go:4 mark.go:4]",
		`{"select": "comparison", "where": [{"repeated": {"atMost": 1, "exact": true}}]}`:  "[label.go:4]",
	} {
		if got := foundAt(t, "go", query, project); got != want {
			t.Errorf("go %s:\n got %s\nwant %s", query, got, want)
		}
	}
}
