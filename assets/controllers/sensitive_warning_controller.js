import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sensitive'];
    static values = {
        title: String,
        text: String,
        confirmText: String,
    };

    connect() {
        this.confirmed = false;
        this.initialValues = new Map();

        this.sensitiveTargets.forEach((field) => {
            this.initialValues.set(field.name, this.fieldValue(field));
        });
    }

    async submit(event) {
        if (this.confirmed || !this.hasSensitiveChange()) {
            return;
        }

        event.preventDefault();
        const submitter = event.submitter;
        const { default: Swal } = await import('sweetalert2');
        const result = await Swal.fire({
            title: this.titleValue || 'Confirmer la modification ?',
            text: this.textValue || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: this.confirmTextValue || 'Enregistrer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#dc2626',
        });

        if (result.isConfirmed) {
            this.confirmed = true;
            if (submitter) {
                this.element.requestSubmit(submitter);
            } else {
                this.element.requestSubmit();
            }
        }
    }

    hasSensitiveChange() {
        return this.sensitiveTargets.some((field) => this.initialValues.get(field.name) !== this.fieldValue(field));
    }

    fieldValue(field) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked ? '1' : '0';
        }

        return field.value;
    }
}
