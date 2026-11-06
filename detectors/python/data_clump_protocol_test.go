package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func TestAClassAnsweringAProtocolOwnsNoneOfItsSignature(t *testing.T) {
	answering := pythontest.FromSource(t, map[string]string{
		"app/client.py": `from typing import Protocol


class AiClient(Protocol):
    def vision(self, image_b64: str, media_type: str, prompt: str) -> str: ...


class HttpAiClient:
    def vision(self, image_b64: str, media_type: str, prompt: str) -> str:
        return image_b64 + media_type + prompt
`,
	})

	if found := (python.DataClumpDetector{}).Find(answering); len(found) != 0 {
		t.Errorf("a class answering a Protocol was asked to change the protocol's signature: %v", found)
	}

	alone := pythontest.FromSource(t, map[string]string{
		"app/sender.py": `class Postman:
    def send(self, image_b64: str, media_type: str, prompt: str) -> str:
        return image_b64 + media_type + prompt


class Courier:
    def deliver(self, image_b64: str, media_type: str, prompt: str) -> str:
        return prompt + media_type + image_b64
`,
	})

	if found := (python.DataClumpDetector{}).Find(alone); len(found) == 0 {
		t.Error("three parameters travelling together with no protocol behind them are still a clump")
	}
}
