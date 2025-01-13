import { Controller } from '@hotwired/stimulus';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import frLocale from '@fullcalendar/core/locales/fr';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import Swal from 'sweetalert2';

// Créez une instance de Notyf
const notyf = new Notyf();

export default class extends Controller {
    static targets = ['calendar'];
    static values = {
        events: Array,
        nannyId: Number,
    };

    connect() {
        const isMobile = window.matchMedia("(max-width: 768px)").matches;
        const isDesktop = window.matchMedia("(min-width: 1024px)").matches;

        this.calendar = new Calendar(this.calendarTarget, {
            plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
            initialView: isMobile ? 'timeGridDay' : 'timeGridWeek', // Vue journalière sur mobile, hebdomadaire ailleurs
            firstDay: 1,
            locale: frLocale,
            headerToolbar: {
                left: isMobile ? 'prev,next' : 'prev,next today',
                center: isMobile ? 'title' : '',
                right: isMobile ? '' : 'title',
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '20:00:00',
            contentHeight: isDesktop ? '77vh' : isMobile ? 'auto' : '85vh', // Réduction sur bureau et auto sur mobile
            selectable: false,
            editable: true,
            nowIndicator: true,
            allDaySlot: false,
            eventOverlap: false,
            events: this.eventsValue,
            dateClick: (info) => this.handleDoubleClick(info),
            eventContent: (arg) => this.renderEventWithDeleteButton(arg),
            eventDrop: (info) => this.handleEventUpdate(info),
            eventResize: (info) => this.handleEventUpdate(info),
            eventClick: (info) => this.handleEventEdit(info),
        });

        // Ajustements spécifiques pour mobile
        if (isMobile) {
            // Réduction de la taille des flèches (appliquer une classe CSS plus petite)
            const calendarEl = this.calendarTarget;
            calendarEl.classList.add('text-sm'); // Réduction globale des flèches et boutons

            // Suppression de l'année dans le titre
            this.calendar.setOption('titleFormat', { month: 'long', day: 'numeric' });

            // Réduction de la taille du titre
            const titleEl = calendarEl.querySelector('.fc-toolbar-title');
            if (titleEl) {
                titleEl.style.fontSize = '1rem'; // Taille réduite
            }
        }

        window.calendarController = this;

        this.calendar.render();
    }

    handleEventEdit(info) {
        if (this.lastClick && Date.now() - this.lastClick < 300) {
            const event = info.event;

            const startTime = new Date(event.start);
            const endTime = new Date(event.end || new Date(startTime.getTime() + 30 * 60 * 1000));

            const formatTime = (date) =>
                date.toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                });

            const formattedStartTime = formatTime(startTime);
            const formattedEndTime = formatTime(endTime);

            Swal.fire({
                title: 'Modifier la garde',
                html: `
                    <form id="edit-care-form">
                        <div class="mb-4">
                            <label for="startTime" class="block text-base font-medium text-gray-700">Heure de début</label>
                            <input type="time" id="startTime" value="${formattedStartTime}" class="p-2 mt-1 block w-full shadow-sm text-base border-gray-300 rounded-md">
                        </div>
                        <div class="mb-4">
                            <label for="endTime" class="block text-base font-medium text-gray-700">Heure de fin</label>
                            <input type="time" id="endTime" value="${formattedEndTime}" class="p-2 mt-1 block w-full shadow-sm text-base border-gray-300 rounded-md">
                        </div>
                        <div class="mb-4">
                            <label for="mealsCount" class="block text-base font-medium text-gray-700">Nombre de repas</label>
                            <input type="number" id="mealsCount" value="${event.extendedProps.meals || 0}" min="0" class="p-2 mt-1 block w-full shadow-sm text-base border-gray-300 rounded-md">
                        </div>
                    </form>
                `,
                confirmButtonText: 'Modifier',
                showCancelButton: true,
                cancelButtonText: 'Annuler',
                focusConfirm: false,
                preConfirm: () => {
                    const start = document.getElementById('startTime').value;
                    const end = document.getElementById('endTime').value;
                    const meals = parseInt(document.getElementById('mealsCount').value, 10) || 0;

                    if (!start || !end) {
                        Swal.showValidationMessage('Les heures de début et de fin sont obligatoires.');
                        return false;
                    }

                    if (new Date(`1970-01-01T${end}:00`) <= new Date(`1970-01-01T${start}:00`)) {
                        Swal.showValidationMessage('L\'heure de fin doit être après l\'heure de début.');
                        return false;
                    }

                    return { start, end, meals };
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    this.updateEvent(event, result.value);
                }
            });
        }
        this.lastClick = Date.now();
    }

    updateEvent(event, formData) {
        const startDate = event.start.toISOString().split('T')[0];
        const start = `${startDate}T${formData.start}:00`;
        const end = `${startDate}T${formData.end}:00`;

        const params = new URLSearchParams({
            nanny: this.nannyIdValue,
            start,
            end,
            meals: formData.meals,
        });

        fetch(`/care/update/${event.id}?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Erreur lors de la mise à jour de l\'événement.');
                }
                return response.json();
            })
            .then((data) => {
                const hoursCount = ((new Date(data.end) - new Date(data.start)) / (1000 * 60 * 60)).toFixed(2);

                const startFormatted = new Date(data.start).toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                });

                const endFormatted = new Date(data.end).toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                });

                const mealsText = formData.meals > 0 ? `<br>${formData.meals} repas` : '';

                event.setStart(data.start);
                event.setEnd(data.end);
                event.setProp('title', `${hoursCount} heures`);
                event.setExtendedProp('description', `De ${startFormatted} à ${endFormatted}${mealsText}`);

                notyf.success('L\'événement a été mis à jour avec succès.');
            })
            .catch((error) => {
                notyf.error(error.message || 'Une erreur est survenue lors de la mise à jour de l\'événement.');
            });
    }

    getWeekForTemplate(templateSlug) {
        // Obtenir la date de début actuelle de la vue
        const currentDate = this.calendar.view.currentStart;

        // Créer une copie de la date actuelle
        const localDate = new Date(currentDate);

        // Ajuster manuellement la timezone pour obtenir une date locale correcte
        const offsetMinutes = localDate.getTimezoneOffset();
        localDate.setMinutes(localDate.getMinutes() - offsetMinutes);

        // Formater la date pour obtenir une chaîne sans décalage UTC
        const formattedWeekStart = localDate.toISOString().split('T')[0];

        console.log('Application du template', templateSlug, formattedWeekStart);

        confirmation(
            event,
            'Application du template',
            'Voulez-vous appliquer le template à la semaine en cours ?',
            `/care-template/apply/${templateSlug}?start=${formattedWeekStart}`
        );
    }

    handleEventUpdate(info) {
        const event = info.event;

        const params = new URLSearchParams({
            nanny: this.nannyIdValue,
            start: event.start.toISOString(),
            end: event.end ? event.end.toISOString() : null,
        });

        fetch(`/care/update/${event.id}?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Erreur lors de la mise à jour de l\'événement.');
                }
                return response.json();
            })
            .then((data) => {
                const hoursCount = ((new Date(data.end) - new Date(data.start)) / (1000 * 60 * 60)).toFixed(2);

                // Met à jour l'affichage de l'événement
                event.setProp('title', `${hoursCount} heures`);
                event.setExtendedProp(
                    'description',
                    `De ${new Date(data.start).toLocaleTimeString('fr-FR', {
                        hour: '2-digit',
                        minute: '2-digit',
                    })} à ${new Date(data.end).toLocaleTimeString('fr-FR', {
                        hour: '2-digit',
                        minute: '2-digit',
                    })}`
                );
                notyf.success('L\'événement a été mis à jour avec succès.');
            })
            .catch((error) => {
                notyf.error(error.message || 'Une erreur est survenue lors de la mise à jour de l\'événement.');
                info.revert(); // Annule le déplacement/redimensionnement en cas d'erreur
            });
    }



    renderEventWithDeleteButton(arg) {
        const container = document.createElement('div');
        container.style.position = 'relative';

        // Titre avec le nombre d'heures
        const title = document.createElement('div');
        title.innerHTML = arg.event.title;
        title.style.fontWeight = 'bold';

        // Description avec heure de début, fin et nombre de repas
        const description = document.createElement('div');
        description.innerHTML = arg.event.extendedProps.description;
        description.style.fontSize = '0.85rem';
        description.style.marginTop = '4px';

        // Bouton de suppression (croix FontAwesome)
        const deleteButton = document.createElement('i');
        deleteButton.className = 'fas fa-times';
        deleteButton.style.position = 'absolute';
        deleteButton.style.top = '5px';
        deleteButton.style.right = '5px';
        deleteButton.style.cursor = 'pointer';
        deleteButton.style.color = 'red';
        deleteButton.style.fontSize = '12px';

        deleteButton.addEventListener('click', (e) => {
            e.stopPropagation();
            this.handleEventDelete(arg.event);
        });

        // Ajout des éléments au conteneur
        container.appendChild(title);
        container.appendChild(description);
        container.appendChild(deleteButton);

        return { domNodes: [container] };
    }

    handleEventDelete(event) {
        const date = new Date(event.start).toLocaleString('fr-FR', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
        const start = new Date(event.start).toLocaleString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
        });
        const end = new Date(event.end).toLocaleString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
        });

        Swal.fire({
            title: 'Supprimer la garde ?',
            html: `
                <p>Voulez-vous vraiment supprimer cette garde ?</p>
                <p><strong>Date :</strong> ${date}</p>
                <p><strong>Horaires :</strong> ${start} à ${end}</p>
                <p><strong>Repas :</strong> ${event.title}</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Supprimer',
            cancelButtonText: 'Annuler',
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/care/delete/${event.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => {
                        if (!response.ok) {
                            return response.json().then((errorData) => {
                                const errorMessage = errorData.error || 'Une erreur inattendue s\'est produite.';
                                throw new Error(errorMessage);
                            });
                        }
                        event.remove();
                        notyf.success('La garde a été supprimée avec succès.');
                    })
                    .catch((error) => {
                        notyf.error(error.message || 'Une erreur est survenue lors de la suppression de la garde.');
                    });
            }
        });
    }

    handleDoubleClick(info) {
        if (this.lastClick && Date.now() - this.lastClick < 300) {
            // Double-clic détecté
            const startTime = new Date(info.date);
            const defaultEndTime = new Date(startTime.getTime() + 30 * 60 * 1000); // 30 minutes après

            const formatTime = (date) =>
                date.toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                });

            const formattedStartTime = formatTime(startTime);
            const formattedEndTime = formatTime(defaultEndTime);

            Swal.fire({
                title: 'Créer une garde',
                html: `
                    <form id="create-care-form">
                        <div class="mb-4">
                            <label for="startTime" class="block text-base font-medium text-gray-700">Heure de début</label>
                            <input type="time" id="startTime" value="${formattedStartTime}" class="p-2 mt-1 block w-full shadow-sm text-base border-gray-300 rounded-md">
                        </div>
                        <div class="mb-4">
                            <label for="endTime" class="block text-base font-medium text-gray-700">Heure de fin</label>
                            <input type="time" id="endTime" value="${formattedEndTime}" class="p-2 mt-1 block w-full shadow-sm text-base border-gray-300 rounded-md">
                        </div>
                        <div class="mb-4">
                            <label for="mealsCount" class="block text-base font-medium text-gray-700">Nombre de repas</label>
                            <input type="number" id="mealsCount" value="0" min="0" class="p-2 mt-1 block w-full shadow-sm text-base border-gray-300 rounded-md">
                        </div>
                    </form>
                `,
                confirmButtonText: 'Créer',
                showCancelButton: true,
                cancelButtonText: 'Annuler',
                focusConfirm: false,
                preConfirm: () => {
                    const start = document.getElementById('startTime').value;
                    const end = document.getElementById('endTime').value;
                    const meals = parseInt(document.getElementById('mealsCount').value, 10) || 0;

                    if (!start || !end) {
                        Swal.showValidationMessage('Les heures de début et de fin sont obligatoires.');
                        return false;
                    }

                    if (new Date(`1970-01-01T${end}:00`) <= new Date(`1970-01-01T${start}:00`)) {
                        Swal.showValidationMessage('L\'heure de fin doit être après l\'heure de début.');
                        return false;
                    }

                    return { start, end, meals };
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    this.createEvent(info.date, result.value);
                }
            });
        }
        this.lastClick = Date.now();
    }

    createEvent(startDate, formData) {
        const dateStr = startDate.toISOString().split('T')[0];
        const start = `${dateStr}T${formData.start}:00`;
        const end = `${dateStr}T${formData.end}:00`;

        const params = new URLSearchParams({
            nanny: this.nannyIdValue,
            start,
            end,
            meals: formData.meals,
        });

        fetch(`/care/create?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    return response.json().then((errorData) => {
                        const errorMessage = errorData.error || 'Une erreur inattendue s\'est produite.';
                        throw new Error(errorMessage);
                    });
                }
                return response.json();
            })
            .then((data) => {
                const hoursCount = ((new Date(data.end) - new Date(data.start)) / (1000 * 60 * 60)).toFixed(2); // Calcul du nombre d'heures

                const startFormatted = new Date(data.start).toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                });

                const endFormatted = new Date(data.end).toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                });

                const mealsText = formData.meals > 0 ? `<br>${formData.meals} repas` : ''; // Affiche uniquement si meals > 0

                this.calendar.addEvent({
                    id: data.id,
                    title: `${hoursCount} heures`, // Titre avec le nombre d'heures
                    start: data.start,
                    end: data.end,
                    extendedProps: {
                        description: `De ${startFormatted} à ${endFormatted}${mealsText}`,
                    },
                });
                notyf.success('La garde a été créée avec succès.');
            });
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy();
        }
    }
}
