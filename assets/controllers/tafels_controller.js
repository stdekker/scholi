import { Controller } from '@hotwired/stimulus';
import {
    appendDigit,
    checkAnswer,
    createRound,
    findTask,
    format,
    isRoundComplete,
    remainingTasks,
    removeLastDigit,
    solveTask,
} from '../games/rekenen/tafels.js';

const BUBBLE_HUES = [200, 280, 330, 30, 150, 50];
// Fallback for when no animationend fires (e.g. prefers-reduced-motion).
const POP_FALLBACK_MS = 600;

/*
 * Tafels bubbles: one bubble per sum; click a bubble, type the answer, pop it.
 * DOM wiring only — the game rules live in assets/games/rekenen/tafels.js.
 *
 * Translated texts arrive as values with %placeholders%, filled in with format().
 */
export default class extends Controller {
    static targets = [
        'field',
        'bubbleTemplate',
        'keypad',
        'status',
        'progress',
        'complete',
        'restart',
    ];

    static values = {
        table: Number,
        sumLabel: String,
        correct: String,
        tryAgain: String,
        remaining: String,
    };

    connect() {
        this.timers = new Set();
        this.start();
    }

    disconnect() {
        this.timers.forEach((timer) => clearTimeout(timer));
        this.timers.clear();
    }

    start() {
        this.round = createRound(this.tableValue);
        this.activeBubble = null;

        this.fieldTarget.replaceChildren(
            ...this.round.tasks.map((task) => this.createBubble(task)),
        );
        this.keypadTarget.hidden = true;
        this.completeTarget.hidden = true;
        this.statusTarget.textContent = '';
        this.updateProgress();
    }

    restart() {
        this.start();
        this.fieldTarget.querySelector('.bubble__sum')?.focus();
    }

    /** @param {import('../games/rekenen/tafels.js').Task} task */
    createBubble(task) {
        const bubble = this.bubbleTemplateTarget.content.firstElementChild.cloneNode(true);
        const params = { multiplier: task.multiplier, table: task.table };

        bubble.dataset.taskId = String(task.id);
        bubble.style.setProperty('--bubble-hue', String(BUBBLE_HUES[task.id % BUBBLE_HUES.length]));
        bubble.style.setProperty('--bubble-delay', `${(-Math.random() * 3).toFixed(2)}s`);
        bubble.querySelector('.bubble__question').textContent =
            `${task.multiplier} × ${task.table} =`;
        bubble
            .querySelector('.bubble__sum')
            .setAttribute('aria-label', format(this.sumLabelValue, params));

        bubble.addEventListener('animationend', (event) => this.animationEnded(event, bubble));

        return bubble;
    }

    open(event) {
        const bubble = event.currentTarget.closest('.bubble');
        if (bubble === this.activeBubble || bubble.classList.contains('bubble--popping')) {
            this.answerInput()?.focus();
            return;
        }

        this.close();
        this.activeBubble = bubble;
        bubble.classList.add('bubble--active');
        bubble.querySelector('.bubble__sum').setAttribute('aria-expanded', 'true');

        const input = this.answerInput();
        input.value = '';
        input.hidden = false;
        this.keypadTarget.hidden = false;
        this.statusTarget.textContent = '';
        input.focus();
    }

    close() {
        if (!this.activeBubble) {
            return;
        }

        this.activeBubble.classList.remove('bubble--active', 'bubble--wrong');
        this.activeBubble.querySelector('.bubble__sum').setAttribute('aria-expanded', 'false');
        this.answerInput().hidden = true;
        this.activeBubble = null;
        this.keypadTarget.hidden = true;
    }

    press({ params: { digit } }) {
        const input = this.answerInput();
        if (!input) {
            return;
        }

        input.value = appendDigit(input.value, String(digit));
        input.focus();
    }

    erase() {
        const input = this.answerInput();
        if (!input) {
            return;
        }

        input.value = removeLastDigit(input.value);
        input.focus();
    }

    /** Keeps physical-keyboard input to digits only. */
    typed(event) {
        const input = event.currentTarget;
        input.value = [...input.value].reduce((value, char) => appendDigit(value, char), '');
    }

    keydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.submit();
        } else if (event.key === 'Escape') {
            const button = this.activeBubble?.querySelector('.bubble__sum');
            this.close();
            button?.focus();
        }
    }

    submit() {
        const bubble = this.activeBubble;
        if (!bubble) {
            return;
        }

        const input = this.answerInput();
        const task = findTask(this.round, Number(bubble.dataset.taskId));
        if (!task || input.value === '') {
            input.focus();
            return;
        }

        if (!checkAnswer(task, input.value)) {
            this.statusTarget.textContent = this.tryAgainValue;
            bubble.classList.remove('bubble--wrong');
            // Force a reflow so the wiggle animation restarts on repeated wrong answers.
            void bubble.offsetWidth;
            bubble.classList.add('bubble--wrong');
            input.value = '';
            input.focus();
            return;
        }

        this.round = solveTask(this.round, task.id);
        this.statusTarget.textContent = format(this.correctValue, {
            multiplier: task.multiplier,
            table: task.table,
            answer: task.answer,
        });

        this.close();
        bubble.classList.add('bubble--popping');
        bubble.querySelector('.bubble__sum').disabled = true;
        this.updateProgress();
        this.focusNext(bubble);

        const timer = setTimeout(() => this.removeBubble(bubble), POP_FALLBACK_MS);
        this.timers.add(timer);
        bubble.dataset.popTimer = String(timer);
    }

    animationEnded(event, bubble) {
        if (event.target !== bubble) {
            return;
        }

        if (event.animationName === 'bubble-pop') {
            this.removeBubble(bubble);
        } else if (event.animationName === 'bubble-wiggle') {
            bubble.classList.remove('bubble--wrong');
        }
    }

    removeBubble(bubble) {
        const timer = Number(bubble.dataset.popTimer);
        clearTimeout(timer);
        this.timers.delete(timer);
        bubble.remove();
    }

    focusNext(poppedBubble) {
        if (isRoundComplete(this.round)) {
            this.completeTarget.hidden = false;
            this.restartTarget.focus();
            return;
        }

        const next = [...this.fieldTarget.children].find(
            (bubble) => bubble !== poppedBubble && !bubble.classList.contains('bubble--popping'),
        );
        next?.querySelector('.bubble__sum').focus();
    }

    updateProgress() {
        const count = remainingTasks(this.round).length;
        this.progressTarget.textContent = count > 0 ? format(this.remainingValue, { count }) : '';
    }

    answerInput() {
        return this.activeBubble?.querySelector('.bubble__answer') ?? null;
    }
}
