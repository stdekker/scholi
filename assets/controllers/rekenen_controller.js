import { Controller } from '@hotwired/stimulus';

/*
 * Binds the rekenen game logic (assets/games/rekenen/) to the page.
 * Keep this controller thin: DOM wiring only, no game rules.
 *
 * Usage: <section data-controller="rekenen">…</section>
 */
export default class extends Controller {
    connect() {
        this.element.dataset.ready = 'true';
    }
}
