import { describe, expect, it } from 'vitest';
import { appendDigit, removeLastDigit } from '../../../../assets/games/shared/input.js';

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
