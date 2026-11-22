export function doubled(
  total: number, // @label total binding
): number {
  return total * 2 // @contract promise/read-links-binding -> total
}
