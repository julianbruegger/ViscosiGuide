// Small DOM helpers shared across pages. All text goes in via textContent, so
// user-supplied content is never interpreted as HTML (XSS-safe by construction).

export function el<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  props: Partial<Record<string, string>> & { class?: string; text?: string } = {},
  children: (Node | string)[] = []
): HTMLElementTagNameMap[K] {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(props)) {
    if (v == null) continue;
    if (k === 'class') node.className = v;
    else if (k === 'text') node.textContent = v;
    else node.setAttribute(k, v);
  }
  for (const c of children) node.append(c);
  return node;
}

/** Render a 1–5 star string (filled ★ / empty ☆) for an average or exact value. */
export function starsEl(value: number | null): HTMLElement {
  const span = el('span', { class: 'vg-stars' });
  const rounded = value == null ? 0 : Math.round(value);
  for (let i = 1; i <= 5; i++) {
    const s = el('span', { text: i <= rounded ? '★' : '☆' });
    if (i > rounded) s.className = 'empty';
    span.append(s);
  }
  return span;
}

const CATEGORY_LABELS: Record<string, string> = {
  canteen: 'Kantine',
  restaurant: 'Restaurant',
  fastfood: 'Fast Food',
  italian: 'Italienisch',
  asian: 'Asiatisch',
  bakery: 'Bäckerei',
  cafe: 'Café',
  bar: 'Bar',
  vegetarian: 'Vegetarisch',
  other: 'Sonstiges',
};

export const CATEGORIES = Object.keys(CATEGORY_LABELS);

export function categoryLabel(cat: string): string {
  return CATEGORY_LABELS[cat] ?? cat;
}

/** Price level 1–4 → "$".."$$$$". */
export function priceLevel(level: number): string {
  return '$'.repeat(Math.max(1, Math.min(4, level)));
}

/** Show a message in a container element with a status style. */
export function showMessage(container: HTMLElement, text: string, kind: 'ok' | 'error' = 'error'): void {
  container.innerHTML = '';
  container.append(el('div', { class: `vg-msg vg-msg--${kind}`, text }));
}

/** Format an ISO/DB datetime for display (locale de-CH). */
export function fmtDate(value: string | null): string {
  if (!value) return '';
  const d = new Date(value.replace(' ', 'T'));
  if (isNaN(d.getTime())) return value;
  return d.toLocaleString('de-CH', { dateStyle: 'medium', timeStyle: 'short' });
}
