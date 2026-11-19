export interface ParsedItemModifier {
    group: string | null;
    name: string;
    price: number;
    text: string;
}

export interface ParsedToppingsAndNote {
    toppings: ParsedItemModifier[];
    note: string | null;
}

/**
 * Parses food item modifiers and notes into a clean, concise structure.
 *
 * Expected format from user requirement:
 * Toping :
 * 1. Toping 1
 * 2. Toping 2
 *
 * Catatan :
 * [customer note]
 */
export function parseToppingsAndNote(item: any): ParsedToppingsAndNote {
    const toppings: ParsedItemModifier[] = [];
    let note: string | null = null;

    // 1. Structured modifiers (preferred)
    if (Array.isArray(item?.selected_modifiers) && item.selected_modifiers.length > 0) {
        for (const sm of item.selected_modifiers) {
            const name = (sm.name || '').trim();
            const group = sm.group_name ? String(sm.group_name).trim() : null;
            const price = Number(sm.additional_price || 0);
            const priceStr = price > 0 ? ` (+Rp ${price.toLocaleString('id-ID')})` : '';
            const text = group ? `${group}: ${name}${priceStr}` : `${name}${priceStr}`;
            if (name) {
                toppings.push({ group, name, price, text });
            }
        }
    }

    // 2. Parse note field
    if (item?.note && typeof item.note === 'string') {
        const rawNote = item.note.trim();

        if (rawNote.includes('Catatan:')) {
            const parts = rawNote.split(/Catatan:/i);
            const userNote = parts[1]?.trim();
            if (userNote) {
                note = userNote;
            }

            // Fallback for older orders without structured selected_modifiers
            if (toppings.length === 0 && parts[0]?.trim()) {
                const modParts = parts[0].split('|').map((s: string) => s.trim()).filter(Boolean);
                for (const mp of modParts) {
                    toppings.push({ group: null, name: mp, price: 0, text: mp });
                }
            }
        } else if (toppings.length === 0) {
            if (rawNote.includes('|')) {
                // Serialized modifiers string (no real user note)
                const modParts = rawNote.split('|').map((s: string) => s.trim()).filter(Boolean);
                for (const mp of modParts) {
                    toppings.push({ group: null, name: mp, price: 0, text: mp });
                }
            } else {
                // Pure customer note without modifiers
                note = rawNote;
            }
        } else {
            // selected_modifiers already populated toppings.
            // Check if rawNote is simply the serialized modifier string or an actual customer note.
            const isJustModifiers = rawNote.includes('|') ||
                toppings.some((t) => rawNote.includes(t.name) || (t.group && rawNote.includes(t.group)));

            if (!isJustModifiers && rawNote) {
                note = rawNote;
            }
        }
    }

    return { toppings, note };
}
