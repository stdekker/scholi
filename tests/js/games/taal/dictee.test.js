import { describe, expect, it } from 'vitest';
import {
    checkWord,
    createWordItems,
    describeHint,
    hintFor,
    MODES,
    normalizeWord,
    pickWords,
    ROUND_SIZE,
} from '../../../../assets/games/taal/dictee.js';
import { createTaskRound } from '../../../../assets/games/shared/round.js';

/** A simple seeded source of randomness, so failures can be reproduced. */
const seeded = (seed) => () => {
    seed = (seed * 16807) % 2147483647;
    return (seed - 1) / 2147483646;
};

const WORDS = [
    'vriendelijk',
    'gezellig',
    'verjaardag',
    'belangrijk',
    'ontbijt',
    'pannenkoek',
    'vrolijk',
    'gevaarlijk',
    'opeens',
    'meestal',
    'kleurpotlood',
    'verkeerd',
];

describe('checkWord', () => {
    const task = { answer: 'ontbijt' };

    it('accepts the word, also with capitals or spaces around it', () => {
        expect(checkWord(task, 'ontbijt')).toBe(true);
        expect(checkWord(task, 'Ontbijt')).toBe(true);
        expect(checkWord(task, '  ontbijt ')).toBe(true);
    });

    it('rejects a different spelling', () => {
        expect(checkWord(task, 'ontbijd')).toBe(false);
        expect(checkWord(task, 'ontbij')).toBe(false);
        expect(checkWord(task, 'ontbijtt')).toBe(false);
        expect(checkWord(task, 'ont bijt')).toBe(false);
        expect(checkWord(task, '')).toBe(false);
    });

    it('normalizes what was typed', () => {
        expect(normalizeWord('  KAT ')).toBe('kat');
    });
});

describe('hintFor', () => {
    it('shows every letter in mode full', () => {
        expect(hintFor('boot', MODES.FULL)).toEqual(['b', 'o', 'o', 't']);
    });

    it('shows only a blank per letter in mode blanks', () => {
        expect(hintFor('boot', MODES.BLANKS)).toEqual([null, null, null, null]);
    });

    it('shows nothing in mode hidden', () => {
        expect(hintFor('boot', MODES.HIDDEN)).toEqual([]);
    });

    it('rejects an unknown mode', () => {
        expect(() => hintFor('boot', 'raden')).toThrow(RangeError);
    });

    describe('mode partial', () => {
        it('hides about half of the letters, but never all or none of them', () => {
            const random = seeded(42);

            for (const word of WORDS.concat(['aap', 'ei', 'verantwoordelijk', 'a'])) {
                const hint = hintFor(word, MODES.PARTIAL, random);
                const blanks = hint.filter((letter) => letter === null).length;
                const expected = Math.min(word.length - 1, Math.max(1, Math.ceil(word.length / 2)));

                expect(hint).toHaveLength(word.length);
                expect(blanks, word).toBe(expected);
                expect(blanks, word).toBeLessThan(word.length);
            }
        });

        it('keeps the letters that are shown in their place', () => {
            const word = 'verjaardag';
            const hint = hintFor(word, MODES.PARTIAL, seeded(7));

            hint.forEach((letter, index) => {
                if (letter !== null) {
                    expect(letter).toBe(word[index]);
                }
            });
        });

        it('chooses the blanks with the random source', () => {
            const first = hintFor('verjaardag', MODES.PARTIAL, seeded(1));
            const again = hintFor('verjaardag', MODES.PARTIAL, seeded(1));
            const positions = new Set();
            for (let seed = 1; seed <= 30; seed++) {
                positions.add(JSON.stringify(hintFor('verjaardag', MODES.PARTIAL, seeded(seed))));
            }

            expect(again).toEqual(first);
            expect(positions.size).toBeGreaterThan(1);
        });
    });
});

describe('describeHint', () => {
    it('says a letter or the blank label per position', () => {
        expect(describeHint(['k', null, 't'], 'leeg')).toBe('k, leeg, t');
        expect(describeHint([null, null], 'leeg')).toBe('leeg, leeg');
    });

    it('is empty when nothing is shown', () => {
        expect(describeHint([], 'leeg')).toBe('');
    });
});

describe('pickWords', () => {
    it('picks ten different words of the list', () => {
        const picked = pickWords(WORDS, seeded(3));

        expect(picked).toHaveLength(ROUND_SIZE);
        expect(new Set(picked).size).toBe(ROUND_SIZE);
        picked.forEach((word) => expect(WORDS).toContain(word));
    });

    it('gives other words and another order each time', () => {
        const rounds = new Set();
        for (let seed = 1; seed <= 20; seed++) {
            rounds.add(pickWords(WORDS, seeded(seed)).join());
        }

        expect(rounds.size).toBeGreaterThan(1);
    });

    it('gives all words of a short list', () => {
        expect(pickWords(['aap', 'noot'], seeded(1)).sort()).toEqual(['aap', 'noot']);
    });

    it('does not change the list', () => {
        const words = [...WORDS];
        pickWords(words, seeded(1));

        expect(words).toEqual(WORDS);
    });
});

describe('createWordItems', () => {
    it('pairs every picked word with what is shown of it', () => {
        const items = createWordItems(WORDS, MODES.BLANKS, seeded(5));

        expect(items).toHaveLength(ROUND_SIZE);
        for (const { answer, hint } of items) {
            expect(WORDS).toContain(answer);
            expect(hint).toEqual([...answer].map(() => null));
        }
    });

    it('makes tasks for a round', () => {
        const round = createTaskRound(createWordItems(WORDS, MODES.FULL, seeded(5)), 180_000);

        expect(round.tasks).toHaveLength(ROUND_SIZE);
        expect(round.tasks.every((task) => checkWord(task, task.answer.toUpperCase()))).toBe(true);
    });
});
