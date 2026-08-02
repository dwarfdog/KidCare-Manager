import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    async connect() {
        this.flashesValue = JSON.parse(this.element.dataset.flashes || "{}");
        if (!this.hasFlashes()) {
            return;
        }

        const { Notyf } = await import('notyf');
        this.notyf = new Notyf({
            duration: 3000,
            position: {
                x: 'right',
                y: 'bottom',
            },
            types: [
                {
                    type: 'info',
                    background: '#3498db',
                    icon: '<i class="fas fa-info text-black"></i>',
                    className: 'notyf-custom-info',
                },
                {
                    type: 'warning',
                    background: '#f39c12',
                    icon: '<i class="fas fa-exclamation-triangle text-black"></i>',
                    className: 'notyf-custom-warning',
                },
                {
                    type: 'success',
                    background: '#2ecc71',
                    icon: '<i class="fas fa-check text-black"></i>',
                    className: 'notyf-custom-success',
                },
                {
                    type: 'error',
                    background: '#e74c3c',
                    icon: '<i class="fas fa-times-circle text-black"></i>',
                    className: 'notyf-custom-error',
                },
            ],
            dismissible: true,
        });
        this.displayFlashes();
    }
    displayFlashes() {
        if (!this.hasFlashes()) return;
        for (const [type, messages] of Object.entries(this.flashesValue)) {
            if (Array.isArray(messages)) {
                messages.forEach((message) => {
                    if (["info", "warning", "success", "error"].includes(type)) {
                        this.notyf.open({ type, message });
                    } else {
                        console.warn(`Type de notification inconnu : ${type}`);
                    }
                });
            }
        }
    }

    hasFlashes() {
        return this.flashesValue && Object.values(this.flashesValue).some((messages) => Array.isArray(messages) && messages.length > 0);
    }
}
