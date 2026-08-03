import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['calendar', 'calendarPanel', 'listPanel', 'list'];

    static values = {
        events: Array,
        nannyId: String,
        currentDate: String,
        csrfToken: String,
        defaultStart: String,
        defaultEnd: String,
        defaultMeals: Number,
    };

    async connect() {
        if (!this.hasCalendarTarget) {
            return;
        }

        const [
            { Calendar },
            { default: dayGridPlugin },
            { default: timeGridPlugin },
            { default: interactionPlugin },
            { default: frLocale },
            { Notyf },
            { default: Swal },
        ] = await Promise.all([
            import('@fullcalendar/core'),
            import('@fullcalendar/daygrid'),
            import('@fullcalendar/timegrid'),
            import('@fullcalendar/interaction'),
            import('@fullcalendar/core/locales/fr'),
            import('notyf'),
            import('sweetalert2'),
        ]);

        this.Swal = Swal;
        this.notyf = new Notyf({
            duration: 3500,
            position: { x: 'right', y: 'top' },
        });

        const isMobile = window.matchMedia('(max-width: 767px)').matches;

        this.calendar = new Calendar(this.calendarTarget, {
            plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
            initialView: isMobile ? 'timeGridDay' : 'timeGridWeek',
            timeZone: 'local',
            firstDay: 1,
            initialDate: this.currentDateValue,
            locale: frLocale,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: isMobile ? '' : 'timeGridWeek,timeGridDay,dayGridMonth',
            },
            buttonText: {
                today: 'Aujourd’hui',
                month: 'Mois',
                week: 'Semaine',
                day: 'Jour',
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '24:00:00',
            slotDuration: '00:30:00',
            contentHeight: isMobile ? 'auto' : '74vh',
            expandRows: true,
            selectable: true,
            editable: true,
            nowIndicator: true,
            allDaySlot: false,
            eventOverlap: true,
            events: this.eventsValue,
            select: (info) => this.openCreateDialog(info.start, info.end),
            dateClick: (info) => {
                const start = info.allDay ? this.dateWithTime(info.date, this.defaultStartValue || '08:00') : info.date;
                const end = info.allDay ? this.dateWithTime(info.date, this.defaultEndValue || '17:00') : this.addMinutes(info.date, 30);
                this.openCreateDialog(start, end);
            },
            eventContent: (arg) => this.renderEvent(arg),
            eventDrop: (info) => this.persistEventMove(info),
            eventResize: (info) => this.persistEventMove(info),
            eventClick: (info) => this.openEditDialog(info.event),
        });

        this.calendar.render();
        this.renderList();
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy();
        }
    }

    async openCreateDialog(start, end) {
        const result = await this.careDialog('Créer une garde', start, end, this.defaultMealsValue || 0, 'Créer');
        if (!result.isConfirmed) {
            return;
        }

        const payload = this.payloadFromDialog(start, result.value);
        this.request('/care/create', 'POST', payload)
            .then((data) => {
                this.addReturnedEvents(data.events || []);
                this.renderList();
                this.notyf.success('La garde a été créée.');
            })
            .catch((error) => this.notyf.error(error.message));
    }

    async openEditDialog(event) {
        if (event.extendedProps.displayOnly) {
            await this.Swal.fire({
                title: event.title,
                text: event.extendedProps.notes || 'Période d’indisponibilité. La modification se fait depuis l’écran Indisponibilités.',
                icon: 'info',
                confirmButtonText: 'Fermer',
            });
            return;
        }
        const start = new Date(event.start);
        const end = event.end ? new Date(event.end) : this.addMinutes(start, 30);
        const meals = Number.parseInt(event.extendedProps.meals || 0, 10);
        const result = await this.careDialog('Modifier la garde', start, end, meals, 'Modifier', event);
        if (result.isDenied) {
            await this.statusDialog(event);
            return;
        }
        if (!result.isConfirmed) {
            return;
        }

        const payload = this.payloadFromDialog(start, result.value);
        this.request(`/care/update/${event.id}`, 'PATCH', payload)
            .then((data) => {
                this.removeEvents(data.removed || [event.id]);
                this.addReturnedEvents(data.events || []);
                this.renderList();
                this.notyf.success('La garde a été mise à jour.');
            })
            .catch((error) => this.notyf.error(error.message));
    }

    persistEventMove(info) {
        const event = info.event;
        if (event.extendedProps.displayOnly) {
            info.revert();
            return;
        }
        const payload = {
            nanny: this.nannyIdValue,
            start: this.formatDateTime(event.start),
            end: this.formatDateTime(event.end || this.addMinutes(event.start, 30)),
            meals: Number.parseInt(event.extendedProps.meals || 0, 10),
        };

        this.request(`/care/update/${event.id}`, 'PATCH', payload)
            .then((data) => {
                this.removeEvents(data.removed || [event.id]);
                this.addReturnedEvents(data.events || []);
                this.renderList();
                this.notyf.success('La garde a été déplacée.');
            })
            .catch((error) => {
                info.revert();
                this.notyf.error(error.message);
            });
    }

    async handleEventDelete(event) {
        if (event.extendedProps.displayOnly) {
            return;
        }
        const result = await this.Swal.fire({
            title: 'Supprimer la garde ?',
            text: `${this.formatReadableDate(event.start)} de ${this.formatTime(event.start)} à ${this.formatTime(event.end)}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Supprimer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#dc2626',
        });

        if (!result.isConfirmed) {
            return;
        }

        this.request(`/care/delete/${event.id}`, 'DELETE')
            .then(() => {
                event.remove();
                this.renderList();
                this.notyf.success('La garde a été supprimée.');
            })
            .catch((error) => this.notyf.error(error.message));
    }

    async applyTemplate(event) {
        const button = event.currentTarget;
        const startDate = this.templateStartDate(button);
        const preview = await this.previewTemplate(button, startDate).catch((error) => {
            this.notyf.error(error.message);
            return null;
        });

        if (!preview) {
            return;
        }

        const conflictHtml = preview.conflicts.length > 0
            ? `<div class="max-h-72 overflow-y-auto text-left text-sm"><ul class="space-y-2">${preview.conflicts.map((conflict) => `<li><strong>${this.escapeHtml(conflict.label)}</strong><br><span class="text-slate-600">${this.escapeHtml(conflict.reason)}</span></li>`).join('')}</ul></div>`
            : '<p class="text-sm text-slate-600">Aucun conflit détecté.</p>';

        const result = await this.Swal.fire({
            title: 'Appliquer le template ?',
            html: `
                <div class="space-y-3">
                    <p class="text-sm text-slate-600">Semaine du ${this.formatReadableDate(startDate)}</p>
                    <p class="text-sm font-medium">${preview.accepted_count} créneau(x) valide(s), ${preview.rejected_count} refusé(s)</p>
                    ${conflictHtml}
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Appliquer',
            cancelButtonText: 'Annuler',
        });

        if (!result.isConfirmed) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'post';
        form.action = button.dataset.templateUrl;
        form.className = 'hidden';

        form.appendChild(this.hiddenInput('_token', button.dataset.templateToken));
        form.appendChild(this.hiddenInput('start', this.formatDate(startDate)));

        document.body.appendChild(form);
        form.submit();
    }

    previewTemplate(button, startDate) {
        const body = new FormData();
        body.append('_token', button.dataset.templateToken);
        body.append('start', this.formatDate(startDate));

        return fetch(button.dataset.templatePreviewUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        }).then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.error || 'La prévisualisation a échoué.');
            }

            return {
                accepted_count: data.accepted_count || 0,
                rejected_count: data.rejected_count || 0,
                conflicts: data.conflicts || [],
            };
        });
    }

    templateStartDate(button) {
        const offset = Number.parseInt(button.dataset.templateWeekOffset || '0', 10);
        return this.addDays(this.calendar.view.currentStart, Number.isInteger(offset) ? offset * 7 : 0);
    }

    showCalendar() {
        if (this.hasCalendarPanelTarget) {
            this.calendarPanelTarget.classList.remove('hidden');
        }
        if (this.hasListPanelTarget) {
            this.listPanelTarget.classList.add('hidden');
        }
        if (this.calendar) {
            this.calendar.updateSize();
        }
    }

    showList() {
        if (this.hasCalendarPanelTarget) {
            this.calendarPanelTarget.classList.add('hidden');
        }
        if (this.hasListPanelTarget) {
            this.listPanelTarget.classList.remove('hidden');
        }
        this.renderList();
    }

    changeNanny(event) {
        if (event.currentTarget.value) {
            window.location.assign(event.currentTarget.value);
        }
    }

    async careDialog(title, start, end, meals, confirmButtonText, event = null) {
        return this.Swal.fire({
            title,
            html: `
                <div class="space-y-4 text-left">
                    <label class="block text-sm font-medium text-slate-700" for="care-start-time">Heure de début</label>
                    <input id="care-start-time" type="time" value="${this.formatTime(start)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">

                    <label class="block text-sm font-medium text-slate-700" for="care-end-time">Heure de fin</label>
                    <input id="care-end-time" type="time" value="${this.formatTime(end)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">

                    <label class="block text-sm font-medium text-slate-700" for="care-meals">Repas</label>
                    <input id="care-meals" type="number" min="0" step="1" value="${meals}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
            `,
            confirmButtonText,
            showCancelButton: true,
            showDenyButton: event !== null,
            denyButtonText: 'État / notes',
            cancelButtonText: 'Annuler',
            focusConfirm: false,
            preConfirm: () => {
                const startTime = document.getElementById('care-start-time').value;
                const endTime = document.getElementById('care-end-time').value;
                const mealsCount = Number.parseInt(document.getElementById('care-meals').value, 10);

                if (!startTime || !endTime) {
                    this.Swal.showValidationMessage('Les horaires sont obligatoires.');
                    return false;
                }

                if (!Number.isInteger(mealsCount) || mealsCount < 0) {
                    this.Swal.showValidationMessage('Le nombre de repas doit être un entier positif ou zéro.');
                    return false;
                }

                return { startTime, endTime, meals: mealsCount };
            },
        });
    }

    async statusDialog(event) {
        const currentStatus = event.extendedProps.status || 'planned';
        const currentReason = event.extendedProps.reason || 'other';
        const currentNotes = event.extendedProps.notes || '';
        const result = await this.Swal.fire({
            title: 'État de la garde',
            html: `<div class="space-y-4 text-left">
                <label class="block text-sm font-medium" for="care-status">État</label>
                <select id="care-status" class="w-full rounded-md border border-slate-300 px-3 py-2">
                    <option value="planned" ${currentStatus === 'planned' ? 'selected' : ''}>Prévue (verrou manuel)</option>
                    <option value="realized" ${currentStatus === 'realized' ? 'selected' : ''}>Réalisée</option>
                    <option value="cancelled" ${currentStatus === 'cancelled' ? 'selected' : ''}>Non réalisée / annulée</option>
                </select>
                <label class="block text-sm font-medium" for="care-reason">Motif de non-réalisation</label>
                <select id="care-reason" class="w-full rounded-md border border-slate-300 px-3 py-2">
                    ${[['child_absence','Absence enfant'],['nanny_absence','Absence nounou'],['paid_leave','Congé payé'],['unpaid_leave','Congé non payé'],['child_sick_leave','Arrêt maladie enfant'],['nanny_sick_leave','Arrêt maladie nounou'],['closure','Fermeture'],['other','Autre']].map(([value,label]) => `<option value="${value}" ${currentReason === value ? 'selected' : ''}>${label}</option>`).join('')}
                </select>
                <label class="flex items-center gap-2 text-sm"><input id="care-remunerated" type="checkbox" ${event.extendedProps.remunerated && currentStatus !== 'realized' ? 'checked' : ''}> Rémunérer malgré la non-réalisation</label>
                <label class="block text-sm font-medium" for="care-notes">Notes privées</label>
                <textarea id="care-notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">${this.escapeHtml(currentNotes)}</textarea>
            </div>`,
            showCancelButton: true,
            confirmButtonText: 'Enregistrer',
            cancelButtonText: 'Annuler',
            preConfirm: () => ({
                status: document.getElementById('care-status').value,
                reason: document.getElementById('care-reason').value,
                remunerated: document.getElementById('care-remunerated').checked,
                notes: document.getElementById('care-notes').value,
            }),
        });
        if (!result.isConfirmed) return;
        try {
            const data = await this.request(`/care/status/${event.id}`, 'PATCH', result.value);
            event.remove();
            this.calendar.addEvent(data.event);
            this.renderList();
            this.notyf.success('État de la garde mis à jour.');
        } catch (error) {
            this.notyf.error(error.message);
        }
    }

    payloadFromDialog(baseDate, values) {
        const start = this.dateWithTime(baseDate, values.startTime);
        let end = this.dateWithTime(baseDate, values.endTime);

        if (end <= start) {
            end = this.addDays(end, 1);
        }

        return {
            nanny: this.nannyIdValue,
            start: this.formatDateTime(start),
            end: this.formatDateTime(end),
            meals: values.meals,
        };
    }

    request(url, method, payload = null) {
        const options = {
            method,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.csrfTokenValue,
            },
        };

        if (payload !== null) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        return fetch(url, options).then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.error || 'La demande a échoué.');
            }

            return data;
        });
    }

    addReturnedEvents(events) {
        events.forEach((event) => this.calendar.addEvent(event));
    }

    removeEvents(ids) {
        ids.forEach((id) => {
            const event = this.calendar.getEventById(id);
            if (event) {
                event.remove();
            }
        });
    }

    renderList() {
        if (!this.hasListTarget || !this.calendar) {
            return;
        }

        const events = this.calendar.getEvents()
            .slice()
            .sort((a, b) => a.start - b.start);

        this.listTarget.innerHTML = '';

        if (events.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'rounded-md border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500';
            empty.textContent = 'Aucune garde.';
            this.listTarget.appendChild(empty);
            return;
        }

        events.forEach((event) => {
            const row = document.createElement('div');
            row.className = 'rounded-md border border-slate-200 bg-white p-3';
            if (event.extendedProps.conflict) {
                row.classList.add('border-rose-300', 'bg-rose-50');
            }

            const header = document.createElement('div');
            header.className = 'flex items-start justify-between gap-3';

            const content = document.createElement('button');
            content.type = 'button';
            content.className = 'min-w-0 text-left';
            content.addEventListener('click', () => this.openEditDialog(event));

            const title = document.createElement('p');
            title.className = 'font-semibold text-slate-900';
            title.textContent = this.formatReadableDate(event.start);

            const meta = document.createElement('p');
            meta.className = 'mt-1 text-sm text-slate-500';
            meta.textContent = `${this.formatTime(event.start)} - ${this.formatTime(event.end)} | ${event.title}`;

            content.appendChild(title);
            content.appendChild(meta);
            if (event.extendedProps.conflict) {
                const warning = document.createElement('p');
                warning.className = 'mt-2 text-xs font-semibold text-rose-700';
                warning.textContent = 'Conflit de planning détecté';
                content.appendChild(warning);
            }

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'shrink-0 rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700';
            deleteButton.textContent = 'Supprimer';
            deleteButton.addEventListener('click', () => this.handleEventDelete(event));

            header.appendChild(content);
            if (!event.extendedProps.displayOnly) {
                header.appendChild(deleteButton);
            }
            row.appendChild(header);

            const meals = Number.parseInt(event.extendedProps.meals || 0, 10);
            if (meals > 0 || event.extendedProps.segment) {
                const details = document.createElement('p');
                details.className = 'mt-2 text-xs text-slate-500';
                details.textContent = [
                    meals > 0 ? `${meals} repas` : null,
                    event.extendedProps.segment ? `Segment ${event.extendedProps.segment}` : null,
                ].filter(Boolean).join(' | ');
                row.appendChild(details);
            }

            this.listTarget.appendChild(row);
        });
    }

    renderEvent(arg) {
        const container = document.createElement('div');
        container.className = 'relative pr-5 leading-tight';

        const title = document.createElement('div');
        title.className = 'font-semibold';
        title.textContent = arg.event.title;

        const time = document.createElement('div');
        time.className = 'mt-1 text-[11px] opacity-90';
        time.textContent = `De ${this.formatTime(arg.event.start)} à ${this.formatTime(arg.event.end)}`;

        container.appendChild(title);
        container.appendChild(time);

        if (arg.event.extendedProps.conflict) {
            const warning = document.createElement('div');
            warning.className = 'mt-1 text-[11px] font-semibold';
            warning.textContent = '⚠ Conflit';
            container.appendChild(warning);
        }

        const meals = Number.parseInt(arg.event.extendedProps.meals || 0, 10);
        if (meals > 0) {
            const mealsNode = document.createElement('div');
            mealsNode.className = 'mt-1 text-[11px] opacity-90';
            mealsNode.textContent = `${meals} repas`;
            container.appendChild(mealsNode);
        }

        if (arg.event.extendedProps.segment) {
            const segment = document.createElement('div');
            segment.className = 'mt-1 text-[11px] opacity-80';
            segment.textContent = `Segment ${arg.event.extendedProps.segment}`;
            container.appendChild(segment);
        }

        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'absolute right-0 top-0 inline-flex h-5 w-5 items-center justify-center rounded text-white/90 hover:bg-white/20';
        deleteButton.setAttribute('aria-label', 'Supprimer la garde');
        deleteButton.innerHTML = '<i class="fa-solid fa-xmark text-[11px]"></i>';
        deleteButton.addEventListener('click', (event) => {
            event.stopPropagation();
            this.handleEventDelete(arg.event);
        });

        if (!arg.event.extendedProps.displayOnly) {
            container.appendChild(deleteButton);
        }

        return { domNodes: [container] };
    }

    hiddenInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value || '';

        return input;
    }

    escapeHtml(value) {
        const node = document.createElement('span');
        node.textContent = value || '';

        return node.innerHTML;
    }

    dateWithTime(date, time) {
        const [hours, minutes] = time.split(':').map((part) => Number.parseInt(part, 10));
        const copy = new Date(date);
        copy.setHours(hours, minutes, 0, 0);

        return copy;
    }

    addMinutes(date, minutes) {
        const copy = new Date(date);
        copy.setMinutes(copy.getMinutes() + minutes);

        return copy;
    }

    addDays(date, days) {
        const copy = new Date(date);
        copy.setDate(copy.getDate() + days);

        return copy;
    }

    formatDateTime(date) {
        return `${this.formatDate(date)}T${this.formatTime(date)}:00`;
    }

    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    formatTime(date) {
        const safeDate = date ? new Date(date) : new Date();
        const hours = String(safeDate.getHours()).padStart(2, '0');
        const minutes = String(safeDate.getMinutes()).padStart(2, '0');

        return `${hours}:${minutes}`;
    }

    formatReadableDate(date) {
        return new Intl.DateTimeFormat('fr-FR', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        }).format(date);
    }
}
