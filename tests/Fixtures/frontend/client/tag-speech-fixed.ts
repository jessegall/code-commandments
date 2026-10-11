// @example SideTableTrait good
// @fixed SideTableTrait
type SpokenTag = { name: string; command: string; saidInChat: boolean }

// @fixed SideTableTrait
const TAGS: SpokenTag[] = [
  { name: 'reply', command: 'message reply', saidInChat: false },
  { name: 'log', command: 'work log', saidInChat: false },
  { name: 'todo', command: 'todo create', saidInChat: true },
]

// @fixed SideTableTrait
const QUIET = new Set(TAGS.filter((tag) => !tag.saidInChat).map((tag) => tag.name))

// @fixed SideTableTrait
export function saidInChat(tag: SpokenTag): boolean {
  return tag.saidInChat
}
