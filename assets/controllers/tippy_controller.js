import { Controller } from '@hotwired/stimulus';

// Initialise des infobulles Tippy sur tous les elements [data-tippy-content]
// presents dans la portee du controleur (par defaut le <body>).
export default class extends Controller {
    async connect() {
        const elements = this.element.querySelectorAll('[data-tippy-content]');
        if (elements.length === 0) {
            this.instances = [];
            return;
        }

        const [{ default: tippy }] = await Promise.all([
            import('tippy.js'),
            import('tippy.js/dist/tippy.css'),
            import('tippy.js/animations/shift-away.css'),
        ]);

        this.instances = tippy(elements, {
            animation: 'shift-away',
            theme: 'light-border',
            delay: [120, 0],
            allowHTML: false,
        });
    }

    disconnect() {
        if (Array.isArray(this.instances)) {
            this.instances.forEach((instance) => instance.destroy());
        }
    }
}
