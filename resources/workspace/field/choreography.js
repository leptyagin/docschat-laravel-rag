/**
 * The composition as a function of scroll progress. Every layer has its own
 * keyframes, so they do not move together, and its own lag, so a fast scroll
 * pulls the layers apart and they settle one after another. Keyframes are
 * [progress, x, y, radiusX, radiusY, opacity], with y growing upwards.
 */
const SKY = [0.02, 0.576, 0.776];
const CYAN = [0.2, 0.83, 0.88];
const ICE = [0.72, 0.95, 0.97];
const PINK = [0.847, 0.224, 0.584];
const HOT = [0.98, 0.42, 0.68];
const VIOLET = [0.4, 0.22, 0.8];
const DEEP = [0.02, 0.13, 0.33];

export const LAYERS = [
    {
        color: PINK,
        lag: 5.5,
        orbit: [0.05, 0.04, 0.13, 0.17, 0.4],
        keys: [
            [0, 0.42, 0.42, 0.27, 0.34, 0.95],
            [0.22, 0.6, 0.62, 0.33, 0.24, 0.88],
            [0.5, 0.18, 0.72, 0.24, 0.38, 0.8],
            [0.78, 0.74, 0.3, 0.36, 0.26, 0.92],
            [1, 0.36, 0.14, 0.3, 0.32, 0.95],
        ],
    },
    {
        color: DEEP,
        lag: 3.2,
        orbit: [0.03, 0.05, 0.09, 0.11, 1.9],
        keys: [
            [0, 0.68, 0.62, 0.15, 0.56, 0.92],
            [0.3, 0.86, 0.4, 0.2, 0.6, 0.8],
            [0.62, 0.3, 0.24, 0.3, 0.3, 0.86],
            [1, 0.84, 0.78, 0.18, 0.5, 0.9],
        ],
    },
    {
        color: CYAN,
        lag: 7.5,
        orbit: [0.06, 0.05, 0.15, 0.1, 2.6],
        keys: [
            [0, 0.08, 0.84, 0.36, 0.26, 0.82],
            [0.36, 0.34, 0.26, 0.26, 0.3, 0.7],
            [0.7, 0.92, 0.66, 0.3, 0.34, 0.78],
            [1, 0.14, 0.58, 0.4, 0.24, 0.74],
        ],
    },
    {
        color: VIOLET,
        lag: 2.4,
        orbit: [0.04, 0.03, 0.08, 0.14, 3.3],
        keys: [
            [0, 0.28, 0.08, 0.3, 0.2, 0.7],
            [0.26, 0.2, 0.4, 0.2, 0.26, 0.42],
            [0.55, 0.62, 0.52, 0.32, 0.3, 0.86],
            [0.84, 0.3, 0.84, 0.24, 0.22, 0.6],
            [1, 0.64, 0.3, 0.3, 0.3, 0.76],
        ],
    },
    {
        color: ICE,
        lag: 9,
        orbit: [0.07, 0.04, 0.19, 0.12, 4.4],
        keys: [
            [0, 0.92, 0.94, 0.2, 0.12, 0.42],
            [0.18, 0.72, 0.84, 0.1, 0.1, 0],
            [0.46, 0.46, 0.92, 0.3, 0.1, 0.5],
            [0.72, 0.1, 0.34, 0.1, 0.16, 0.08],
            [1, 0.9, 0.12, 0.24, 0.1, 0.46],
        ],
    },
    {
        color: HOT,
        lag: 4.2,
        orbit: [0.05, 0.06, 0.16, 0.2, 5.2],
        keys: [
            [0, 0.5, 0.5, 0.1, 0.1, 0],
            [0.3, 0.3, 0.46, 0.14, 0.2, 0.08],
            [0.46, 0.76, 0.34, 0.16, 0.24, 0.8],
            [0.68, 0.5, 0.8, 0.2, 0.14, 0.34],
            [0.9, 0.2, 0.3, 0.12, 0.12, 0],
            [1, 0.2, 0.3, 0.12, 0.12, 0],
        ],
    },
    {
        color: SKY,
        lag: 6.4,
        orbit: [0.05, 0.05, 0.11, 0.09, 0.9],
        keys: [
            [0, 0.9, 0.2, 0.3, 0.3, 0.6],
            [0.4, 0.5, 0.1, 0.42, 0.22, 0.8],
            [0.8, 0.54, 0.62, 0.2, 0.3, 0.35],
            [1, 0.1, 0.9, 0.34, 0.2, 0.7],
        ],
    },
];

/**
 * How the glass and the blur change along the page: [progress, softness, ribs
 * refraction, vertical shear, glass strength, band contrast].
 */
export const ATMOSPHERE = [
    [0, 1.0, 2.5, 0.05, 0.92, 0.3],
    [0.24, 1.32, 1.5, 0.02, 0.6, 0.72],
    [0.52, 0.82, 3.3, 0.09, 1, 0.2],
    [0.76, 1.42, 1.9, 0.03, 0.7, 0.62],
    [1, 1.08, 2.8, 0.07, 0.95, 0.34],
];

function smoother(value) {
    return value * value * value * (value * (value * 6 - 15) + 10);
}

/**
 * Reads a keyframe track, easing into and out of every keyframe.
 */
export function sample(keys, progress, into) {
    let upper = 1;

    while (upper < keys.length - 1 && keys[upper][0] < progress) {
        upper++;
    }

    const from = keys[upper - 1];
    const to = keys[upper];
    const amount = smoother(Math.min(1, Math.max(0, (progress - from[0]) / (to[0] - from[0]))));

    for (let index = 1; index < from.length; index++) {
        into[index - 1] = from[index] + (to[index] - from[index]) * amount;
    }

    return into;
}
