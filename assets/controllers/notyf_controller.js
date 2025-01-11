import { Controller } from '@hotwired/stimulus';
import { Notyf } from "notyf";
import 'notyf/notyf.min.css';
export default class extends Controller {
    connect() {
        this.flashesValue = JSON.parse(this.element.dataset.flashes || "{}");
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
                    icon: '<i class="fas fa-info fa-2x text-black"></i>',
                    className: 'notyf-custom-info',
                },
                {
                    type: 'warning',
                    background: '#f39c12',
                    icon: '<i class="fas fa-exclamation-triangle fa-2x text-black"></i>',
                    className: 'notyf-custom-warning',
                },
                {
                    type: 'success',
                    background: '#2ecc71',
                    icon: '<i class="fas fa-check fa-2x text-black"></i>',
                    className: 'notyf-custom-success',
                },
                {
                    type: 'error',
                    background: '#e74c3c',
                    icon: '<i class="fas fa-times-circle fa-2x text-black"></i>',
                    className: 'notyf-custom-error',
                },
            ],
            dismissible: true,
        });
        this.displayFlashes();
    }
    displayFlashes() {
        if (!this.flashesValue) return;
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
}