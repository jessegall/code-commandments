export function label(name: string | null, other: string): string {
  // @contract kind/fallback
  // @contract fact/fallback/trigger = "null"
  const first = name ?? other;
  // @contract kind/logical
  // @contract fact/logical/operator = "or"
  const second = name || other;
  return first + second;
}
