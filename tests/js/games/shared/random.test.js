import { describe, expect, it } from 'vitest';
import { randomInt, shuffle } from '../../../../assets/games/shared/random.js';

describe('randomInt', () => {
    it('returns the lower bound for the lowest random value', () => {
        expect(randomInt(1, 10, () => 0)).toBe(1);
    });

    it('returns the upper bound for the highest random value', () => {
        expect(randomInt(1, 10, () => 0.9999)).toBe(10);
    });

    it('stays within bounds', () => {
        for (let i = 0; i < 100; i++) {
            const value = randomInt(3, 7);
            expect(value).toBeGreaterThanOrEqual(3);
            expect(value).toBeLessThanOrEqual(7);
        }
    });

    it('rejects an invalid range', () => {
        expect(() => randomInt(5, 1)).toThrow(RangeError);
        expect(() => randomInt(0.5, 2)).toThrow(RangeError);
    });
});

describe('shuffle', () => {
    it('keeps all elements', () => {
        const items = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        expect(shuffle(items).toSorted((a, b) => a - b)).toEqual(items);
    });

    it('does not modify the input', () => {
        const items = [1, 2, 3];
        shuffle(items, () => 0);

        expect(items).toEqual([1, 2, 3]);
    });

    it('is deterministic for a given random source', () => {
        // random() === 0 always swaps with index 0: [1, 2, 3, 4] -> [2, 3, 4, 1]
        expect(shuffle([1, 2, 3, 4], () => 0)).toEqual([2, 3, 4, 1]);
        // random() close to 1 always swaps an element with itself
        expect(shuffle([1, 2, 3, 4], () => 0.9999)).toEqual([1, 2, 3, 4]);
    });
});
