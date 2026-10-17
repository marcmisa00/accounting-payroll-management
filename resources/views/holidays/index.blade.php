@extends('layouts.app')

@section('title', 'Holiday Calendar')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">

<style>
    /* ---------- HOLIDAY PAGE – LIGHT/DARK READY ---------- */
    .holiday-page {
        padding: 24px;
        color: var(--text-primary);
        transition: color 0.25s;
    }

    .holiday-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .holiday-heading h1 {
        font-size: 26px;
        font-weight: 700;
        margin: 0 0 6px;
        letter-spacing: -0.3px;
    }

    .holiday-heading p {
        color: var(--text-secondary);
        margin: 0;
        font-size: 14px;
        opacity: 0.9;
    }

    /* Panel */
    .holiday-panel {
        background: var(--card-bg);
        border: 1px solid var(--border-light);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--card-shadow);
        transition: background 0.25s, border-color 0.25s, box-shadow 0.25s;
    }

    #holidayCalendar {
        min-height: 650px;
    }

    /* Legend */
    .holiday-legend {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin: 0 0 20px;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .holiday-legend span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
    }

    .holiday-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 2px var(--card-bg);
    }

    /* Buttons */
    .holiday-btn {
        border: none;
        border-radius: 10px;
        padding: 10px 20px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .holiday-btn:active {
        transform: scale(0.97);
    }

    .holiday-btn-primary {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
    }

    .holiday-btn-primary:hover {
        background: #1d4ed8;
    }

    .holiday-btn-secondary {
        background: var(--toggle-hover);
        color: var(--text-primary);
        border: 1px solid var(--border-light);
    }

    .holiday-btn-secondary:hover {
        background: var(--toggle-border);
    }

    .holiday-btn-danger {
        background: #dc2626;
        color: #fff;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
    }

    .holiday-btn-danger:hover {
        background: #b91c1c;
    }

    /* Modal */
    .holiday-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(0, 0, 0, 0.55);
        backdrop-filter: blur(4px);
        padding: 20px;
        align-items: center;
        justify-content: center;
    }

    .holiday-modal.open {
        display: flex;
    }

    .holiday-modal-card {
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        background: var(--card-bg);
        color: var(--text-primary);
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
        border: 1px solid var(--border-light);
        transition: background 0.25s, color 0.25s, border-color 0.25s;
    }

    .holiday-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 22px;
    }

    .holiday-modal-header h2 {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.2px;
    }

    .holiday-close {
        background: transparent;
        border: none;
        font-size: 26px;
        line-height: 1;
        cursor: pointer;
        color: var(--text-secondary);
        padding: 0 4px;
        transition: color 0.15s;
    }

    .holiday-close:hover {
        color: var(--text-primary);
    }

    /* Fields */
    .holiday-field {
        margin-bottom: 18px;
    }

    .holiday-field label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 7px;
        color: var(--text-secondary);
        letter-spacing: 0.2px;
    }

    .holiday-field input,
    .holiday-field select {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 14px;
        border: 1px solid var(--border-light);
        background: var(--toggle-hover);
        color: var(--text-primary);
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        transition: border-color 0.15s, background 0.2s, color 0.2s;
    }

    .holiday-field input:focus,
    .holiday-field select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }

    .holiday-field input::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }

    /* Actions */
    .holiday-modal-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        flex-wrap: wrap;
        margin-top: 26px;
    }

    /* Messages */
    #holidayMessage {
        display: none;
        padding: 14px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 500;
        border: 1px solid transparent;
        transition: background 0.2s, color 0.2s;
    }

    #holidayMessage.error {
        display: block;
        background: rgba(220, 38, 38, 0.12);
        color: #ef4444;
        border-color: rgba(220, 38, 38, 0.25);
    }

    #holidayMessage.success {
        display: block;
        background: rgba(34, 197, 94, 0.12);
        color: #22c55e;
        border-color: rgba(34, 197, 94, 0.25);
    }

    /* ---------- FullCalendar DARK MODE OVERRIDES ---------- */
    html.dark-mode .fc {
        --fc-border-color: rgba(255, 255, 255, 0.08);
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: rgba(255, 255, 255, 0.03);
        --fc-list-event-hover-bg-color: rgba(255, 255, 255, 0.05);
        --fc-today-bg-color: rgba(37, 99, 235, 0.1);
        color: var(--text-primary);
    }

    html.dark-mode .fc .fc-toolbar-title {
        color: var(--text-primary);
    }

    html.dark-mode .fc .fc-button {
        background: var(--toggle-hover);
        border-color: var(--border-light);
        color: var(--text-primary);
        text-transform: capitalize;
        font-weight: 500;
        box-shadow: none;
    }

    html.dark-mode .fc .fc-button:hover {
        background: var(--toggle-border);
        color: var(--text-primary);
    }

    html.dark-mode .fc .fc-button-primary:not(:disabled).fc-button-active,
    html.dark-mode .fc .fc-button-primary:not(:disabled):active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }

    html.dark-mode .fc .fc-col-header-cell-cushion,
    html.dark-mode .fc .fc-daygrid-day-number {
        color: var(--text-secondary);
        text-decoration: none;
    }

    html.dark-mode .fc .fc-daygrid-day.fc-day-today {
        background: rgba(37, 99, 235, 0.15);
    }

    html.dark-mode .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
        background: #2563eb;
        color: #fff;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 2px;
    }

    html.dark-mode .fc-theme-standard td,
    html.dark-mode .fc-theme-standard th {
        border-color: var(--border-light);
    }

    html.dark-mode .fc-theme-standard .fc-scrollgrid {
        border-color: var(--border-light);
    }

    html.dark-mode .fc .fc-daygrid-event {
        border-radius: 6px;
        padding: 2px 4px;
    }

    html.dark-mode .fc-event {
        filter: brightness(1.1);
    }

    /* Light mode tweaks */
    .fc .fc-toolbar-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .fc .fc-button {
        text-transform: capitalize;
        font-weight: 500;
        border-radius: 8px;
        padding: 7px 14px;
    }

    .fc .fc-button-primary {
        background: var(--card-bg);
        border-color: var(--border-light);
        color: var(--text-primary);
    }

    .fc .fc-button-primary:hover {
        background: var(--toggle-hover);
        border-color: var(--toggle-border);
        color: var(--text-primary);
    }

    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }

    .fc .fc-col-header-cell-cushion,
    .fc .fc-daygrid-day-number {
        color: var(--text-secondary);
        text-decoration: none;
        font-weight: 500;
    }

    .fc .fc-daygrid-day.fc-day-today {
        background: rgba(37, 99, 235, 0.06);
    }

    .fc-theme-standard td,
    .fc-theme-standard th {
        border-color: var(--border-light);
    }

    .fc-theme-standard .fc-scrollgrid {
        border-color: var(--border-light);
    }

    .fc-event {
        border-radius: 6px;
        font-size: 12px;
        padding: 1px 4px;
        border: none;
    }

    @media (max-width: 640px) {
        .holiday-page {
            padding: 12px;
        }
        .holiday-panel {
            padding: 12px;
        }
        #holidayCalendar {
            min-height: 500px;
        }
        .fc .fc-toolbar {
            flex-wrap: wrap;
            gap: 8px;
        }
        .fc .fc-toolbar-title {
            font-size: 17px;
        }
        .fc .fc-button {
            padding: 5px 10px;
            font-size: 12px;
        }
        .holiday-modal-card {
            padding: 20px;
        }
        .holiday-modal-actions {
            flex-direction: column-reverse;
        }
        .holiday-modal-actions .holiday-btn {
            width: 100%;
        }
    }
</style>

<div class="holiday-page">
    <div class="holiday-heading">
        <div>
            <h1>Holiday Calendar</h1>
            <p>Click a date to add a holiday, or click an existing holiday to manage it.</p>
        </div>

        <button type="button"
                class="holiday-btn holiday-btn-primary"
                id="addHolidayButton">
            <i class="fa-solid fa-plus"></i> Add Holiday
        </button>
    </div>

    <div class="holiday-panel">
        <div class="holiday-legend">
            <span><i class="holiday-dot" style="background:#dc3545"></i> Regular Holiday</span>
            <span><i class="holiday-dot" style="background:#0d6efd"></i> Special Non-Working</span>
            <span><i class="holiday-dot" style="background:#6f42c1"></i> Office Closure</span>
        </div>

        <div id="holidayCalendar"></div>
    </div>
</div>

<!-- Modal -->
<div class="holiday-modal" id="holidayModal" aria-hidden="true">
    <div class="holiday-modal-card" role="dialog"
         aria-modal="true" aria-labelledby="holidayModalTitle">

        <div class="holiday-modal-header">
            <h2 id="holidayModalTitle">Add Holiday</h2>
            <button type="button" class="holiday-close"
                    id="closeHolidayModal" aria-label="Close">&times;</button>
        </div>

        <div id="holidayMessage" role="alert"></div>

        <form id="holidayForm">
            <input type="hidden" id="holidayId">

            <div class="holiday-field">
                <label for="holidayDate">Date</label>
                <input type="date" id="holidayDate" required>
            </div>

            <div class="holiday-field">
                <label for="holidayType">Holiday Type</label>
                <select id="holidayType" required>
                    <option value="rh">Regular Holiday</option>
                    <option value="snwh">Special Non-Working Holiday</option>
                    <option value="close">Office Closure</option>
                </select>
            </div>

            <div class="holiday-field">
                <label for="holidayLocation">Location</label>
                <select id="holidayLocation" required>
                    <option value="allbranch">All Branches</option>
                    <option value="Davao">Davao City</option>
                    <option value="Iloilo">Iloilo City</option>
                    <option value="Kidapawan">Kidapawan City</option>
                    <option value="Panabo">Panabo City</option>
                    <option value="Digos">Digos City</option>
                    <option value="Koronadal">Koronadal City</option>
                </select>
            </div>

            <div class="holiday-field">
                <label for="holidayDescription">Title / Description</label>
                <input type="text" id="holidayDescription"
                       maxlength="255"
                       placeholder="e.g. Independence Day" required>
            </div>

            <div class="holiday-modal-actions">
                <button type="button" class="holiday-btn holiday-btn-danger"
                        id="deleteHolidayButton" hidden>
                    Delete
                </button>

                <button type="button"
                        class="holiday-btn holiday-btn-secondary"
                        id="cancelHolidayButton">
                    Cancel
                </button>

                <button type="submit"
                        class="holiday-btn holiday-btn-primary"
                        id="saveHolidayButton">
                    Save Holiday
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('holidayModal');
    const form = document.getElementById('holidayForm');
    const message = document.getElementById('holidayMessage');
    const deleteButton = document.getElementById('deleteHolidayButton');
    const saveButton = document.getElementById('saveHolidayButton');

    const urls = {
        events: @json(route('holidays.events')),
        store: @json(route('holidays.store')),
        update: @json(url('/holidays')),
        destroy: @json(url('/holidays')),
    };

    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    let calendar;

    function showMessage(text, isError = false) {
        message.textContent = text;
        message.className = isError ? 'error' : 'success';
    }

    function clearMessage() {
        message.textContent = '';
        message.className = '';
    }

    function openModal(date = '', holiday = null) {
        form.reset();
        clearMessage();

        document.getElementById('holidayId').value = holiday?.id || '';
        document.getElementById('holidayDate').value =
            date || holiday?.startStr?.slice(0, 10) || '';

        document.getElementById('holidayType').value =
            holiday?.extendedProps?.type || 'rh';

        document.getElementById('holidayLocation').value =
            holiday?.extendedProps?.location || 'allbranch';

        document.getElementById('holidayDescription').value =
            holiday?.extendedProps?.description || holiday?.title || '';

        document.getElementById('holidayModalTitle').textContent =
            holiday ? 'Edit Holiday' : 'Add Holiday';

        deleteButton.hidden = !holiday;
        saveButton.textContent = holiday ? 'Save Changes' : 'Save Holiday';

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('holidayDescription').focus();
    }

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        clearMessage();
    }

    async function sendRequest(url, method, data = null) {
        const options = {
            method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
        };

        if (data) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            const errors = result.errors
                ? Object.values(result.errors).flat().join(' ')
                : result.message;

            throw new Error(errors || 'The request failed. Please try again.');
        }

        return result;
    }

    calendar = new FullCalendar.Calendar(
        document.getElementById('holidayCalendar'),
        {
            initialView: 'dayGridMonth',
            height: 'auto',
            firstDay: 0,
            selectable: true,
            dayMaxEvents: 3,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day'
            },
            events: {
                url: urls.events,
                failure: function () {
                    alert('Unable to load holidays. Check your connection and Laravel logs.');
                }
            },
            dateClick: function (info) {
                openModal(info.dateStr.slice(0, 10));
            },
            eventClick: function (info) {
                openModal('', info.event);
            },
            eventDidMount: function (info) {
                info.el.title =
                    info.event.extendedProps.location + ' — ' +
                    info.event.extendedProps.description;
            }
        }
    );

    calendar.render();

    document.getElementById('addHolidayButton').addEventListener('click', function () {
        openModal(calendar.getDate().toISOString().slice(0, 10));
    });

    document.getElementById('closeHolidayModal').addEventListener('click', closeModal);
    document.getElementById('cancelHolidayButton').addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearMessage();
        saveButton.disabled = true;

        const id = document.getElementById('holidayId').value;

        const data = {
            date: document.getElementById('holidayDate').value,
            type: document.getElementById('holidayType').value,
            location: document.getElementById('holidayLocation').value,
            description: document.getElementById('holidayDescription').value.trim(),
        };

        try {
            const url = id ? `${urls.update}/${id}` : urls.store;
            await sendRequest(url, id ? 'PUT' : 'POST', data);

            closeModal();
            calendar.refetchEvents();
            alert(id ? 'Holiday updated successfully.' : 'Holiday added successfully.');
        } catch (error) {
            showMessage(error.message, true);
        } finally {
            saveButton.disabled = false;
        }
    });

    deleteButton.addEventListener('click', async function () {
        const id = document.getElementById('holidayId').value;

        if (!id || !confirm(
            'Delete this holiday? Payroll may depend on historical holiday records.'
        )) {
            return;
        }

        deleteButton.disabled = true;

        try {
            await sendRequest(`${urls.destroy}/${id}`, 'DELETE');
            closeModal();
            calendar.refetchEvents();
            alert('Holiday deleted successfully.');
        } catch (error) {
            showMessage(error.message, true);
        } finally {
            deleteButton.disabled = false;
        }
    });
});
</script>
@endsection