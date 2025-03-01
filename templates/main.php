<?php
// Get recording note function
function getRecordingNote($fileName)
{
    $notesFile = 'recording_notes.json';
    if (file_exists($notesFile)) {
        $notes = json_decode(file_get_contents($notesFile), true) ?? [];
        $baseFileName = basename($fileName);

        // Clean up notes for deleted files
        $updatedNotes = [];
        foreach ($notes as $fileKey => $note) {
            $fullFilePath = 'recordings/' . $fileKey;
            if (file_exists($fullFilePath)) {
                $updatedNotes[$fileKey] = $note;
            }
        }

        // If notes have changed, save the updated list
        if (count($updatedNotes) !== count($notes)) {
            file_put_contents($notesFile, json_encode($updatedNotes));
        }

        return $updatedNotes[$baseFileName] ?? '';
    }
    return '';
}

if (isAdmin()): ?>
    <div id="recordingStatus" class="recording-status <?php echo $recordingActive ? 'recording-active' : 'recording-inactive'; ?>">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <?php if ($recordingActive): ?>
                <div class="me-2">⚫</div> <span class="status-text">Recording in Progress (DO NOT refresh your browser!)</span>
            <?php else: ?>
                <span class="status-text">Recording Stopped</span>
            <?php endif; ?>
        </div>
        <?php if ($recordingActive): ?>
            <div id="recordingTimer">00:00:00</div>
        <?php endif; ?>
    </div>

    <form method="post" class="mt-3">
        <button type="submit" name="start"
                class="btn btn-success icon-btn" <?php echo $recordingActive ? 'disabled' : ''; ?>>
            <i class="bi bi-record-circle"></i>
            Start Recording
        </button>
        <button type="submit" name="stop"
                class="btn btn-danger icon-btn" <?php echo !$recordingActive ? 'disabled' : ''; ?>>
            <i class="bi bi-stop-circle"></i>
            Stop Recording
        </button>
        &nbsp;
        <!-- Add this button for the scheduler -->
        <?php if (isset($settings['enable_scheduler']) && $settings['enable_scheduler']): ?>
            <button type="button" class="btn btn-info icon-btn" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                <i class="bi bi-calendar"></i>
                Schedules
            </button>
        <?php endif; ?>
    </form>
<?php endif; ?>

<?php if (isAuthenticated()): ?>
    <h2 class="mt-4">Recordings:</h2>
    <div class="list-group">
        <?php if (!empty($recordings)): ?>
            <?php
            $currentRecording = file_exists('current_recording.txt') ? file_get_contents('current_recording.txt') : '';
            foreach ($recordings as $file):
                $fileName = basename($file);
                $thumbnailFile = $thumbnailsDir . '/' . pathinfo($fileName, PATHINFO_FILENAME) . '.jpg';
                generateThumbnail($file, $thumbnailFile);
                $fileSize = filesize($file);
                $fileSizeFormatted = $fileSize > 1024 * 1024 * 1024
                    ? number_format($fileSize / (1024 * 1024 * 1024), 2) . ' GB'
                    : number_format($fileSize / (1024 * 1024), 2) . ' MB';
                $fileDate = date('Y-m-d H:i:s', filemtime($file));
                $isCurrentlyRecording = ($file === $currentRecording);
                ?>
                <div class="list-group-item">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <div class="thumbnail-container">
                            <img src="<?php
                            echo $isCurrentlyRecording
                                ? 'assets/imgs/recording.png'
                                : (file_exists($thumbnailFile)
                                    ? $thumbnailFile . '?t=' . filemtime($thumbnailFile)
                                    : 'default-thumbnail.jpg'); ?>"
                                 alt="<?php echo $isCurrentlyRecording ? 'Recording in Progress' : 'Thumbnail'; ?>">
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1">
                                <?php echo htmlspecialchars($fileName); ?>
                                <?php if ($isCurrentlyRecording): ?>
                                    <span class="badge bg-danger">Recording in Progress</span>
                                <?php endif; ?>
                            </h5>
                            <p class="mb-1">Size: <?php echo $fileSizeFormatted; ?></p>
                            <small class="text-muted">
                                Recorded: <?php echo $fileDate; ?><br>
                                Duration: <?php echo $isCurrentlyRecording ? 'Recording...' : getVideoDuration($file); ?>
                                <?php
                                $note = getRecordingNote($file);
                                if (!empty($note)): ?>
                                    <br>Note: <span class="recording-note"><?php echo htmlspecialchars($note); ?></span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                            <!-- Only show Add/Edit Note button if not recording -->
                            <?php if (!$isCurrentlyRecording && isAdmin() && !$recordingActive): ?>
                                <button class="btn btn-info btn-sm icon-btn me-2"
                                        onclick="toggleNoteForm('<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $fileName); ?>')"
                                        type="button">
                                    <i class="bi bi-pencil-square"></i>
                                    <?php echo empty(getRecordingNote($file)) ? 'Add Note' : 'Edit Note'; ?>
                                </button>
                            <?php endif; ?>

                            <?php if (!$isCurrentlyRecording): ?>
                                <?php if (isAdmin() || (isset($settings['allow_vlc']) && $settings['allow_vlc'])): ?>
                                    <a href="<?php echo generateVLCUrl($file); ?>"
                                       onclick="logVLCPlay(event, '<?php echo htmlspecialchars($fileName); ?>')"
                                       class="btn btn-primary btn-sm icon-btn me-2">
                                        <i class="bi bi-play-circle"></i>
                                        Play Video
                                    </a>
                                <?php endif; ?>

                                <?php if (isAdmin() || (isset($settings['allow_m3u']) && $settings['allow_m3u'])): ?>
                                    <a href="?getm3u=<?php echo urlencode($fileName); ?>"
                                       class="btn btn-secondary btn-sm icon-btn me-2">
                                        <i class="bi bi-file-earmark-play"></i>
                                        Download M3U
                                    </a>
                                <?php endif; ?>

                                <?php if (isAdmin() || (isset($settings['allow_mp4']) && $settings['allow_mp4'])): ?>
                                    <a href="?download=<?php echo urlencode($fileName); ?>"
                                       class="btn btn-success btn-sm icon-btn me-2">
                                        <i class="bi bi-download"></i>
                                        Download MP4
                                    </a>
                                <?php endif; ?>

                                <?php if (isAdmin() && !$recordingActive): ?>
                                    <button class="btn btn-danger btn-sm icon-btn me-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal"
                                            data-file="<?php echo urlencode($file); ?>">
                                        <i class="bi bi-trash"></i>
                                        Delete
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <!-- Only show note form if not recording -->
                        <?php if (isAdmin() && !$isCurrentlyRecording && !$recordingActive): ?>
                            <div id="noteForm_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $fileName); ?>"
                                 class="mt-2 w-100 border-top pt-2" style="display: none;">
                                <form class="d-flex gap-2" onsubmit="handleNoteSubmit(event, this); return false;">
                                    <input type="hidden" name="recording_file"
                                           value="<?php echo htmlspecialchars($file); ?>">
                                    <div class="character-count text-muted">
                                        <small><span>0</span>/50</small>
                                    </div>
                                    <div class="input-group flex-grow-1">
                                        <span class="input-group-text">
                                            <i class="bi bi-sticky"></i>
                                        </span>
                                        <input type="text" name="note" class="form-control form-control-sm w-50"
                                               maxlength="50"
                                               value="<?php echo htmlspecialchars(getRecordingNote($file)); ?>"
                                               placeholder="Enter note for this recording (max 50 characters)"
                                               oninput="updateCharacterCount(this)">
                                    </div>
                                    <div class="flex-shrink-0">
                                        <button type="submit" class="btn btn-primary btn-sm icon-btn">
                                            <i class="bi bi-save"></i>
                                            Save Note
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm icon-btn"
                                                onclick="toggleNoteForm('<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $fileName); ?>')">
                                            <i class="bi bi-x-circle"></i>
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="list-group-item">No recordings found.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
    // Set up SSE connection
    let retryCount = 0;
    const MAX_RETRIES = 3;
    let evtSource = null;

    // Note submission handler
    async function handleNoteSubmit(event, form) {
        event.preventDefault();

        try {
            const formData = new FormData(form);
            const response = await fetch('update_note.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Update the note display without refreshing
                const fileName = form.querySelector('[name="recording_file"]').value;
                const noteText = form.querySelector('[name="note"]').value;
                const listItem = form.closest('.list-group-item');
                const noteDisplay = listItem.querySelector('.recording-note');

                if (noteText) {
                    if (noteDisplay) {
                        noteDisplay.textContent = noteText;
                    } else {
                        const smallElement = listItem.querySelector('small.text-muted');
                        smallElement.innerHTML += '<br>Note: <span class="recording-note">' +
                            noteText.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
                    }
                }

                // Hide the form
                const fileId = fileName.split('/').pop().replace(/[^a-zA-Z0-9]/g, '_');
                toggleNoteForm(fileId);

                // Update edit button text
                const editButton = listItem.querySelector('.btn-info');
                if (editButton) {
                    editButton.innerHTML = '<i class="bi bi-pencil-square"></i> Edit Note';
                }
            }
        } catch (error) {
            console.error('Error updating note:', error);
        }
    }

    function toggleNoteForm(fileId) {
        const formId = 'noteForm_' + fileId;
        const form = document.getElementById(formId);
        if (form) {
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    }

    function updateCharacterCount(input) {
        const charCount = input.value.length;
        const characterCountElement = input.closest('form').querySelector('.character-count span');
        characterCountElement.textContent = charCount;
    }

    function setupSSE() {
        if (evtSource !== null) {
            evtSource.close();
        }

        evtSource = new EventSource('?sse=listen');

        evtSource.onopen = function () {
            console.log('SSE connection established');
            retryCount = 0;
        };

        evtSource.onerror = function (err) {
            console.error('SSE connection error:', err);
            evtSource.close();
            evtSource = null;

            if (retryCount < MAX_RETRIES) {
                const backoffTime = Math.min(1000 * Math.pow(2, retryCount), 10000);
                retryCount++;
                console.log(`Attempting reconnection in ${backoffTime / 1000} seconds...`);
                setTimeout(setupSSE, backoffTime);
            } else {
                console.log('Max retry attempts reached. Please refresh the page manually.');
            }
        };

        evtSource.addEventListener('recordingChange', function (e) {
            console.log('Recording change detected');
            window.location.reload();
        });

        evtSource.addEventListener('heartbeat', function (e) {
            console.log('Heartbeat received at:', new Date().toISOString());
        });
    }

    // Initialize SSE when page loads
    document.addEventListener('DOMContentLoaded', setupSSE);

    // Clean up when page is unloaded
    window.addEventListener('beforeunload', function () {
        if (evtSource) {
            evtSource.close();
        }
    });
</script>
