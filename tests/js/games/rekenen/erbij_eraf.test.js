import { describe, expect, it } from 'vitest';
import {
    allSums,
    LEVELS,
    OPERATIONS,
    pickSums,
    ROUND_SIZE,
} from '../../../../assets/games/rekenen/erbij_eraf.js';
import { answerOf } from '../../../../assets/games/shared/round.js';

const units = (n) => n % 10;
const key = (sum) => `${sum.left}${sum.operator}${sum.right}`;

/** A simple seeded source of randomness, so failures can be reproduced. */
const seeded = (seed) => () => {
    seed = (seed * 16807) % 2147483647;
    return (seed - 1) / 2147483646;
};

/** What each level must look like, written out independently of the game's own rules. */
const EXPECTED = {
    [LEVELS.TOT_10]: {
        plus: ({ left, right }) => left + right <= 10,
        min: ({ left }) => left <= 10,
        examples: { plus: [3, 4], min: [9, 5] },
        not: { plus: [8, 5], min: [12, 5] },
    },
    [LEVELS.TOT_20]: {
        plus: ({ left, right }) => left > 10 && left + right < 20 && units(left) + right < 10,
        min: ({ left, right }) => left > 10 && left < 20 && right <= units(left),
        examples: { plus: [12, 5], min: [17, 4] },
        not: { plus: [8, 5], min: [13, 6] },
    },
    [LEVELS.TOT_20_BRUG]: {
        plus: ({ left, right }) => left < 10 && right < 10 && left + right > 10,
        min: ({ left, right }) => left > 10 && left < 20 && right < 10 && right > units(left),
        examples: { plus: [8, 5], min: [13, 6] },
        not: { plus: [12, 5], min: [17, 4] },
    },
    [LEVELS.TIENTALLEN]: {
        plus: ({ left, right }) => left % 10 === 0 && right % 10 === 0 && left + right <= 100,
        min: ({ left, right }) => left % 10 === 0 && right % 10 === 0,
        examples: { plus: [30, 40], min: [90, 50] },
        not: { plus: [34, 25], min: [68, 25] },
    },
    [LEVELS.TOT_100]: {
        plus: ({ left, right }) =>
            left + right > 20 && left + right < 100 && units(left) + units(right) < 10,
        min: ({ left, right }) => left > 20 && units(right) <= units(left),
        examples: { plus: [34, 25], min: [68, 25] },
        not: { plus: [38, 7], min: [52, 18] },
    },
    [LEVELS.TOT_100_BRUG]: {
        plus: ({ left, right }) =>
            left + right > 20 && left + right <= 100 && units(left) + units(right) >= 10,
        min: ({ left, right }) => left > 20 && units(right) > units(left),
        examples: { plus: [38, 7], min: [52, 18] },
        not: { plus: [34, 25], min: [68, 25] },
    },
};

const levels = Object.values(LEVELS);

describe('allSums', () => {
    it.each(levels)('level %i only has sums of that level', (level) => {
        for (const operator of ['plus', 'min']) {
            const sums = allSums(level, operator);

            expect(sums.length).toBeGreaterThanOrEqual(ROUND_SIZE);
            for (const sum of sums) {
                expect(sum.operator).toBe(operator);
                expect(sum.left).toBeGreaterThanOrEqual(1);
                expect(sum.right).toBeGreaterThanOrEqual(1);
                expect(answerOf(sum)).toBeGreaterThanOrEqual(0);
                expect(answerOf(sum)).toBeLessThanOrEqual(100);
                expect(EXPECTED[level][operator](sum), key(sum)).toBe(true);
            }
        }
    });

    it.each(levels)('level %i has its examples and not those of other levels', (level) => {
        const { examples, not } = EXPECTED[level];
        for (const operator of ['plus', 'min']) {
            const keys = new Set(allSums(level, operator).map(key));

            expect(keys).toContain(`${examples[operator][0]}${operator}${examples[operator][1]}`);
            expect(keys).not.toContain(`${not[operator][0]}${operator}${not[operator][1]}`);
        }
    });

    it('rejects an unknown level or operator', () => {
        expect(() => allSums(7, 'plus')).toThrow(RangeError);
        expect(() => allSums(LEVELS.TOT_10, 'keer')).toThrow(RangeError);
    });
});

describe('pickSums', () => {
    it.each(levels.flatMap((level) => OPERATIONS.map((operation) => [level, operation])))(
        'level %i, %s: ten different sums of the level',
        (level, operation) => {
            for (let seed = 1; seed <= 20; seed++) {
                const sums = pickSums(level, operation, seeded(seed));
                const allowed = new Set([
                    ...allSums(level, 'plus').map(key),
                    ...allSums(level, 'min').map(key),
                ]);

                expect(sums).toHaveLength(ROUND_SIZE);
                expect(new Set(sums.map(key)).size).toBe(ROUND_SIZE);
                for (const sum of sums) {
                    expect(allowed).toContain(key(sum));
                }

                const plus = sums.filter((sum) => sum.operator === 'plus').length;
                const expectedPlus = { erbij: 10, eraf: 0, 'door-elkaar': 5 }[operation];
                expect(plus).toBe(expectedPlus);
            }
        },
    );

    it('is deterministic for the injected random source', () => {
        expect(pickSums(LEVELS.TOT_20_BRUG, 'door-elkaar', seeded(42))).toEqual(
            pickSums(LEVELS.TOT_20_BRUG, 'door-elkaar', seeded(42)),
        );
    });

    it('rejects an unknown operation', () => {
        expect(() => pickSums(LEVELS.TOT_10, 'keer')).toThrow(RangeError);
    });
});
