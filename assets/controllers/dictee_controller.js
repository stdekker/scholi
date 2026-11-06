import BubbleGameController from './bubble_game.js';
import { format } from '../games/shared/format.js';
import { appendLetter } from '../games/shared/input.js';
import { createTaskRound, findTask } from '../games/shared/round.js';
import { checkWord, createWordItems, describeHint } from '../games/taal/dictee.js';

/*
 * Dictee: ten bubbles with a spoken word each. Open a bubble, listen, write the word. What the
 * bubble shows of the word depends on the level (mode): all letters, some, only blanks or nothing.
 * Everything else is the shared bubble game (./bubble_game.js); the words and hints come from
 * assets/games/taal/dictee.js.
 *
 * The words are recordings made beforehand (bin/generate-dictee-audio.mjs) and served from this
 * site: the browser never contacts a speech service.
 */
export default class extends BubbleGameController {
    static values = {
        // "full", "partial", "blanks" or "hidden", see MODES.
        mode: String,
        // The words of the groep, each with the URL of its recording: { kat: "/assets/…" }.
        audio: Object,
        answerMaxLength: Number,
        // Screen-reader label of a bubble: "Woord %number%" and "Woord %number%: %hint%".
        wordLabel: String,
        wordLabelHint: String,
        // What is said for a blank letter in that hint: "leeg".
        blankLabel: String,
        // Shown when a recording can't be played.
        audioFailed: String,
    };

    connect() {
        // One element for all words: once a browser lets it play (see unlockAudio) it keeps doing so.
        this.speaker = new Audio();
        this.speaker.addEventListener('error', () => this.audioFailed());
        super.connect();
    }

    disconnect() {
        this.speaker.pause();
        super.disconnect();
    }

    newRound() {
        return createTaskRound(
            createWordItems(Object.keys(this.audioValue), this.modeValue, Math.random),
            this.timeLimitValue,
        );
    }

    /**
     * Some browsers (iPads) only play sound that starts from a click. "Ik ben er klaar voor!" is
     * that click: play a recording silently now, so the words that follow may play on their own.
     */
    ready() {
        this.unlockAudio();
        super.ready();
    }

    unlockAudio() {
        const [url] = Object.values(this.audioValue);
        if (!url) {
            return;
        }

        this.speaker.muted = true;
        this.speaker.src = url;
        // Only undo the silent play if it is still the one playing: on a slow connection the first
        // word may have started (say() unmutes) before it settles.
        const restore = () => {
            if (this.speaker.muted) {
                this.speaker.pause();
                this.speaker.muted = false;
            }
        };
        this.speaker.play().then(restore, restore);
    }

    taskActivated(task) {
        this.say(task.answer);
    }

    /** "Luister nog eens": plays the word of the open bubble again. */
    replay() {
        const task = findTask(this.round, Number(this.activeBubble?.dataset.taskId));
        if (task) {
            this.say(task.answer);
        }
        this.answerInput()?.focus();
    }

    /** @param {string} word */
    say(word) {
        const url = this.audioValue[word];
        if (!url) {
            return;
        }

        this.speaker.muted = false;
        if (this.speaker.getAttribute('src') !== url) {
            this.speaker.src = url;
        }
        this.speaker.currentTime = 0;
        this.speaker.play().catch(() => this.audioFailed());
    }

    audioFailed() {
        this.statusTarget.textContent = this.audioFailedValue;
    }

    fillQuestion(element, task) {
        element.replaceChildren(
            ...task.hint.map((letter) => {
                const cell = document.createElement('span');
                cell.className = 'bubble__letter';
                if (letter === null) {
                    cell.classList.add('bubble__letter--blank');
                } else {
                    cell.textContent = letter;
                }

                return cell;
            }),
        );
    }

    /** "Woord 3: k, leeg, t" for a screen reader; the letters are also read by the hint. */
    sumLabel(task) {
        const hint = describeHint(task.hint, this.blankLabelValue);

        return format(hint ? this.wordLabelHintValue : this.wordLabelValue, {
            number: task.id,
            hint,
        });
    }

    isCorrect(task, input) {
        return checkWord(task, input);
    }

    correctParams(task) {
        return { answer: task.answer };
    }

    acceptsKey(key) {
        return appendLetter('', key, 1) !== '';
    }

    appendKey(value, char) {
        return appendLetter(value, char, this.answerMaxLengthValue);
    }
}
