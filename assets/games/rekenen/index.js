/**
 * Rekenen game logic.
 *
 * Pure ES module: no DOM access and no bare (importmap) imports, so it can be
 * unit-tested with Vitest. Wire it to the page from assets/controllers/rekenen_controller.js.
 *
 * Game mechanics are not specified yet; this is a placeholder.
 */

/**
 * Returns a random integer in [min, max] (both inclusive).
 *
 * @param {number} min
 * @param {number} max
 * @param {() => number} [random] source of randomness in [0, 1); injectable for tests
 * @returns {number}
 */
export function randomInt(min, max, random = Math.random) {
    if (!Number.isInteger(min) || !Number.isInteger(max) || min > max) {
        throw new RangeError(`Invalid range: [${min}, ${max}]`);
    }

    return min + Math.floor(random() * (max - min + 1));
}
