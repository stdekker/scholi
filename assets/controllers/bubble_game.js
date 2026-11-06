import { Controller } from '@hotwired/stimulus';
import { format, formatDuration, formatSum, formatTime } from '../games/shared/format.js';
import { appendDigit, removeLastDigit } from '../games/shared/input.js';
import {
    checkAnswer,
    createRound,
    findTask,
    frozenTasks,
    isPassed,
    isRoundComplete,
    isTimeUp,
    pauseRound,
    practiceSeconds,
    READY_COUNTDOWN_SECONDS,
    remainingTasks,
    resumeRound,
    registerWrongAnswer,
    roundDuration,
    solveTask,
    startTimer,
    timeLeft,
} from '../games/shared/round.js';

const BUBBLE_HUES = [200, 280, 330, 30, 150, 50];
// Fallback for when no animationend fires (e.g. prefers-reduced-motion).
const POP_FALLBACK_MS = 600;
const CLOCK_TICK_MS = 200;
// How long "Start!" stays visible before the ready dialog closes.
const GO_VISIBLE_MS = 800;
// Delay between bubbles popping up one after another.
const APPEAR_STAGGER_MS = 40;
// Opens a bubble by itself when none has been opened for this long.
const AUTO_OPEN_DELAY_MS = 3000;
// Confetti of a popped bubble: number of pieces, and when they are removed (after the animation).
const CONFETTI_PIECES = 32;
const CONFETTI_MS = 700;

/*
 * Base class of the bubble games (tafels, erbij en eraf, dictee): one bubble per task (a sum, or
 * a word to write); click a bubble, type the answer, pop it. Each round opens with a "Ben je er klaar voor?" dialog and a 3-2-1
 * countdown; then the clock starts. When it runs out the pupil can still finish.
 * The next sum opens by itself (after "Start!", a pop or a freeze); the pupil may tap another one
 * instead. If no bubble is open for AUTO_OPEN_DELAY_MS (e.g. after Escape), one opens as well.
 * A click on a menu link (the site header) during a round pauses it and asks: stop or continue?
 *
 * Not a controller itself (the file name has no _controller suffix): a game's controller
 * extends it and implements createSums() (or newRound() for tasks that are not sums) and, when
 * its answers are not numbers, the input hooks (see "Hooks" below). Stimulus merges the
 * subclass's static values.
 * DOM wiring only — the round rules live in assets/games/shared/round.js.
 * The markup is templates/game/bubble_game.html.twig; the mechanics are in docs/games/bubble-games.md.
 *
 * Translated texts arrive as values with %placeholders%, filled in with format().
 */
export default class BubbleGameController extends Controller {
    static targets = [
        'field',
        'bubbleTemplate',
        'keypad',
        'status',
        'progress',
        'complete',
        'restart',
        'next',
        'timer',
        'time',
        'passedMessage',
        'lateMessage',
        'frozenMessage',
        'durationMessage',
        'header',
        'readyDialog',
        'readyTime',
        'readyButton',
        'readyBack',
        'countdown',
        'saveFailedMessage',
        'pauseDialog',
        'continueButton',
    ];

    static values = {
        timeLimit: Number,
        // Empty when the round isn't saved (e.g. "geoefende tafels").
        saveUrl: String,
        // Every finished round's seconds go here (practice time), saved or not.
        practiceUrl: String,
        // Screen-reader label of a sum per operator: { plus: "%left% plus %right%", … }.
        sumLabels: Object,
        // "Goed zo! %sum% = %answer%"
        correct: String,
        tryAgain: String,
        // Shown when a bubble freezes after too many wrong answers.
        frozen: String,
        // Screen-reader label of a frozen bubble: "%label%, bevroren".
        frozenLabel: String,
        // Screen-reader label of a frozen bubble once the answer is shown: "%label% is %answer%".
        revealedLabel: String,
        // Completion text when bubbles froze: "%solved% van de %total% …".
        doneFrozen: String,
        // "Je deed het dit keer in %time%." and the texts formatDuration() builds %time% from.
        duration: String,
        durationTexts: Object,
        remaining: String,
        timeUp: String,
        go: String,
    };

    connect() {
        this.timers = new Set();
        this.leaving = false;
        this.start();
    }

    disconnect() {
        this.stopClock();
        this.timers.forEach((timer) => clearTimeout(timer));
        this.timers.clear();
    }

    /**
     * The sums of a new round, in the order the bubbles appear. Implemented by each game that asks
     * sums.
     *
     * @returns {import('../games/shared/round.js').Sum[]}
     */
    createSums() {
        throw new Error('A bubble game must implement createSums()');
    }

    // Hooks: what a game with other tasks than sums changes. The defaults are for sums.

    /**
     * A new round. Games whose tasks are not sums override this (createTaskRound()).
     *
     * @returns {import('../games/shared/round.js').Round}
     */
    newRound() {
        return createRound(this.createSums(), this.timeLimitValue);
    }

    /**
     * Writes what a bubble shows of its task, e.g. "7 + 5 =".
     *
     * @param {HTMLElement} element
     * @param {import('../games/shared/round.js').Task} task
     */
    fillQuestion(element, task) {
        element.textContent = `${formatSum(task)} =`;
    }

    /**
     * @param {import('../games/shared/round.js').Task} task
     * @param {string} input
     * @returns {boolean}
     */
    isCorrect(task, input) {
        return checkAnswer(task, input);
    }

    /**
     * The placeholders of the "correct" text.
     *
     * @param {import('../games/shared/round.js').Task} task
     * @returns {Record<string, string | number>}
     */
    correctParams(task) {
        return { sum: formatSum(task), answer: task.answer };
    }

    /**
     * Whether typing this key on the keyboard answers a task.
     *
     * @param {string} key
     * @returns {boolean}
     */
    acceptsKey(key) {
        return /^\d$/.test(key);
    }

    /**
     * Adds one typed character to the answer (or ignores it).
     *
     * @param {string} value
     * @param {string} char
     * @returns {string}
     */
    appendKey(value, char) {
        return appendDigit(value, char);
    }

    /**
     * A task has been opened (not when it already was open): games with sound play it here.
     *
     * @param {import('../games/shared/round.js').Task} task
     */
    // eslint-disable-next-line no-unused-vars
    taskActivated(task) {}

    start() {
        this.stopClock();
        this.cancelAutoOpen();
        this.round = this.newRound();
        this.activeBubble = null;
        this.timerTarget.classList.remove('round-timer--expired');
        this.renderClock();
        this.showReady();

        // Bubbles only appear once the ready countdown is over (see revealBubbles).
        this.fieldTarget.replaceChildren();
        this.keypadTarget.hidden = true;
        this.completeTarget.hidden = true;
        this.headerTarget.hidden = false;
        this.saveFailedMessageTargets.forEach((message) => (message.hidden = true));
        this.statusTarget.textContent = '';
        this.updateProgress();
    }

    restart() {
        this.start();
    }

    showReady() {
        this.readyDone = false;
        this.readyTimeTarget.textContent = formatTime(this.round.timeLimitMs);
        this.readyButtonTarget.hidden = false;
        this.readyBackTarget.hidden = false;
        this.countdownTarget.textContent = '';
        if (!this.readyDialogTarget.open) {
            this.readyDialogTarget.showModal();
        }
        this.readyButtonTarget.focus();
    }

    /** "Ik ben er klaar voor!" → 3, 2, 1, Start! → close the dialog and start the clock. */
    ready() {
        this.readyButtonTarget.hidden = true;
        this.readyBackTarget.hidden = true;

        for (let step = 0; step < READY_COUNTDOWN_SECONDS; step++) {
            this.schedule(step * 1000, () =>
                this.showCountdown(String(READY_COUNTDOWN_SECONDS - step)),
            );
        }
        this.schedule(READY_COUNTDOWN_SECONDS * 1000, () => this.showCountdown(this.goValue));
        this.schedule(READY_COUNTDOWN_SECONDS * 1000 + GO_VISIBLE_MS, () => {
            this.readyDone = true;
            this.readyDialogTarget.close();
            this.revealBubbles();
            this.startClock();
            this.openNext(null);
        });
    }

    showCountdown(text) {
        const countdown = this.countdownTarget;
        countdown.textContent = text;
        countdown.classList.remove('ready-dialog__countdown--tick');
        // Force a reflow so the pop-in animation restarts for every step.
        void countdown.offsetWidth;
        countdown.classList.add('ready-dialog__countdown--tick');
    }

    /** The countdown is the only way out of the ready dialog (no Escape). */
    preventCancel(event) {
        event.preventDefault();
    }

    /**
     * Browsers may still close the dialog (e.g. Escape before any user interaction, where
     * `cancel` is not cancelable and `closedby` is unsupported): reopen it.
     */
    readyClosed() {
        if (this.readyDone) {
            return;
        }

        this.readyDialogTarget.showModal();
        if (!this.readyButtonTarget.hidden) {
            this.readyButtonTarget.focus();
        }
    }

    schedule(delay, callback) {
        const timer = setTimeout(() => {
            this.timers.delete(timer);
            callback();
        }, delay);
        this.timers.add(timer);
    }

    /** (Re)starts the idle wait after which a bubble opens by itself. */
    scheduleAutoOpen() {
        this.cancelAutoOpen();
        if (!this.readyDone || this.activeBubble || isRoundComplete(this.round)) {
            return;
        }

        this.autoOpenTimer = setTimeout(() => {
            this.timers.delete(this.autoOpenTimer);
            this.autoOpenTimer = null;
            const bubble = this.activeBubble ? null : this.bubbleToActivate();
            if (bubble) {
                this.activate(bubble);
            }
        }, AUTO_OPEN_DELAY_MS);
        this.timers.add(this.autoOpenTimer);
    }

    cancelAutoOpen() {
        clearTimeout(this.autoOpenTimer);
        this.timers.delete(this.autoOpenTimer);
        this.autoOpenTimer = null;
    }

    revealBubbles() {
        this.fieldTarget.replaceChildren(
            ...this.round.tasks.map((task, index) => this.createBubble(task, index)),
        );
    }

    /**
     * Screen-reader text of a sum, e.g. "7 plus 5".
     *
     * @param {import('../games/shared/round.js').Task} task
     */
    sumLabel(task) {
        return format(this.sumLabelsValue[task.operator] ?? formatSum(task), task);
    }

    /**
     * @param {import('../games/shared/round.js').Task} task
     * @param {number} index position in the field, for the staggered pop-up
     */
    createBubble(task, index) {
        const bubble = this.bubbleTemplateTarget.content.firstElementChild.cloneNode(true);

        bubble.dataset.taskId = String(task.id);
        bubble.classList.add('bubble--appearing');
        bubble.style.setProperty('--appear-delay', `${index * APPEAR_STAGGER_MS}ms`);
        bubble.style.setProperty('--bubble-hue', String(BUBBLE_HUES[task.id % BUBBLE_HUES.length]));
        bubble.style.setProperty('--bubble-delay', `${(-Math.random() * 3).toFixed(2)}s`);
        this.fillQuestion(bubble.querySelector('.bubble__question'), task);
        bubble.querySelector('.bubble__sum').setAttribute('aria-label', this.sumLabel(task));

        bubble.addEventListener('animationend', (event) => this.animationEnded(event, bubble));

        return bubble;
    }

    open(event) {
        this.activate(event.currentTarget.closest('.bubble'));
    }

    /**
     * @param {HTMLElement} bubble
     * @param {{keepStatus?: boolean}} options keepStatus: leave the feedback ("Goed zo!") in place
     */
    activate(bubble, { keepStatus = false } = {}) {
        if (
            bubble === this.activeBubble ||
            bubble.classList.contains('bubble--popping') ||
            bubble.classList.contains('bubble--frozen')
        ) {
            this.answerInput()?.focus();
            return;
        }

        this.close();
        this.cancelAutoOpen();
        this.activeBubble = bubble;
        bubble.classList.add('bubble--active');
        bubble.querySelector('.bubble__sum').setAttribute('aria-expanded', 'true');

        const input = this.answerInput();
        input.value = '';
        input.hidden = false;
        this.keypadTarget.hidden = false;
        if (!keepStatus) {
            this.statusTarget.textContent = '';
        }
        input.focus({ preventScroll: true });
        // scroll-margin on .bubble--active keeps the bubble clear of the sticky keypad dock.
        bubble.scrollIntoView({ block: 'nearest' });

        const task = findTask(this.round, Number(bubble.dataset.taskId));
        if (task) {
            this.taskActivated(task);
        }
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
        this.scheduleAutoOpen();
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

    /**
     * Typing a digit (or a letter, in dictee) anywhere on the page answers a bubble: the open
     * one, else the focused bubble, else the first remaining bubble.
     */
    typeAnywhere(event) {
        const key = event.key;
        if (
            !this.acceptsKey(key) ||
            event.ctrlKey ||
            event.metaKey ||
            event.altKey ||
            this.readyDialogTarget.open ||
            this.pauseDialogTarget.open ||
            event.target.classList?.contains('bubble__answer')
        ) {
            return;
        }

        const bubble = this.activeBubble ?? this.bubbleToActivate();
        if (!bubble) {
            return;
        }

        // Handle the key ourselves: focus moves to the answer field during this keydown.
        event.preventDefault();
        this.activate(bubble);
        const input = this.answerInput();
        input.value = this.appendKey(input.value, key);
        input.focus();
    }

    bubbleToActivate() {
        const available = (bubble) =>
            bubble &&
            !bubble.classList.contains('bubble--popping') &&
            !bubble.classList.contains('bubble--frozen');
        const focused = document.activeElement?.closest('.bubble');

        if (available(focused) && this.fieldTarget.contains(focused)) {
            return focused;
        }

        return [...this.fieldTarget.children].find(available) ?? null;
    }

    /** Keeps physical-keyboard input to what the game accepts (digits, or letters in dictee). */
    typed(event) {
        const input = event.currentTarget;
        input.value = [...input.value].reduce((value, char) => this.appendKey(value, char), '');
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

        if (!this.isCorrect(task, input.value)) {
            this.round = registerWrongAnswer(this.round, task.id, Date.now());
            if (findTask(this.round, task.id)?.frozen) {
                this.freeze(bubble, task);
                return;
            }

            this.statusTarget.textContent = this.tryAgainValue;
            bubble.classList.remove('bubble--wrong');
            // Force a reflow so the wiggle animation restarts on repeated wrong answers.
            void bubble.offsetWidth;
            bubble.classList.add('bubble--wrong');
            input.value = '';
            input.focus();
            return;
        }

        this.round = solveTask(this.round, task.id, Date.now());
        this.statusTarget.textContent = format(this.correctValue, this.correctParams(task));

        this.close();
        bubble.classList.add('bubble--popping');
        bubble.querySelector('.bubble__sum').disabled = true;
        this.burst(bubble);
        this.updateProgress();
        this.openNext(bubble);

        const timer = setTimeout(() => this.removeBubble(bubble), POP_FALLBACK_MS);
        this.timers.add(timer);
        bubble.dataset.popTimer = String(timer);
    }

    /**
     * A correctly answered bubble explodes into confetti in its own colour. Decoration only
     * (aria-hidden), and skipped under prefers-reduced-motion.
     *
     * @param {HTMLElement} bubble
     */
    burst(bubble) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const { left, top, width, height } = bubble.getBoundingClientRect();
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.setAttribute('aria-hidden', 'true');
        confetti.style.left = `${left + width / 2}px`;
        confetti.style.top = `${top + height / 2}px`;
        confetti.style.setProperty('--bubble-hue', bubble.style.getPropertyValue('--bubble-hue'));

        for (let i = 0; i < CONFETTI_PIECES; i++) {
            const piece = document.createElement('span');
            const angle = ((i + Math.random() * 0.5) / CONFETTI_PIECES) * 2 * Math.PI;
            const distance = width * (0.8 + Math.random() * 0.9);
            piece.className = 'confetti__piece';
            piece.style.setProperty('--x', `${(Math.cos(angle) * distance).toFixed(1)}px`);
            piece.style.setProperty('--y', `${(Math.sin(angle) * distance).toFixed(1)}px`);
            piece.style.setProperty('--turn', `${Math.round(Math.random() * 720 - 360)}deg`);
            piece.style.setProperty('--lightness', `${45 + Math.round(Math.random() * 30)}%`);
            confetti.append(piece);
        }

        this.element.append(confetti);
        this.schedule(CONFETTI_MS, () => confetti.remove());
    }

    /** Third wrong answer: the bubble shrinks, turns grey and can't be answered any more. */
    freeze(bubble, task) {
        this.close();
        this.statusTarget.textContent = this.frozenValue;
        bubble.classList.add('bubble--frozen');
        const button = bubble.querySelector('.bubble__sum');
        button.disabled = true;
        button.setAttribute(
            'aria-label',
            format(this.frozenLabelValue, { label: this.sumLabel(task) }),
        );
        this.updateProgress();
        this.openNext(bubble);
    }

    animationEnded(event, bubble) {
        if (event.target !== bubble) {
            return;
        }

        if (event.animationName === 'bubble-pop') {
            this.removeBubble(bubble);
        } else if (event.animationName === 'bubble-wiggle') {
            bubble.classList.remove('bubble--wrong');
        } else if (event.animationName === 'bubble-appear') {
            bubble.classList.remove('bubble--appearing');
        }
    }

    removeBubble(bubble) {
        const timer = Number(bubble.dataset.popTimer);
        clearTimeout(timer);
        this.timers.delete(timer);
        bubble.remove();
    }

    /**
     * Opens the next remaining bubble after `previous` (the one just popped or frozen, or null for
     * the first), wrapping around; finishes the round when none is left.
     *
     * @param {HTMLElement | null} previous
     */
    openNext(previous) {
        if (isRoundComplete(this.round)) {
            this.finish();
            return;
        }

        const bubbles = [...this.fieldTarget.children];
        const start = previous ? bubbles.indexOf(previous) + 1 : 0;
        const next = [...bubbles.slice(start), ...bubbles.slice(0, start)].find(
            (bubble) =>
                bubble !== previous &&
                !bubble.classList.contains('bubble--popping') &&
                !bubble.classList.contains('bubble--frozen'),
        );
        if (next) {
            this.activate(next, { keepStatus: true });
        }
    }

    finish() {
        this.stopClock();
        this.cancelAutoOpen();
        this.renderClock();

        const passed = isPassed(this.round);
        const frozen = frozenTasks(this.round).length;
        this.passedMessageTarget.hidden = !passed;
        this.lateMessageTarget.hidden = passed || frozen > 0;
        this.frozenMessageTarget.hidden = frozen === 0;
        this.frozenMessageTarget.textContent = format(this.doneFrozenValue, {
            solved: this.round.tasks.length - frozen,
            total: this.round.tasks.length,
        });
        this.durationMessageTarget.hidden = frozen > 0;
        this.renderDuration();
        this.revealFrozenAnswers();
        // The last feedback ("Goed zo! …", "Ga verder met een andere!") no longer applies: the
        // completion panel says how it went.
        this.statusTarget.textContent = '';
        this.headerTarget.hidden = true;
        this.completeTarget.hidden = false;
        // After passing, the next level replaces "Nog een keer"; otherwise "Nog een keer" is the main button.
        const recommendNext = passed && this.hasNextTarget;
        if (this.hasNextTarget) {
            this.nextTarget.hidden = !recommendNext;
        }
        this.restartTarget.hidden = recommendNext;
        (recommendNext ? this.nextTarget : this.restartTarget).focus();
        this.recordRound();
    }

    /** "Je deed het dit keer in <strong>41 seconden</strong>.", the time in colour. */
    renderDuration() {
        const time = document.createElement('strong');
        time.className = 'round-complete__time';
        time.textContent = formatDuration(roundDuration(this.round), this.durationTextsValue);
        const [before, after = ''] = this.durationValue.split('%time%');
        this.durationMessageTarget.replaceChildren(before, time, after);
    }

    /**
     * Practice time first, then the result. One after the other on purpose: for a guest both
     * responses rewrite the progress cookie, and in parallel the second would undo the first.
     */
    async recordRound() {
        await this.recordPractice();
        await this.saveResult();
    }

    /** Adds this round's seconds to the practice time (the header shows the minutes). */
    async recordPractice() {
        const seconds = practiceSeconds(this.round);
        if (!this.practiceUrlValue || seconds === 0) {
            return;
        }

        try {
            await fetch(this.practiceUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ seconds }),
                credentials: 'same-origin',
                keepalive: true,
            });
        } catch {
            // Practice time is a bonus: a failure stays silent and never blocks the result.
        }
    }

    /** After the round, frozen bubbles show the right answer (only now, so it can't be peeked). */
    revealFrozenAnswers() {
        for (const task of frozenTasks(this.round)) {
            const bubble = this.fieldTarget.querySelector(`.bubble[data-task-id="${task.id}"]`);
            if (!bubble) {
                continue;
            }

            const params = { label: this.sumLabel(task), answer: task.answer };
            bubble.classList.add('bubble--revealed');
            bubble.querySelector('.bubble__solution').textContent = String(task.answer);
            bubble
                .querySelector('.bubble__sum')
                .setAttribute('aria-label', format(this.revealedLabelValue, params));
        }
    }

    async saveResult() {
        const durationMs = roundDuration(this.round);
        // Only complete rounds count as progress: rounds with frozen bubbles are not saved.
        if (!this.saveUrlValue || durationMs === null || frozenTasks(this.round).length > 0) {
            return;
        }

        try {
            const response = await fetch(this.saveUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ durationMs }),
                credentials: 'same-origin',
                keepalive: true,
            });
            if (!response.ok) {
                throw new Error(`Saving the result failed: HTTP ${response.status}`);
            }
        } catch {
            this.saveFailedMessageTargets.forEach((message) => (message.hidden = false));
        }
    }

    /** Starts the round's clock (once per round). */
    startClock() {
        if (this.round.startedAt !== null) {
            return;
        }

        this.round = startTimer(this.round, Date.now());
        this.runClock();
    }

    runClock() {
        this.stopClock();
        this.clock = setInterval(() => this.tick(), CLOCK_TICK_MS);
    }

    /** Whether a round is being played: after "Start!" and before the last bubble. */
    isPlaying() {
        return this.readyDone && this.round.startedAt !== null && !isRoundComplete(this.round);
    }

    /**
     * A click on a menu link (in the site header) during a round: don't leave yet, but pause and
     * ask whether to stop or continue. Opening the link in a new tab is left alone.
     */
    confirmLeave(event) {
        const link = event.target.closest?.('.site-header a[href]');
        if (
            !link ||
            this.leaving ||
            event.defaultPrevented ||
            event.button !== 0 ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey ||
            !this.isPlaying()
        ) {
            return;
        }

        // Runs on document, before Turbo's click handler on window, which then skips the link.
        event.preventDefault();
        this.pendingLink = link;
        this.round = pauseRound(this.round, Date.now());
        this.stopClock();
        this.cancelAutoOpen();
        this.element.classList.add('bubble-game--paused');
        this.renderClock();
        this.pauseDialogTarget.showModal();
        this.continueButtonTarget.focus();
    }

    /** "Verder spelen": closing the dialog resumes the round (see resume). */
    continue() {
        this.pauseDialogTarget.close();
    }

    /** "Stoppen": follow the menu link after all. The unfinished round doesn't count. */
    stop() {
        const link = this.pendingLink;
        this.leaving = true;
        this.pauseDialogTarget.close();
        link?.click();
    }

    /** The pause dialog closed ("Verder spelen" or Escape): the clock runs on. */
    resume() {
        this.pendingLink = null;
        if (this.leaving) {
            return;
        }

        const now = Date.now();
        this.round = resumeRound(this.round, now);
        this.element.classList.remove('bubble-game--paused');
        this.renderClock(now);
        if (!isTimeUp(this.round, now)) {
            this.runClock();
        }

        if (this.activeBubble) {
            this.answerInput().focus();
        } else {
            this.bubbleToActivate()?.querySelector('.bubble__sum').focus();
            this.scheduleAutoOpen();
        }
    }

    stopClock() {
        clearInterval(this.clock);
        this.clock = null;
    }

    tick() {
        const now = Date.now();
        this.renderClock(now);

        if (isTimeUp(this.round, now)) {
            this.stopClock();
            this.timerTarget.classList.add('round-timer--expired');
            this.statusTarget.textContent = this.timeUpValue;
        }
    }

    renderClock(now = Date.now()) {
        const left = timeLeft(this.round, now);
        this.timeTarget.textContent = formatTime(left);
        this.timerTarget.style.setProperty('--time-left', String(left / this.round.timeLimitMs));
    }

    updateProgress() {
        const count = remainingTasks(this.round).length;
        this.progressTarget.textContent = count > 0 ? format(this.remainingValue, { count }) : '';
    }

    answerInput() {
        return this.activeBubble?.querySelector('.bubble__answer') ?? null;
    }
}
