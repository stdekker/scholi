import BubbleGameController from './bubble_game.js';
import { tafelsSums } from '../games/rekenen/tafels.js';

/*
 * Tafels bubbles: the sums of one table, or 20 from several ("alle tafels").
 * Everything else is the shared bubble game (./bubble_game.js); the sums come from
 * assets/games/rekenen/tafels.js.
 */
export default class extends BubbleGameController {
    static values = {
        // One table, or several for "alle tafels".
        tables: Array,
    };

    createSums() {
        return tafelsSums(this.tablesValue, Math.random);
    }
}
