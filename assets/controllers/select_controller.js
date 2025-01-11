// assets/controllers/select_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    submit(event) {
        const id = event.target.value;
        if (id) {
            window.location.href = `${window.location.pathname}/${id}`;
        } else {
            window.location.href = window.location.pathname;
        }
    }
}