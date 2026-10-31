import { describe, expect, it } from 'vitest';
import { ALL_TABLES_COUNT, tafelsSums } from '../../../../assets/games/rekenen/tafels.js';
import {
    createRound,
    isPassed,
    isRoundComplete,
    solveTask,
    startTimer,
} from '../../../../assets/games/shared/round.js';

describe('tafelsSums', () => {
    it('gives the ten sums of the table', () => {
        const sums = tafelsSums([6]);

        expect(sums).toHaveLength(10);

        const multipliers = sums.map((sum) => sum.left).toSorted((a, b) => a - b);
        expect(multipliers).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        for (const sum of sums) {
            expect(sum).toMatchObject({ operator: 'keer', right: 6 });
        }
    });

    it('shuffles with the injected random source', () => {
        const order = (random) => tafelsSums([2], random).map((sum) => sum.left);

        expect(order(() => 0.9999)).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        expect(order(() => 0)).toEqual([2, 3, 4, 5, 6, 7, 8, 9, 10, 1]);
    });

    it.each([-1, 0, 13, 2.5, Number.NaN])('rejects table %s', (table) => {
        expect(() => tafelsSums([table])).toThrow(RangeError);
        expect(() => tafelsSums([2, table])).toThrow(RangeError);
    });

    it('rejects no tables and duplicate tables', () => {
        expect(() => tafelsSums([])).toThrow(RangeError);
        expect(() => tafelsSums([3, 3])).toThrow(RangeError);
    });

    it.each([1, 12])('accepts table %s', (table) => {
        expect(tafelsSums([table])).toHaveLength(10);
    });
});

describe('tafelsSums for alle tafels', () => {
    const twoToTen = [2, 3, 4, 5, 6, 7, 8, 9, 10];

    it('picks twenty different sums from the given tables', () => {
        const sums = tafelsSums(twoToTen);

        expect(ALL_TABLES_COUNT).toBe(20);
        expect(sums).toHaveLength(20);
        expect(new Set(sums.map((sum) => `${sum.left}x${sum.right}`)).size).toBe(20);

        for (const sum of sums) {
            expect(sum.operator).toBe('keer');
            expect(sum.right).toBeGreaterThanOrEqual(2);
            expect(sum.right).toBeLessThanOrEqual(10);
            expect(sum.left).toBeGreaterThanOrEqual(1);
            expect(sum.left).toBeLessThanOrEqual(10);
        }
    });

    it('picks only from the given tables', () => {
        const sums = tafelsSums([3, 7]);

        expect(sums).toHaveLength(20);
        expect(new Set(sums.map((sum) => sum.right))).toEqual(new Set([3, 7]));
    });

    it('is deterministic for the injected random source', () => {
        const sums = tafelsSums(twoToTen, () => 0.9999).map((sum) => `${sum.left}x${sum.right}`);

        // random() close to 1 keeps the order: the first twenty sums (tafel van 2 and 3).
        expect(sums).toEqual([
            ...Array.from({ length: 10 }, (_, i) => `${i + 1}x2`),
            ...Array.from({ length: 10 }, (_, i) => `${i + 1}x3`),
        ]);
    });

    it('makes a round that can be completed and judged like a single table', () => {
        let round = startTimer(createRound(tafelsSums(twoToTen), 120_000), 0);
        for (const task of round.tasks) {
            expect(task.answer).toBe(task.left * task.right);
            round = solveTask(round, task.id, 119_000);
        }

        expect(isRoundComplete(round)).toBe(true);
        expect(isPassed(round)).toBe(true);
    });
});
