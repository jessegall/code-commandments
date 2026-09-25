An object that holds the data to answer a question should answer it. When the answer is computed
somewhere else — a separate class reaching in to read its fields and derive a result — the behaviour
has been **exiled** from its home. Move it back: `$node->edges()`, not `EdgeDetector::detect($node)`.