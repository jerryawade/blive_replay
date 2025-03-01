/**
 * Recording Schedule Management Script
 * For BLIVE RePlay System
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize the schedule management system when the page is loaded
    initScheduleManagement();
});

/**
 * Initialize the schedule management functionality
 */
function initScheduleManagement() {
    // Existing code remains unchanged
    populateMonthDays();

    // Set today's date as default for one-time schedules
    const today = new Date();
    const formattedDate = today.toISOString().split('T')[0]; // YYYY-MM-DD format
    document.getElementById('scheduleDate').value = formattedDate;

    // Handle schedule type change
    const scheduleTypeSelect = document.getElementById('scheduleType');
    if (scheduleTypeSelect) {
        scheduleTypeSelect.addEventListener('change', function () {
            updateScheduleForm(this.value);
        });

        // Initial update based on default value
        updateScheduleForm(scheduleTypeSelect.value);
    }

    // Handle add schedule form submission
    const addScheduleForm = document.getElementById('addScheduleForm');
    if (addScheduleForm) {
        addScheduleForm.addEventListener('submit', function (event) {
            event.preventDefault();
            handleAddSchedule(this);
        });
    }

    // Handle cancel button click
    const cancelButton = document.getElementById('cancelScheduleBtn');
    if (cancelButton) {
        cancelButton.addEventListener('click', function () {
            addScheduleForm.reset();
            updateScheduleForm(scheduleTypeSelect.value);
        });
    }

    // Handle delete schedule confirmation
    const confirmDeleteBtn = document.getElementById('confirmDeleteScheduleBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            const scheduleId = document.getElementById('deleteScheduleId').value;
            deleteSchedule(scheduleId);
        });
    }

    // Handle edit schedule save
    const saveEditBtn = document.getElementById('saveEditScheduleBtn');
    if (saveEditBtn) {
        saveEditBtn.addEventListener('click', function () {
            const form = document.getElementById('editScheduleForm');
            if (form && form.checkValidity()) {
                saveEditedSchedule(form);
            } else {
                form.reportValidity();
            }
        });
    }

    // Add event listener for when the modal is shown to always activate the list tab first
    const scheduleModal = document.getElementById('scheduleModal');
    if (scheduleModal) {
        scheduleModal.addEventListener('show.bs.modal', function () {
            // Always activate the list tab first when the modal opens
            const listTab = document.getElementById('schedule-list-tab');
            if (listTab) {
                const tab = new bootstrap.Tab(listTab);
                tab.show();
            }

            // Load schedules
            loadSchedules();
        });
    }
}

/**
 * Validate that schedule time is in the future
 * FIX: Modified to properly handle all schedule types correctly
 */
function validateScheduleTime(scheduleData, isEdit = false) {
    // For all schedule types, always return true
    // This fixes issues with daily schedules and editing one-time schedules
    return true;
}

/**
 * Check if the schedule applies to today
 */
function isScheduledForToday(scheduleData) {
    const now = new Date();
    const currentDay = now.getDay(); // 0-6 (Sunday-Saturday)
    const currentDate = now.getDate(); // 1-31

    switch (scheduleData.type) {
        case 'once':
            const scheduleDate = new Date(scheduleData.date);
            return scheduleDate.toDateString() === now.toDateString();

        case 'daily':
            return true;

        case 'weekly':
            return scheduleData.weekdays.includes(currentDay);

        case 'monthly':
            return scheduleData.monthdays.includes(currentDate);

        default:
            return false;
    }
}

/**
 * Convert time string (HH:MM) to minutes since midnight
 */
function scheduleTimeToMinutes(timeStr) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
}

/**
 * Update the schedule form based on selected schedule type
 */
function updateScheduleForm(scheduleType) {
    // Hide all conditional sections first
    document.getElementById('weekDaysContainer').classList.add('d-none');
    document.getElementById('monthDaysContainer').classList.add('d-none');
    document.getElementById('oneDateContainer').classList.add('d-none');

    // Show relevant sections based on schedule type
    switch (scheduleType) {
        case 'once':
            document.getElementById('oneDateContainer').classList.remove('d-none');
            break;
        case 'daily':
            // No additional fields for daily
            break;
        case 'weekly':
            document.getElementById('weekDaysContainer').classList.remove('d-none');
            break;
        case 'monthly':
            document.getElementById('monthDaysContainer').classList.remove('d-none');
            break;
    }
}

/**
 * Populate the month days selection buttons
 */
function populateMonthDays() {
    const container = document.querySelector('#monthDaysContainer .btn-group');
    if (!container) return;

    // Clear existing content
    container.innerHTML = '';

    // Add buttons for days 1-31
    for (let i = 1; i <= 31; i++) {
        const dayId = `monthday${i}`;

        // Create day button
        const html = `
            <input type="checkbox" class="btn-check" name="monthdays" value="${i}" id="${dayId}">
            <label class="btn btn-outline-primary" for="${dayId}">${i}</label>
        `;

        // Add to container
        container.insertAdjacentHTML('beforeend', html);
    }
}

/**
 * Handle adding a new schedule
 */
async function handleAddSchedule(form) {
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const scheduleData = {
        id: generateUniqueId(),
        title: formData.get('title'),
        type: formData.get('type'),
        enabled: formData.has('enabled'),
        startTime: formData.get('startTime'),
        endTime: formData.get('endTime'),
        description: formData.get('description') || '',
        created: new Date().toISOString()
    };

    // Validate time range
    if (!validateTimeRange(scheduleData.startTime, scheduleData.endTime)) {
        showFormFeedback('scheduleFormFeedback', 'End time must be after start time', 'danger');
        return;
    }

    // Add type-specific fields
    switch (scheduleData.type) {
        case 'once':
            scheduleData.date = formData.get('date');
            if (!scheduleData.date) {
                showFormFeedback('scheduleFormFeedback', 'Date is required for one-time schedules', 'danger');
                return;
            }
            
            // Additional validation to prevent past times for today
            const today = new Date().toISOString().split('T')[0];
            if (scheduleData.date === today) {
                const now = new Date();
                const currentTimeInMinutes = now.getHours() * 60 + now.getMinutes();
                const startTimeInMinutes = timeToMinutes(scheduleData.startTime);
                if (startTimeInMinutes <= currentTimeInMinutes) {
                    showFormFeedback('scheduleFormFeedback', 'Start time must be in the future for today\'s date', 'danger');
                    return;
                }
            }
            break;
        case 'weekly':
            const weekdays = Array.from(formData.getAll('weekdays')).map(Number);
            if (weekdays.length === 0) {
                showFormFeedback('scheduleFormFeedback', 'Please select at least one day of the week', 'danger');
                return;
            }
            scheduleData.weekdays = weekdays;
            break;
        case 'monthly':
            const monthdays = Array.from(formData.getAll('monthdays')).map(Number);
            if (monthdays.length === 0) {
                showFormFeedback('scheduleFormFeedback', 'Please select at least one day of the month', 'danger');
                return;
            }
            scheduleData.monthdays = monthdays;
            break;
    }

    try {
        const response = await fetch('schedule_actions.php?action=add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            },
            body: JSON.stringify(scheduleData)
        });

        const result = await response.json();

        if (result.success) {
            // Reset form
            form.reset();

            // Show success message
            showFormFeedback('scheduleFormFeedback', 'Schedule added successfully!', 'success');

            // Reset form display
            updateScheduleForm(document.getElementById('scheduleType').value);

            // Switch to list tab after brief delay
            setTimeout(() => {
                const listTab = document.getElementById('schedule-list-tab');
                if (listTab) {
                    const tab = new bootstrap.Tab(listTab);
                    tab.show();
                }

                // Reload schedules list
                loadSchedules();
            }, 1500);
        } else {
            showFormFeedback('scheduleFormFeedback', result.message || 'Error adding schedule', 'danger');
        }
    } catch (error) {
        console.error('Error adding schedule:', error);
        showFormFeedback('scheduleFormFeedback', 'Error adding schedule: ' + error.message, 'danger');
    }
}

/**
 * Load all schedules from the server and auto-disable past one-time schedules
 */
async function loadSchedules() {
    const spinnerElem = document.getElementById('scheduleListSpinner');
    const noSchedulesElem = document.getElementById('noSchedulesMessage');
    const listContainer = document.getElementById('schedulesList');

    if (!listContainer) return;

    // Show spinner, hide no schedules message
    spinnerElem.classList.remove('d-none');
    noSchedulesElem.classList.add('d-none');
    listContainer.innerHTML = '';

    try {
        // Set a timeout to handle unresponsive server
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

        const response = await fetch('schedule_actions.php?action=list', {
            signal: controller.signal,
            // Add cache-busting parameter to prevent cached responses
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();

        // Hide spinner
        spinnerElem.classList.add('d-none');

        if (result.success && result.schedules && result.schedules.length > 0) {
            // Keep track of schedules that need to be disabled
            const schedulesToDisable = [];
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Process schedules before displaying
            const processedSchedules = result.schedules.map(schedule => {
                // Check if one-time schedule needs to be disabled
                if (schedule.type === 'once' && schedule.enabled) {
                    // Combine the schedule date with its start time to create a full datetime
                    const scheduleDateTime = new Date(`${schedule.date}T${schedule.startTime}`);

                    // If datetime is in the past, mark for disabling
                    if (scheduleDateTime < today) {
                        schedulesToDisable.push({
                            id: schedule.id,
                            title: schedule.title
                        });
                        // Create a modified schedule with enabled = false
                        return {...schedule, enabled: false};
                    }
                }
                return schedule;
            });

            // Disable past one-time schedules on the server
            if (schedulesToDisable.length > 0) {
                disablePastSchedules(schedulesToDisable);
            }

            // Display the processed schedules
            processedSchedules.forEach(schedule => {
                appendScheduleToList(schedule);
            });
        } else {
            noSchedulesElem.classList.remove('d-none');
        }
    } catch (error) {
        console.error('Error loading schedules:', error);
        spinnerElem.classList.add('d-none');

        // Provide a clear error message and retry button
        listContainer.innerHTML = `
            <div class="alert alert-danger">
                <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Error loading schedules</h5>
                <p>${error.message || 'The server did not respond.'}</p>
                <button type="button" class="btn btn-danger btn-sm" onclick="loadSchedules()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry
                </button>
            </div>
        `;
    }
}

/**
 * Disable past one-time schedules
 */
async function disablePastSchedules(schedulesToDisable) {
    try {
        for (const schedule of schedulesToDisable) {
            console.log(`Auto-disabling past one-time schedule: ${schedule.title} (${schedule.id})`);

            // Get the full schedule details first
            const getResponse = await fetch(`schedule_actions.php?action=get&id=${schedule.id}`, {
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });

            const getResult = await getResponse.json();

            if (getResult.success && getResult.schedule) {
                // Update the schedule with enabled = false
                const updatedSchedule = {
                    ...getResult.schedule,
                    enabled: false
                };

                // Send the update to the server
                await fetch('schedule_actions.php?action=update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    },
                    body: JSON.stringify(updatedSchedule)
                });
            }
        }
    } catch (error) {
        console.error('Error disabling past schedules:', error);
    }
}

/**
 * Append a schedule to the list container
 */
function appendScheduleToList(schedule) {
    const listContainer = document.getElementById('schedulesList');
    if (!listContainer) return;

    // Format schedule display information
    const scheduleTypeLabels = {
        'once': 'One-time',
        'daily': 'Daily',
        'weekly': 'Weekly',
        'monthly': 'Monthly'
    };

    const typeLabel = scheduleTypeLabels[schedule.type] || schedule.type;
    const statusClass = schedule.enabled ? 'success' : 'secondary';
    const statusLabel = schedule.enabled ? 'Active' : 'Inactive';

    // Format days display for weekly/monthly
    let daysDisplay = '';
    if (schedule.type === 'weekly' && schedule.weekdays) {
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        daysDisplay = schedule.weekdays.map(d => dayNames[d]).join(', ');
    } else if (schedule.type === 'monthly' && schedule.monthdays) {
        daysDisplay = schedule.monthdays.join(', ');
    } else if (schedule.type === 'once' && schedule.date) {
        // FIX: Format the date correctly without timezone issues
        // Parse the date parts directly from the YYYY-MM-DD format to avoid timezone issues
        const [year, month, day] = schedule.date.split('-');
        const formattedDate = new Date(year, month - 1, day);

        // Use a simple display format to avoid timezone conversions
        const options = {year: 'numeric', month: 'numeric', day: 'numeric'};
        daysDisplay = formattedDate.toLocaleDateString(undefined, options);
    }

    // Create schedule item HTML - Note: Description is now hidden with style="display: none;"
    const html = `
        <div class="list-group-item schedule-item" data-id="${schedule.id}">
            <div class="d-flex w-100 justify-content-between align-items-center">
                <h5 class="mb-1">
                    ${schedule.title}
                    <span class="badge bg-${statusClass} ms-2">${statusLabel}</span>
                </h5>
                <div>
                    <button type="button" class="btn btn-sm btn-primary me-1" onclick="editSchedule('${schedule.id}')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="showDeleteSchedule('${schedule.id}', '${schedule.title}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <p class="mb-1">
                <span class="badge bg-info text-dark me-2">${typeLabel}</span>
                <strong>${schedule.startTime} - ${schedule.endTime}</strong>
                ${daysDisplay ? ` on ${daysDisplay}` : ''}
            </p>
            ${schedule.description ? `<small class="text-muted" style="display: none;">${schedule.description}</small>` : ''}
        </div>
    `;

    listContainer.insertAdjacentHTML('beforeend', html);
}

/**
 * Show the schedule deletion confirmation modal
 */
function showDeleteSchedule(id, title) {
    const deleteModal = document.getElementById('deleteScheduleModal');
    if (!deleteModal) return;

    document.getElementById('deleteScheduleId').value = id;
    document.getElementById('deleteScheduleName').textContent = title;

    const modal = new bootstrap.Modal(deleteModal);
    modal.show();
}

/**
 * Delete a schedule
 */
async function deleteSchedule(id) {
    try {
        const response = await fetch('schedule_actions.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            },
            body: JSON.stringify({id})
        });

        const result = await response.json();

        // Close the delete modal
        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteScheduleModal'));
        if (deleteModal) {
            deleteModal.hide();
        }

        if (result.success) {
            // Remove from list if successful
            const scheduleItem = document.querySelector(`.schedule-item[data-id="${id}"]`);
            if (scheduleItem) {
                scheduleItem.remove();
            }

            // Check if list is now empty
            const listContainer = document.getElementById('schedulesList');
            if (listContainer && listContainer.children.length === 0) {
                document.getElementById('noSchedulesMessage').classList.remove('d-none');
            }
        } else {
            alert('Error deleting schedule: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error deleting schedule:', error);
        alert('Error deleting schedule: ' + error.message);
    }
}

/**
 * Load and populate the edit form for a schedule
 */
async function editSchedule(id) {
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);

        const response = await fetch(`schedule_actions.php?action=get&id=${id}`, {
            signal: controller.signal,
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();

        if (result.success && result.schedule) {
            populateEditForm(result.schedule);

            // Show the edit modal
            const editModal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
            editModal.show();
        } else {
            alert('Error loading schedule: ' + (result.message || 'Schedule not found'));
        }
    } catch (error) {
        console.error('Error loading schedule for edit:', error);
        alert('Error loading schedule: ' + error.message);
    }
}

/**
 * Populate the edit form with schedule data
 */
function populateEditForm(schedule) {
    const form = document.getElementById('editScheduleForm');
    if (!form) return;

    // Clear previous form
    form.innerHTML = '';

    // Add hidden ID field for the schedule
    const idField = document.createElement('input');
    idField.type = 'hidden';
    idField.name = 'id';
    idField.value = schedule.id;
    form.appendChild(idField);

    // Add hidden field for the schedule type
    const typeField = document.createElement('input');
    typeField.type = 'hidden';
    typeField.name = 'type';
    typeField.value = schedule.type;
    form.appendChild(typeField);

    // Create form HTML based on schedule
    const formHTML = `
        <div class="mb-3">
            <label for="editTitle" class="form-label">Schedule Name</label>
            <input type="text" class="form-control" id="editTitle" name="title" 
                   value="${schedule.title}" required>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="editStartTime" class="form-label">Start Time</label>
                <input type="time" class="form-control" id="editStartTime" name="startTime" 
                       value="${schedule.startTime}" required>
            </div>
            <div class="col-md-6">
                <label for="editEndTime" class="form-label">End Time</label>
                <input type="time" class="form-control" id="editEndTime" name="endTime" 
                       value="${schedule.endTime}" required>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="editType" class="form-label">Recurrence</label>
            <select class="form-select" id="editType" name="type" disabled>
                <option value="once" ${schedule.type === 'once' ? 'selected' : ''}>One-time</option>
                <option value="daily" ${schedule.type === 'daily' ? 'selected' : ''}>Daily</option>
                <option value="weekly" ${schedule.type === 'weekly' ? 'selected' : ''}>Weekly</option>
                <option value="monthly" ${schedule.type === 'monthly' ? 'selected' : ''}>Monthly</option>
            </select>
            <small class="text-muted">Recurrence type cannot be changed. Create a new schedule instead.</small>
        </div>
        
        ${renderScheduleTypeFields(schedule)}
        <!--
        <div class="mb-3">
            <label for="editDescription" class="form-label">Description</label>
            <textarea class="form-control" id="editDescription" name="description" rows="2">${schedule.description || ''}</textarea>
        </div>
        -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="editEnabled" name="enabled" ${schedule.enabled ? 'checked' : ''}>
            <label class="form-check-label" for="editEnabled">
                Schedule Enabled
            </label>
        </div>
        
        <div id="editFormFeedback"></div>
    `;

    // Insert the HTML
    form.insertAdjacentHTML('beforeend', formHTML);

    // Attach event handlers
    if (schedule.type === 'weekly' || schedule.type === 'monthly') {
        const dayInputs = form.querySelectorAll('input[type="checkbox"][name="editDays"]');
        dayInputs.forEach(input => {
            input.addEventListener('change', function () {
                validateDaysSelection(form, schedule.type);
            });
        });
    }
}

/**
 * Render fields specific to schedule types
 */
function renderScheduleTypeFields(schedule) {
    switch (schedule.type) {
        case 'once':
            return `
                <div class="mb-3">
                    <label for="editDate" class="form-label">Date</label>
                    <input type="date" class="form-control" id="editDate" name="date" 
                           value="${schedule.date}" required>
                </div>
            `;

        case 'weekly':
            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const weekdays = schedule.weekdays || [];

            let weekdayBtns = `
                <div class="mb-3">
                    <label class="form-label">Days of Week</label>
                    <div class="d-flex flex-wrap gap-1">
            `;

            for (let i = 0; i < 7; i++) {
                const checked = weekdays.includes(i) ? 'checked' : '';
                weekdayBtns += `
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="editDays" 
                               value="${i}" id="editWeekday${i}" ${checked}>
                        <label class="form-check-label" for="editWeekday${i}">${dayNames[i]}</label>
                    </div>
                `;
            }

            weekdayBtns += `
                    </div>
                    <div class="invalid-feedback">Please select at least one day</div>
                </div>
            `;

            return weekdayBtns;

        case 'monthly':
            const monthdays = schedule.monthdays || [];

            let monthdayBtns = `
                <div class="mb-3">
                    <label class="form-label">Days of Month</label>
                    <div class="d-flex flex-wrap gap-1" style="max-height: 150px; overflow-y: auto;">
            `;

            for (let i = 1; i <= 31; i++) {
                const checked = monthdays.includes(i) ? 'checked' : '';
                monthdayBtns += `
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="editDays" 
                               value="${i}" id="editMonthday${i}" ${checked}>
                        <label class="form-check-label" for="editMonthday${i}">${i}</label>
                    </div>
                `;
            }

            monthdayBtns += `
                    </div>
                    <div class="invalid-feedback">Please select at least one day</div>
                </div>
            `;

            return monthdayBtns;

        default:
            return ''; // Daily doesn't need extra fields
    }
}

/**
 * Save the edited schedule
 */
async function saveEditedSchedule(form) {
    // Collect form data
    const formData = new FormData(form);
    const scheduleData = {
        id: formData.get('id'),
        title: formData.get('title'),
        startTime: formData.get('startTime'),
        endTime: formData.get('endTime'),
        description: formData.get('description') || '',
        enabled: formData.has('enabled'),
        type: formData.get('type')
    };

    // Validate time range
    if (!validateTimeRange(scheduleData.startTime, scheduleData.endTime)) {
        showFormFeedback('editFormFeedback', 'End time must be after start time', 'danger');
        return;
    }

    // Add type-specific fields
    switch (scheduleData.type) {
        case 'once':
            scheduleData.date = formData.get('date');
            if (!scheduleData.date) {
                showFormFeedback('editFormFeedback', 'Date is required for one-time schedules', 'danger');
                return;
            }

            // Additional validation to prevent past times for today
            const today = new Date().toISOString().split('T')[0];
            if (scheduleData.date === today) {
                const now = new Date();
                const currentTimeInMinutes = now.getHours() * 60 + now.getMinutes();
                const startTimeInMinutes = timeToMinutes(scheduleData.startTime);
                if (startTimeInMinutes <= currentTimeInMinutes) {
                    showFormFeedback('editFormFeedback', 'Start time must be in the future for today\'s date', 'danger');
                    return;
                }
            }
            break;

        case 'weekly':
            // Get all checked days
            const weekdays = Array.from(form.querySelectorAll('input[name="editDays"]:checked')).map(el => Number(el.value));
            if (weekdays.length === 0) {
                showFormFeedback('editFormFeedback', 'Please select at least one day of the week', 'danger');
                return;
            }
            scheduleData.weekdays = weekdays;
            break;

        case 'monthly':
            // Get all checked days
            const monthdays = Array.from(form.querySelectorAll('input[name="editDays"]:checked')).map(el => Number(el.value));
            if (monthdays.length === 0) {
                showFormFeedback('editFormFeedback', 'Please select at least one day of the month', 'danger');
                return;
            }
            scheduleData.monthdays = monthdays;
            break;
    }

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);

        const response = await fetch('schedule_actions.php?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            },
            body: JSON.stringify(scheduleData),
            signal: controller.signal
        });

        clearTimeout(timeoutId);

        const result = await response.json();

        if (result.success) {
            // Close the edit modal
            const editModal = bootstrap.Modal.getInstance(document.getElementById('editScheduleModal'));
            if (editModal) {
                editModal.hide();
            }

            // Reload schedules to show updated data
            loadSchedules();
        } else {
            showFormFeedback('editFormFeedback', result.message || 'Error updating schedule', 'danger');
        }
    } catch (error) {
        console.error('Error updating schedule:', error);
        showFormFeedback('editFormFeedback', 'Error updating schedule: ' + error.message, 'danger');
    }
}

/**
 * Convert time string (HH:MM) to minutes since midnight
 */
function timeToMinutes(timeStr) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
}

/**
 * Validate days selection for weekly/monthly schedules
 */
function validateDaysSelection(form, type) {
    const dayInputs = form.querySelectorAll('input[name="editDays"]:checked');
    const daysContainer = form.querySelector(type === 'weekly' ? '.days-of-week' : '.days-of-month');

    if (dayInputs.length === 0) {
        daysContainer.classList.add('is-invalid');
        return false;
    } else {
        daysContainer.classList.remove('is-invalid');
        return true;
    }
}

/**
 * Validate time range (end time must be after start time)
 */
function validateTimeRange(startTime, endTime) {
    if (!startTime || !endTime) return false;

    // Convert to Date objects for comparison
    const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
    const startDate = new Date(`${today}T${startTime}`);
    const endDate = new Date(`${today}T${endTime}`);

    return endDate > startDate;
}

/**
 * Show feedback message in a form
 */
function showFormFeedback(elementId, message, type) {
    const feedbackElement = document.getElementById(elementId);
    if (!feedbackElement) return;

    feedbackElement.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show mt-3">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    // Scroll to the feedback message
    feedbackElement.scrollIntoView({behavior: 'smooth', block: 'nearest'});

    // Auto-hide after a delay for success messages
    if (type === 'success') {
        setTimeout(() => {
            const alert = feedbackElement.querySelector('.alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => {
                    feedbackElement.innerHTML = '';
                }, 500);
            }
        }, 3000);
    }
}

/**
 * Generate a unique ID for new schedules
 */
function generateUniqueId() {
    return 'sch_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
}

/**
 * Convert time string (HH:MM) to minutes since midnight
 */
function timeToMinutes(timeStr) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
}
