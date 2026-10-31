import BubbleGameController from './bubble_game.js';
import { pickSums } from '../games/rekenen/erbij_eraf.js';

/*
 * Erbij en eraf bubbles: ten plussommen, minsommen or both for one level.
 * Everything else is the shared bubble game (./bubble_game.js); the sums come from
 * assets/games/rekenen/erbij_eraf.js.
 */
export default class extends BubbleGameController {
    static values = {
        // 1–6, see LEVELS.
        level: Number,
        // "erbij", "eraf" or "door-elkaar".
        operation: String,
    };

    createSums() {
        return pickSums(this.levelValue, this.operationValue, Math.random);
    }
}
