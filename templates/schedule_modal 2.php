<!-- Recording Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">
                    <i class="bi bi-calendar-event me-2"></i>
                    Scheduled Recordings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="height: calc(100vh - 250px); overflow-y: auto;">
                <ul class="nav nav-tabs mb-3" id="scheduleTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="schedule-list-tab" data-bs-toggle="tab" 
                                data-bs-target="#schedule-list" type="button" role="tab">
                            <i class="bi bi-list-ul me-1"></i> Schedules
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="add-schedule-tab" data-bs-toggle="tab" 
                                data-bs-target="#add-schedule" type="button" role="tab">
                            <i class="bi bi-plus-circle me-1"></i> Add New
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- Schedules List Tab -->
                    <div class="tab-pane fade show active" id="schedule-list" role="tabpanel">
                        <div id="scheduleListSpinner" class="text-center p-4 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        
                        <div id="noSchedulesMessage" class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No recording schedules have been created yet.
                        </div>
                        
                        <div id="schedulesList" class="list-group">
                            <!-- Schedules will be loaded here -->
                        </div>
                    </div>
                    
                    <!-- Add Schedule Tab -->
                    <div class="tab-pane fade" id="add-schedule" role="tabpanel">
                        <form id="addScheduleForm">
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label for="scheduleTitle" class="form-label">Schedule Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-tag"></i>
                                        </span>
                                        <input type="text" class="form-control" id="scheduleTitle" name="title"
                                               placeholder="E.g., Sunday Service, Daily Backup" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="scheduleType" class="form-label">Recurrence</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </span>
                                        <select class="form-select" id="scheduleType" name="type" required>
                                            <option value="once">One-time</option>
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="scheduleEnabled" class="form-label">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="scheduleEnabled" name="enabled" checked>
                                        <label class="form-check-label" for="scheduleEnabled">
                                            Schedule Enabled
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Select days (for weekly) -->
                            <div id="weekDaysContainer" class="mb-3 d-none">
                                <label class="form-label">Days of Week</label>
                                <div class="btn-group d-flex flex-wrap" role="group">
                                    <input type="checkbox" class="btn-check" name="weekdays" value="0" id="weekday0">
                                    <label class="btn btn-outline-primary" for="weekday0">Sun</label>
                                    
                                    <input type="checkbox" class="btn-check" name="weekdays" value="1" id="weekday1">
                                    <label class="btn btn-outline-primary" for="weekday1">Mon</label>
                                    
                                    <input type="checkbox" class="btn-check" name="weekdays" value="2" id="weekday2">
                                    <label class="btn btn-outline-primary" for="weekday2">Tue</label>
                                    
                                    <input type="checkbox" class="btn-check" name="weekdays" value="3" id="weekday3">
                                    <label class="btn btn-outline-primary" for="weekday3">Wed</label>
                                    
                                    <input type="checkbox" class="btn-check" name="weekdays" value="4" id="weekday4">
                                    <label class="btn btn-outline-primary" for="weekday4">Thu</label>
                                    
                                    <input type="checkbox" class="btn-check" name="weekdays" value="5" id="weekday5">
                                    <label class="btn btn-outline-primary" for="weekday5">Fri</label>
                                    
                                    <input type="checkbox" class="btn-check" name="weekdays" value="6" id="weekday6">
                                    <label class="btn btn-outline-primary" for="weekday6">Sat</label>
                                </div>
                            </div>
                            
                            <!-- Select days (for monthly) -->
                            <div id="monthDaysContainer" class="mb-3 d-none">
                                <label class="form-label">Days of Month</label>
                                <div class="btn-group d-flex flex-wrap" role="group" style="gap: 3px;">
                                    <!-- Will be populated with JS -->
                                </div>
                            </div>
                            
                            <!-- One-time date picker -->
                            <div id="oneDateContainer" class="mb-3 d-none">
                                <label for="scheduleDate" class="form-label">Date</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-calendar"></i>
                                    </span>
                                    <input type="date" class="form-control" id="scheduleDate" name="date">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="startTime" class="form-label">Start Time</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-clock"></i>
                                        </span>
                                        <input type="time" class="form-control" id="startTime" name="startTime" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="endTime" class="form-label">End Time</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-clock-history"></i>
                                        </span>
                                        <input type="time" class="form-control" id="endTime" name="endTime" required>
                                    </div>
                                    <small class="text-muted">Must be after start time</small>
                                </div>
                            </div>
                            <!--
                            <div class="mb-3">
                                <label for="scheduleDescription" class="form-label">Description (Optional)</label>
                                <textarea class="form-control" id="scheduleDescription" name="description" rows="2"></textarea>
                            </div>
                            -->
                            <div id="scheduleFormFeedback"></div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary icon-btn me-2" id="cancelScheduleBtn">
                                    <i class="bi bi-eraser"></i>
                                    Clear
                                </button>
                                <button type="submit" class="btn btn-primary icon-btn">
                                    <i class="bi bi-calendar-plus"></i>
                                    Save Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editScheduleModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>
                    Edit Schedule
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editScheduleForm">
                    <input type="hidden" id="editScheduleId" name="id">
                    <!-- Form will be populated dynamically with JS -->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary icon-btn" id="saveEditScheduleBtn">
                    <i class="bi bi-save"></i>
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Schedule Confirmation Modal -->
<div class="modal fade" id="deleteScheduleModal" tabindex="-1" aria-labelledby="deleteScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteScheduleModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deleteScheduleId">
                <p>Are you sure you want to delete the schedule: <strong id="deleteScheduleName"></strong>?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-danger icon-btn" id="confirmDeleteScheduleBtn">
                    <i class="bi bi-trash"></i>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
