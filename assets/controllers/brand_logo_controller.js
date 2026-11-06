import { Controller } from '@hotwired/stimulus';

/*
 * The Scholi logo in the header. When the image can't be loaded, the link falls back to the
 * blue pill with the word "Scholi" (the text is in the markup, only hidden while the logo works).
 */
export default class extends Controller {
    static targets = ['image', 'text'];

    connect() {
        // The error may have happened before this controller was connected.
        if (this.imageTarget.complete && this.imageTarget.naturalWidth === 0) {
            this.fallback();
        }
    }

    fallback() {
        this.element.classList.add('site-header__brand--text');
        this.imageTarget.hidden = true;
        this.textTarget.hidden = false;
    }
}
