const HEX_COLOR = /^#[0-9a-f]{6}$/i;

export const DEFAULT_COLOR = '#0593c6';

export function validColor(color) {
    return HEX_COLOR.test(color ?? '') ? color : DEFAULT_COLOR;
}

/**
 * Picks the text color that stays readable on the given background.
 */
export function readableOn(color) {
    const channels = [1, 3, 5].map((start) => parseInt(color.slice(start, start + 2), 16) / 255);
    const [red, green, blue] = channels.map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4));
    const luminance = 0.2126 * red + 0.7152 * green + 0.0722 * blue;

    return luminance > 0.4 ? '#111827' : '#ffffff';
}
