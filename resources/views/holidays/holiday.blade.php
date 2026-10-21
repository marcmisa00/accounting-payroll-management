@extends('layouts.app')

@section('title', 'Holiday Calendar')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
@vite('resources/css/holiday-cal.css')
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

    <div class="holiday-layout">
        <aside class="holiday-sidebar">
            <h2 class="holiday-sidebar-title">
                Holidays <span id="holidayListYear"></span>
            </h2>
            <div id="holidayList" class="holiday-list">
                <p class="holiday-list-empty">Loading…</p>
            </div>
        </aside>

        <div class="holiday-panel">
            <div class="holiday-legend">
                <span><i class="holiday-dot" style="background:#dc3545"></i> Regular Holiday</span>
                <span><i class="holiday-dot" style="background:#0d6efd"></i> Special Non-Working</span>
                <span><i class="holiday-dot" style="background:#6f42c1"></i> Office Closure</span>
            </div>

        <div id="holidayCalendar"></div>
    </div>
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

    const currentYear = new Date().getFullYear();
    const typeColors = { rh: '#dc3545', snwh: '#0d6efd', close: '#6f42c1' };
    const typeLabels = {
        rh: 'Regular Holiday',
        snwh: 'Special Non-Working',
        close: 'Office Closure',
    };
    const listEl = document.getElementById('holidayList');
    document.getElementById('holidayListYear').textContent = currentYear;

    function normalize(raw) {
        const props = raw.extendedProps || raw;
        return {
            id: raw.id,
            title: raw.title,
            startStr: String(raw.start || raw.date || '').slice(0, 10),
            extendedProps: {
                type: props.type,
                location: props.location,
                description: props.description,
            },
        };
    }

    async function loadHolidayList() {
        try {
            const url = new URL(urls.events, window.location.origin);
            url.searchParams.set('start', `${currentYear}-01-01`);
            url.searchParams.set('end', `${currentYear + 1}-01-01`);

            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error();

            const holidays = (await response.json())
                .map(normalize)
                .filter(h => h.startStr.startsWith(String(currentYear)))
                .sort((a, b) => a.startStr.localeCompare(b.startStr));

            renderHolidayList(holidays);
        } catch (e) {
            listEl.innerHTML = '<p class="holiday-list-empty">Unable to load holidays.</p>';
        }
    }

    function renderHolidayList(holidays) {
        listEl.innerHTML = '';

        if (!holidays.length) {
            listEl.innerHTML = '<p class="holiday-list-empty">No holidays for this year.</p>';
            return;
        }

        let lastMonth = null;

        holidays.forEach(h => {
            const date = new Date(h.startStr + 'T00:00:00');
            const month = date.toLocaleString('en-US', { month: 'long' });

            if (month !== lastMonth) {
                const heading = document.createElement('div');
                heading.className = 'holiday-list-month';
                heading.textContent = month;
                listEl.appendChild(heading);
                lastMonth = month;
            }

            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'holiday-list-item';

            const dot = document.createElement('i');
            dot.className = 'holiday-dot';
            dot.style.background = typeColors[h.extendedProps.type] || '#6c757d';

            const body = document.createElement('div');

            const dateEl = document.createElement('div');
            dateEl.className = 'holiday-list-date';
            dateEl.textContent = date.toLocaleDateString('en-US', {
                weekday: 'short', month: 'short', day: 'numeric',
            });

            const nameEl = document.createElement('div');
            nameEl.className = 'holiday-list-name';
            nameEl.textContent = h.extendedProps.description || h.title;

            const metaEl = document.createElement('div');
            metaEl.className = 'holiday-list-meta';
            metaEl.textContent =
                (typeLabels[h.extendedProps.type] || '') + ' · ' +
                (h.extendedProps.location === 'allbranch' ? 'All Branches' : h.extendedProps.location);

            body.append(dateEl, nameEl, metaEl);
            item.append(dot, body);

            item.addEventListener('click', function () {
                calendar.gotoDate(h.startStr);
                openModal('', h);
            });

            listEl.appendChild(item);
        });
    }

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

    loadHolidayList();

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
            loadHolidayList();
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
            loadHolidayList();
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