/**
 * Safely sets the alpha channel (opacity) of a CSS color string.
 * Supports 3, 4, 6, 8 digit hex colors, as well as rgb and rgba.
 * If the input color format is unrecognized, it falls back to the original color.
 *
 * @param color The CSS color string (e.g. #0c4cb4, #9b592da0, rgb(12,76,180), rgba(12,76,180,0.5))
 * @param opacity The target alpha/opacity (either hex string like '33' or numeric float/int like 0.2)
 * @returns A CSS color string (typically in rgba format)
 */
export function adjustColorOpacity(color: string, opacity: string | number): string {
    if (!color) return color;
    
    let trimmed = color.trim();

    // Handle hex colors
    if (trimmed.startsWith('#')) {
        let hex = trimmed.substring(1);
        
        // Check if it is a valid hex color
        if (/^[0-9a-fA-F]{3,8}$/.test(hex)) {
            let r = 0, g = 0, b = 0;
            let targetAlpha = 1.0;

            // Resolve target opacity
            if (typeof opacity === 'string') {
                if (opacity.length === 2 && /^[0-9a-fA-F]{2}$/.test(opacity)) {
                    targetAlpha = parseInt(opacity, 16) / 255;
                } else {
                    targetAlpha = parseFloat(opacity);
                }
            } else {
                targetAlpha = opacity;
            }

            if (hex.length === 3) {
                r = parseInt(hex[0] + hex[0], 16);
                g = parseInt(hex[1] + hex[1], 16);
                b = parseInt(hex[2] + hex[2], 16);
            } else if (hex.length === 4) {
                r = parseInt(hex[0] + hex[0], 16);
                g = parseInt(hex[1] + hex[1], 16);
                b = parseInt(hex[2] + hex[2], 16);
            } else if (hex.length === 6) {
                r = parseInt(hex.substring(0, 2), 16);
                g = parseInt(hex.substring(2, 4), 16);
                b = parseInt(hex.substring(4, 6), 16);
            } else if (hex.length === 8) {
                r = parseInt(hex.substring(0, 2), 16);
                g = parseInt(hex.substring(2, 4), 16);
                b = parseInt(hex.substring(4, 6), 16);
            }

            // Return rgba representation
            return `rgba(${r}, ${g}, ${b}, ${Number(targetAlpha.toFixed(4))})`;
        }
    }

    // Handle rgb/rgba colors
    const rgbRegex = /^rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d\.]+)\s*)?\)$/i;
    const match = rgbRegex.exec(trimmed);
    if (match) {
        const r = match[1];
        const g = match[2];
        const b = match[3];
        
        let targetAlpha = 1.0;
        if (typeof opacity === 'string') {
            if (opacity.length === 2 && /^[0-9a-fA-F]{2}$/.test(opacity)) {
                targetAlpha = parseInt(opacity, 16) / 255;
            } else {
                targetAlpha = parseFloat(opacity);
            }
        } else {
            targetAlpha = opacity;
        }

        return `rgba(${r}, ${g}, ${b}, ${Number(targetAlpha.toFixed(4))})`;
    }

    // Fallback: if we append a hex opacity (e.g. '33') and color is a CSS variable or name,
    // we cannot easily parse it. But if it's already a standard format, we handled it above.
    return color;
}
