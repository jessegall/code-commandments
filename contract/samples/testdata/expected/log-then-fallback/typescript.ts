export function load(): string {
  try {
    return read();
  } catch (error) {
    console.warn("read failed", error);
    return "";
  }
}
