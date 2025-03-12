let lastFileTimestamp = 0;

async function updateNextScheduleBadge(force = false) {
    try {
        const response = await fetch('next_schedule.php', {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });

        if (!response.ok) {
            throw new Error('Failed to fetch next schedule');
        }

        const data = await response.json();
        console.log('Next schedule data:', data);

        const badge = document.getElementById('nextScheduleBadge');
        if (!badge) {
            console.error('Badge element not found');
            return;
        }

        if (force || data.file_timestamp !== lastFileTimestamp) {
            lastFileTimestamp = data.file_timestamp;

            if (data.success && data.next_schedule) {
                const { title, next_run, startTime, endTime } = data.next_schedule;
                const nextRunDate = new Date(next_run);
                const today = new Date();
                // Compare dates (ignoring time)
                const isToday = nextRunDate.toDateString() === today.toDateString();
                const dateStr = isToday
                    ? 'Today'
                    : nextRunDate.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' });

                badge.textContent = `Next Up: ${title}: ${dateStr} ${startTime}-${endTime}`;
                badge.style.display = 'inline-block';
                console.log('Badge updated:', badge.textContent);
            } else {
                badge.style.display = 'none';
                console.log('No next schedule found');
            }
        }
    } catch (error) {
        console.error('Error updating schedule badge:', error);
        const badge = document.getElementById('nextScheduleBadge');
        if (badge) badge.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    if (document.body.getAttribute('data-is-admin') !== 'true') return;

    console.log('Admin detected, initializing badge update');
    updateNextScheduleBadge(true);
    setInterval(() => updateNextScheduleBadge(), 30000);

    const scheduleModal = document.getElementById('scheduleModal');
    if (scheduleModal) {
        scheduleModal.addEventListener('hidden.bs.modal', function () {
            console.log('Schedule modal closed, forcing badge update');
            updateNextScheduleBadge(true);
        });
    }
});