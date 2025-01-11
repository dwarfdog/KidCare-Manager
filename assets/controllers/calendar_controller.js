// assets/controllers/calendar_controller.js
import { Controller } from '@hotwired/stimulus';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import frLocale from '@fullcalendar/core/locales/fr';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';

// Créez une instance de Notyf
const notyf = new Notyf();

export default class extends Controller {
    static targets = ['calendar']
    static values = {
        events: Array,
        nannyId: Number
    }

    handleSelect(selectInfo) {
        const meals = prompt('Nombre de repas ?', '0');
        if (meals === null) {
            selectInfo.view.calendar.unselect();
            return;
        }
        const params = new URLSearchParams({
            nanny: this.nannyIdValue,
            start: selectInfo.startStr,
            end: selectInfo.endStr,
            meals: parseInt(meals, 10),
        });

        fetch(`/care/create?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then((errorData) => {
                    const errorMessage = errorData.error || 'Une erreur inattendue s\'est produite.';
                    throw new Error(errorMessage); // Lever une erreur pour le catch
                });
            }
            return response.json();
        })
        .then(data => {
            this.calendar.addEvent({
                id: data.id,
                title: `${meals} repas`,
                start: selectInfo.startStr,
                end: selectInfo.endStr
            });
        })
        .catch(error => {
            notyf.error(error.message || 'Une erreur est survenue lors de la création de la garde.');
            })
        .finally(() => {
            selectInfo.view.calendar.unselect();
        });
    }

    connect() {
        this.calendar = new Calendar(this.calendarTarget, {
            plugins: [ dayGridPlugin, timeGridPlugin, interactionPlugin ],
            initialView: 'timeGridWeek',
            firstDay: 1,
            locale: frLocale,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            height: 'auto',
            selectable: true,
            editable: true,
            nowIndicator: true,
            events: this.eventsValue,
            select: (info) => this.handleSelect(info)
        });

        this.calendar.render();
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy();
        }
    }
}