import { describe, expect, it } from 'vitest';
import {
    appendDigit,
    checkAnswer,
    createRound,
    findTask,
    format,
    formatTime,
    isPassed,
    isRoundComplete,
    isTimeUp,
    remainingTasks,
    removeLastDigit,
    solveTask,
    startTimer,
    TIME_LIMIT_MS,
    timeLeft,
} from '../../../../assets/games/rekenen/tafels.js';

describe('createRound', () => {
    it('creates the ten sums of the table', () => {
        const round = createRound(6);

        expect(round.table).toBe(6);
        expect(round.tasks).toHaveLength(10);

        const multipliers = round.tasks.map((task) => task.multiplier).toSorted((a, b) => a - b);
        expect(multipliers).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        for (const task of round.tasks) {
            expect(task.table).toBe(6);
            expect(task.answer).toBe(task.multiplier * 6);
            expect(task.solved).toBe(false);
        }
    });

    it('shuffles with the injected random source', () => {
        const order = (random) => createRound(2, random).tasks.map((task) => task.multiplier);

        expect(order(() => 0.9999)).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        expect(order(() => 0)).toEqual([2, 3, 4, 5, 6, 7, 8, 9, 10, 1]);
    });

    it.each([1, 11, 2.5, Number.NaN])('rejects table %s', (table) => {
        expect(() => createRound(table)).toThrow(RangeError);
    });

    it.each([2, 10])('accepts table %s', (table) => {
        expect(createRound(table).tasks).toHaveLength(10);
    });
});

describe('checkAnswer', () => {
    const task = { id: 2, multiplier: 2, table: 6, answer: 12, solved: false };

    it('accepts the right answer', () => {
        expect(checkAnswer(task, '12')).toBe(true);
        expect(checkAnswer(task, '012')).toBe(true);
    });

    it.each(['', '13', '1a', '-12', ' 12', '12.0'])('rejects %j', (input) => {
        expect(checkAnswer(task, input)).toBe(false);
    });
});

describe('round progress', () => {
    it('tracks solved tasks without modifying the original round', () => {
        const round = createRound(3);
        const solved = solveTask(round, 4, 1000);

        expect(findTask(solved, 4)?.solved).toBe(true);
        expect(findTask(round, 4)?.solved).toBe(false);
        expect(remainingTasks(solved)).toHaveLength(9);
        expect(isRoundComplete(solved)).toBe(false);
    });

    it('is complete when every task is solved', () => {
        let round = createRound(5);
        for (const task of round.tasks) {
            round = solveTask(round, task.id, 1000);
        }

        expect(remainingTasks(round)).toHaveLength(0);
        expect(isRoundComplete(round)).toBe(true);
    });
});

describe('timer', () => {
    const solveAll = (round, now) =>
        round.tasks.reduce((current, task) => solveTask(current, task.id, now), round);

    it('has the full minute before it starts', () => {
        const round = createRound(4);

        expect(TIME_LIMIT_MS).toBe(60_000);
        expect(round.startedAt).toBeNull();
        expect(timeLeft(round, 999_999)).toBe(TIME_LIMIT_MS);
        expect(isTimeUp(round, 999_999)).toBe(false);
    });

    it('counts down from the first start only', () => {
        const round = startTimer(startTimer(createRound(4), 1_000), 5_000);

        expect(round.startedAt).toBe(1_000);
        expect(timeLeft(round, 21_000)).toBe(40_000);
        expect(isTimeUp(round, 60_999)).toBe(false);
        expect(isTimeUp(round, 61_000)).toBe(true);
        expect(timeLeft(round, 90_000)).toBe(0);
    });

    it('passes when everything is solved within the minute', () => {
        const round = solveAll(startTimer(createRound(7), 1_000), 61_000);

        expect(round.finishedAt).toBe(61_000);
        expect(isPassed(round)).toBe(true);
        expect(timeLeft(round, 120_000)).toBe(0);
    });

    it('does not pass when finished after the minute, but the round is complete', () => {
        const round = solveAll(startTimer(createRound(7), 1_000), 61_001);

        expect(isRoundComplete(round)).toBe(true);
        expect(isPassed(round)).toBe(false);
    });

    it('stops the clock when the round is finished', () => {
        const round = solveAll(startTimer(createRound(7), 0), 45_000);

        expect(timeLeft(round, 200_000)).toBe(15_000);
    });

    it('only records finishedAt for the last task', () => {
        const round = solveTask(startTimer(createRound(7), 0), 1, 5_000);

        expect(round.finishedAt).toBeNull();
        expect(isPassed(round)).toBe(false);
    });

    it.each([
        [60_000, '1:00'],
        [59_001, '1:00'],
        [59_000, '0:59'],
        [9_500, '0:10'],
        [1, '0:01'],
        [0, '0:00'],
        [-500, '0:00'],
    ])('formats %i ms as %s', (ms, expected) => {
        expect(formatTime(ms)).toBe(expected);
    });
});

describe('keypad input', () => {
    it('appends digits up to the maximum length', () => {
        expect(appendDigit('', '1')).toBe('1');
        expect(appendDigit('10', '0')).toBe('100');
        expect(appendDigit('100', '5')).toBe('100');
        expect(appendDigit('1', '2', 1)).toBe('1');
    });

    it('ignores anything that is not a single digit', () => {
        expect(appendDigit('1', 'a')).toBe('1');
        expect(appendDigit('1', '-')).toBe('1');
        expect(appendDigit('1', '12')).toBe('1');
    });

    it('removes the last digit', () => {
        expect(removeLastDigit('12')).toBe('1');
        expect(removeLastDigit('')).toBe('');
    });
});

describe('format', () => {
    it('fills placeholders', () => {
        expect(format('Nog %count% bubbels', { count: 7 })).toBe('Nog 7 bubbels');
        expect(
            format('%multiplier% × %table% = %answer%', { multiplier: 2, table: 6, answer: 12 }),
        ).toBe('2 × 6 = 12');
    });

    it('leaves unknown placeholders untouched', () => {
        expect(format('Hallo %name%', {})).toBe('Hallo %name%');
    });
});
