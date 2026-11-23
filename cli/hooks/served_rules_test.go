package hooks

const ownRule = `{"$schema": "commandments:generic-rule/v2", "engine": ["php"], "sin": {"name": "own-rule", "description": "written while the service ran", "skill": "php/absence"}, "find": {"select": "call", "as": "c", "where": ["=", "$c.callee.member.name", "select"], "emit": "$c"}}`
