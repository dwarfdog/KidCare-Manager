import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'content'];
    static values = {
        section: String,
        endpoint: String,
        focus: String,
    };

    connect() {
        this.abortController = null;
        this.changeTimer = null;
        this.onPopState = () => this.load(new URL(window.location.href), false);
        window.addEventListener('popstate', this.onPopState);
        this.focusTransaction();
    }

    disconnect() {
        window.removeEventListener('popstate', this.onPopState);
        this.abortController?.abort();
        window.clearTimeout(this.changeTimer);
    }

    submit(event) {
        event.preventDefault();
        this.loadFromForm();
    }

    change() {
        window.clearTimeout(this.changeTimer);
        this.changeTimer = window.setTimeout(() => this.loadFromForm(), 120);
    }

    paginate(event) {
        event.preventDefault();
        this.load(new URL(event.currentTarget.href), true);
    }

    loadFromForm() {
        const url = new URL(window.location.href);
        const data = new FormData(this.formTarget);

        for (const [name, value] of data.entries()) {
            if (!name.startsWith(`${this.sectionValue}_`)) {
                continue;
            }
            const normalized = String(value).trim();
            if (normalized === '') {
                url.searchParams.delete(name);
            } else {
                url.searchParams.set(name, normalized);
            }
        }

        url.searchParams.delete(`${this.sectionValue}_page`);
        url.searchParams.delete('focus');
        this.load(url, true);
    }

    async load(pageUrl, pushHistory) {
        this.abortController?.abort();
        this.abortController = new AbortController();
        this.element.setAttribute('aria-busy', 'true');
        this.element.classList.add('opacity-70');

        const endpoint = new URL(this.endpointValue, window.location.origin);
        endpoint.search = pageUrl.search;

        try {
            const response = await fetch(endpoint, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.abortController.signal,
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = documentFragment.body.firstElementChild;
            if (!replacement) {
                throw new Error('Fragment de liste manquant.');
            }

            if (pushHistory) {
                window.history.pushState({}, '', pageUrl);
            }
            this.element.replaceWith(replacement);
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.location.assign(pageUrl);
            }
        }
    }

    focusTransaction() {
        if (!this.hasFocusValue || this.focusValue === '') {
            return;
        }

        window.requestAnimationFrame(() => {
            const row = document.getElementById(`bank-transaction-${this.focusValue}`);
            if (!row || !this.element.contains(row)) {
                return;
            }
            row.classList.add('is-focused');
            row.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
            row.focus({ preventScroll: true });
            window.setTimeout(() => row.classList.remove('is-focused'), 4000);
        });
    }
}
