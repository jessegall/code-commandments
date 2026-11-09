package story

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"sync"
)

const binaryVariable = "STORY_BINARY"

const commandmentsPackage = "github.com/jessegall/code-commandments/cmd/commandments"

var realBinary = sync.OnceValues(buildsTheBinary)

// PlainProject is a throwaway git project with no journal anywhere near it, judged by the real binary run directly, the one place a cold read can be told.
type PlainProject struct {
	story  *Story
	folder string
}

// PlainFile is one file a plain project holds, at its path inside the project.
type PlainFile struct {
	Path string
	Text string
}

func buildsTheBinary() (string, error) {
	if named := os.Getenv(binaryVariable); named != "" {
		return named, nil
	}

	folder, err := os.MkdirTemp("", "story-binary-")
	if err != nil {
		return "", err
	}

	binary := filepath.Join(folder, "commandments")
	if printed, err := exec.Command("go", "build", "-o", binary, commandmentsPackage).CombinedOutput(); err != nil {
		return "", fmt.Errorf("the real binary could not be built: %w\n%s", err, printed)
	}

	return binary, nil
}

func (s *Story) ScratchProject(label string, files ...PlainFile) *PlainProject {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("makes a plain project of "+shapeOf(label)))

	if s.rehearsal {
		return &PlainProject{story: s, folder: "/rehearsal/plain"}
	}

	project := &PlainProject{story: s, folder: s.t.TempDir()}

	for _, file := range files {
		project.holds(file)
	}

	project.git("init", "--quiet")
	project.git("add", "--all")
	project.git("commit", "--quiet", "--allow-empty", "--message", "the scratch project")

	return project
}

func (p *PlainProject) Folder() string {
	return p.folder
}

func (p *PlainProject) Worktree() *PlainProject {
	p.story.t.Helper()

	p.story.steps = append(p.story.steps, StepOf("cuts a worktree of the plain project"))

	if p.story.rehearsal {
		return p
	}

	worktree := &PlainProject{story: p.story, folder: filepath.Join(p.story.t.TempDir(), "worktree")}

	p.git("worktree", "add", "--quiet", worktree.folder)
	p.story.t.Cleanup(func() { _ = p.command("worktree", "remove", "--force", worktree.folder).Run() })

	return worktree
}

func (p *PlainProject) Judges(args ...string) Ran {
	p.story.t.Helper()

	return p.JudgesWith(nil, args...)
}

func (p *PlainProject) JudgesWith(env []string, args ...string) Ran {
	p.story.t.Helper()

	p.story.steps = append(p.story.steps, stepOfWords("judges the plain project with the real binary", 0, args))

	if p.story.rehearsal {
		return Ran{}
	}

	binary, err := realBinary()
	if err != nil {
		p.story.Fails(err.Error())
	}

	var out, errors strings.Builder

	run := exec.Command(binary, append([]string{"judge"}, args...)...)
	run.Dir = p.folder
	run.Env = append(os.Environ(), env...)
	run.Stdout, run.Stderr = &out, &errors

	if err := run.Run(); run.ProcessState == nil {
		p.story.Fails("the real binary did not start: " + err.Error())
	}

	return Ran{Code: run.ProcessState.ExitCode(), Out: out.String(), Err: errors.String()}
}

func (p *PlainProject) holds(file PlainFile) {
	p.story.t.Helper()

	path := filepath.Join(p.folder, file.Path)

	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		p.story.Fails(err.Error())
	}

	if err := os.WriteFile(path, []byte(file.Text), 0o644); err != nil {
		p.story.Fails(err.Error())
	}
}

func (p *PlainProject) git(args ...string) {
	p.story.t.Helper()

	if printed, err := p.command(args...).CombinedOutput(); err != nil {
		p.story.Fails(fmt.Sprintf("git %s failed: %v\n%s", strings.Join(args, " "), err, printed))
	}
}

func (p *PlainProject) command(args ...string) *exec.Cmd {
	identified := append([]string{"-C", p.folder, "-c", "user.name=story", "-c", "user.email=story@example.invalid"}, args...)

	return exec.Command("git", identified...)
}
