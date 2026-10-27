import { describe, expect, it } from 'vitest';
import { randomInt } from '../../../../assets/games/rekenen/index.js';

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
