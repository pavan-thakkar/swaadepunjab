/**
 * Forgiving menu search: ignores case and spaces, understands common Hindi/Punjabi
 * spellings (panner → paneer, alu → aloo, daal → dal…), matches every word typed,
 * and tolerates one typo in longer words.
 */

type Searchable = { id: number; name: string; description?: string | null; category?: string | null };

// Spelling variants → one standard form
const VARIANTS: Record<string, string> = {
  panner: 'paneer', panir: 'paneer', paner: 'paneer', pneer: 'paneer',
  alu: 'aloo', aaloo: 'aloo', aalu: 'aloo', potato: 'aloo',
  daal: 'dal', dhal: 'dal', dall: 'dal', lentil: 'dal',
  nan: 'naan', nann: 'naan',
  chaas: 'chhas', chhaas: 'chhas', chas: 'chhas', chaach: 'chhas', buttermilk: 'chhas',
  parantha: 'paratha', prantha: 'paratha', parotta: 'paratha', paratta: 'paratha',
  puri: 'puri', poori: 'puri', puris: 'puri', pooris: 'puri',
  rotis: 'roti', chapati: 'roti', chapatti: 'roti', phulka: 'roti',
  chawal: 'rice', chaawal: 'rice', pulav: 'pulao', pulaw: 'pulao', biriyani: 'biryani', biryanee: 'biryani',
  sabzi: 'sabji', subji: 'sabji', sabjee: 'sabji', sabzee: 'sabji', subzi: 'sabji',
  makhni: 'makhani', makkhani: 'makhani',
  tikkah: 'tikka', tika: 'tikka',
  kadhai: 'kadai', karahi: 'kadai', kadhi: 'kadhi',
  chhole: 'chole', chholey: 'chole', choley: 'chole', chana: 'chole', channa: 'chole',
  rajmah: 'rajma', raajma: 'rajma',
  lassi: 'lassi', lasi: 'lassi',
  thaali: 'thali', combo: 'combo', combos: 'combo',
  jeera: 'jeera', zeera: 'jeera', jira: 'jeera', cumin: 'jeera',
  gobi: 'gobi', gobhi: 'gobi', cauliflower: 'gobi',
  matar: 'matar', mutter: 'matar', mattar: 'matar', peas: 'matar',
  palak: 'palak', spinach: 'palak',
  mirchi: 'mirchi', mirch: 'mirchi', chilli: 'mirchi', chili: 'mirchi',
};

export function normalizeWord(w: string): string {
  const clean = w.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]/g, '');
  return VARIANTS[clean] || clean;
}

function words(text: string): string[] {
  return text.toLowerCase().split(/[^a-z0-9]+/i).filter(Boolean).map(normalizeWord);
}

/** Small edit distance (stops early above 1). */
function withinOneEdit(a: string, b: string): boolean {
  if (a === b) return true;
  const la = a.length, lb = b.length;
  if (Math.abs(la - lb) > 1) return false;
  let i = 0, j = 0, edits = 0;
  while (i < la && j < lb) {
    if (a[i] === b[j]) { i++; j++; continue; }
    if (++edits > 1) return false;
    if (la > lb) i++;
    else if (lb > la) j++;
    else { i++; j++; }
  }
  return edits + (la - i) + (lb - j) <= 1;
}

/** Score one item for a query; 0 = no match. Higher = better. */
export function scoreItem(item: Searchable, query: string, categoryLabel = ''): number {
  const qWords = words(query);
  if (qWords.length === 0) return 0;

  const nameWords = words(item.name);
  const nameJoined = nameWords.join('');
  const descWords = words(item.description || '');
  const catWords = words(`${categoryLabel} ${item.category || ''}`);
  const all = [...nameWords, ...catWords, ...descWords];

  let score = 0;
  for (const q of qWords) {
    let best = 0;
    // name
    if (nameWords.some(w => w === q)) best = 10;
    else if (nameWords.some(w => w.startsWith(q))) best = 8;
    else if (q.length >= 3 && nameJoined.includes(q)) best = 6;
    else if (q.length >= 4 && nameWords.some(w => withinOneEdit(w, q))) best = 5;
    // category
    if (!best && catWords.some(w => w === q || w.startsWith(q))) best = 4;
    // description
    if (!best && descWords.some(w => w === q || (q.length >= 3 && w.startsWith(q)))) best = 3;
    if (!best && q.length >= 5 && all.some(w => withinOneEdit(w, q))) best = 2;
    if (!best) return 0; // every typed word must match something
    score += best;
  }
  // whole phrase at the start of the name ranks first
  const qJoined = qWords.join('');
  if (nameJoined.startsWith(qJoined)) score += 5;
  return score;
}

export function searchMenu<T extends Searchable>(items: T[], query: string, labels: Record<string, string> = {}): T[] {
  if (!query.trim()) return [];
  return items
    .map(it => ({ it, s: scoreItem(it, query, labels[it.category || ''] || '') }))
    .filter(x => x.s > 0)
    .sort((a, b) => b.s - a.s || a.it.name.localeCompare(b.it.name))
    .map(x => x.it);
}
