/**
 * Randomness helpers shared by all games.
 *
 * Pure ES module: no DOM access and no bare (importmap) imports. Every function takes an
 * injectable `random` source so tests can be deterministic.
 */

/**
 * Returns a random integer in [min, max] (both inclusive).
 *
 * @param {number} min
 * @param {number} max
 * @param {() => number} [random] source of randomness in [0, 1)
 * @returns {number}
 */
export function randomInt(min, max, random = Math.random) {
    if (!Number.isInteger(min) || !Number.isInteger(max) || min > max) {
        throw new RangeError(`Invalid range: [${min}, ${max}]`);
    }

    return min + Math.floor(random() * (max - min + 1));
}

/**
 * Returns a shuffled copy of `items` (Fisher–Yates). The input is not modified.
 *
 * @template T
 * @param {readonly T[]} items
 * @param {() => number} [random] source of randomness in [0, 1)
 * @returns {T[]}
 */
export function shuffle(items, random = Math.random) {
    const result = [...items];

    for (let i = result.length - 1; i > 0; i--) {
        const j = randomInt(0, i, random);
        [result[i], result[j]] = [result[j], result[i]];
    }

    return result;
}
