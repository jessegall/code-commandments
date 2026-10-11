// @example SideTableTrait bad
type SpeechTag = { name: string; command: string }

const TAGS: SpeechTag[] = [
  { name: 'reply', command: 'message reply' },
  { name: 'log', command: 'work log' },
  { name: 'todo', command: 'todo create' },
]

// @sin SideTableTrait
const QUIET = new Set(['reply', 'log'])

export function saidInChat(name: string): boolean {
  return !QUIET.has(name)
}
