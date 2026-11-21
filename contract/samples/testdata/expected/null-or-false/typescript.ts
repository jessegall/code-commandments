export function label(name: string | null, other: string): string {
  const first = name ?? other;
  const second = name || other;
  return first + second;
}
