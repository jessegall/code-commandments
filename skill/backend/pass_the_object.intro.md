A method that takes `(Workflow $workflow, string $nodeId)` and starts with
`$workflow->graph->nodeById($nodeId)` is asking for the wrong things. It needs the
**node**. The caller named the id and holds the workflow — so the caller should
resolve, and pass the resolved object.