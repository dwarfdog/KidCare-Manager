import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        title: String,
        text: String,
        confirmText: String,
    };

    connect() {
        this.confirmed = false;
    }

    async submit(event) {
        if (this.confirmed) {
            return;
        }

        event.preventDefault();
        const { default: Swal } = await import('sweetalert2');
        const result = await Swal.fire({
            title: this.titleValue || 'Confirmer',
            text: this.textValue || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: this.confirmTextValue || 'Confirmer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#dc2626',
        });

        if (result.isConfirmed) {
            this.confirmed = true;
            this.element.requestSubmit();
        }
    }
}
