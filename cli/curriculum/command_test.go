package curriculum

import (
	"testing"
	"testing/fstest"

	"github.com/jessegall/code-commandments/skills"
)

func TestEveryRegisteredSkillLoadsFromTheShippedCurriculum(t *testing.T) {
	if problems := Problems(skills.Files); len(problems) > 0 {
		t.Fatalf("the shipped curriculum does not load: %v", problems)
	}
}

func TestACurriculumMissingItsLessonsFailsTheCheck(t *testing.T) {
	if problems := Problems(fstest.MapFS{}); len(problems) == 0 {
		t.Fatal("an empty curriculum passed the check")
	}
}
