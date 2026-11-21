package hooks

import ()

const ownRule = `{"engine": "backend", "sin": {"name": "own-rule", "description": "written while the service ran", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"name": "select"}]}}`
