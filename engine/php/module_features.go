package php

import (
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func sameModule(consumer, provider engine.Match) bool {
	owner, supplier := consumer.Namespace(), provider.Namespace()
	root, _, nested := strings.Cut(owner, `\`)
	if nested && supplier == root && keepsContainerLifetime(provider) {
		return true
	}
	if !nested && strings.HasPrefix(supplier, root+`\`) && keepsContainerLifetime(EnclosingClass(consumer)) {
		return true
	}

	return sameFeature(consumer, provider)
}

func sameFeature(consumer, provider engine.Match) bool {
	owner := consumer.Namespace()
	feature := featureNamespace(owner)
	if feature == "" {
		feature = controllerFeature(owner, EnclosingClass(consumer).Name())
	}
	if feature == "" {
		return false
	}
	if feature == featureNamespace(provider.Namespace()) {
		return true
	}

	root, name, _ := strings.Cut(feature, `\`)
	if provider.Namespace() == feature && strings.HasPrefix(owner, root+`\Services\`) {
		return true
	}

	return flatFeatureProvider(provider, root, name)
}

func controllerFeature(namespace, class string) string {
	root, layer, _ := strings.Cut(namespace, `\`)
	name, named := strings.CutSuffix(class, "Controller")
	if layer != `Http\Controllers` || !named || name == "" {
		return ""
	}
	return root + `\` + name
}

func flatFeatureProvider(provider engine.Match, root, feature string) bool {
	switch provider.Namespace() {
	case root + `\Services`:
		return provider.Name() == feature+"Service" || provider.Name() == root+feature+"Service"
	case root + `\Repositories`:
		return provider.Name() == feature+"Repository"
	}
	return false
}

func featureNamespace(namespace string) string {
	root, tail, found := strings.Cut(namespace, `\`)
	if !found {
		return ""
	}
	parts := strings.Split(tail, `\`)
	if len(parts) > 2 && parts[0] == "Http" && slices.Contains([]string{"Controllers", "View", "Requests"}, parts[1]) {
		return root + `\` + parts[2]
	}
	if len(parts) > 3 && parts[0] == "Http" && parts[1] == "Api" && isAPIVersion(parts[2]) {
		return root + `\` + parts[3]
	}
	if len(parts) > 1 && slices.Contains([]string{"Services", "Repositories", "Integrations"}, parts[0]) {
		return root + `\` + parts[1]
	}

	return ""
}

func isAPIVersion(segment string) bool {
	digits, found := strings.CutPrefix(segment, "V")
	_, err := strconv.ParseUint(digits, 10, 64)
	return found && err == nil
}
