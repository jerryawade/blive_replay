#!/bin/bash
# Install the Stream Monitor Service cron job as root
# This script adds a cron job to perform background stream URL checks every minute to the root crontab

# Check if script is run with sudo
if [ "$EUID" -ne 0 ]; then
    echo "This script must be run with sudo to modify root's crontab."
    echo "Please run: sudo $0"
    exit 1
fi

# Define the fixed path for the stream monitor service
STREAM_MONITOR_PATH="/var/www/replay/stream_monitor_service.php"

# Create the cron job entry
CRON_ENTRY="* * * * * php ${STREAM_MONITOR_PATH} >> /var/www/replay/logs/cron_stream_check.log 2>&1"

# Check if the cron job already exists in root's crontab
EXISTING_CRON=$(sudo crontab -l 2>/dev/null | grep "${STREAM_MONITOR_PATH}")

if [ -n "$EXISTING_CRON" ]; then
    echo "Cron job already exists in root's crontab. No changes made."
    exit 0
fi

# Add the new cron job to the existing root crontab
(sudo crontab -l 2>/dev/null; echo "$CRON_ENTRY") | sudo crontab -

# Verify the cron job was added
CRON_VERIFICATION=$(sudo crontab -l | grep "${STREAM_MONITOR_PATH}")

if [ -n "$CRON_VERIFICATION" ]; then
    echo "Cron job successfully installed in root's crontab!"
    echo "The Stream Monitor Service will run every minute to perform background stream URL checks."
    echo "Current root cron jobs:"
    sudo crontab -l
else
    echo "Failed to install cron job. Please check permissions and try again."
    exit 1
fi