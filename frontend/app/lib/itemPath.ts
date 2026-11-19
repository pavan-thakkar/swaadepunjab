/** Same rule as Laravel's Str::slug: "Dal Makhani, Rice" → "dal-makhani-rice" */
export function slugify(name: string): string {
  return (name || '')
    .normalize('NFKD').replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/@/g, ' at ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/** Pretty dish address: /item/paneer-tikka/ (falls back to the number if the name has no letters) */
export function itemPath(item: { id?: number | string; menu_item_id?: number; name?: string; slug?: string | null }): string {
  const slug = item.slug || slugify(item.name || '');
  return `/item/${slug || item.id || item.menu_item_id}/`;
}
