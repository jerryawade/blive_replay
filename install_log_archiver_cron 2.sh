#!/bin/bash
# Install the log archiver cron job as root
# This script adds a daily cron job to run the activity log archiver to the root crontab

# Check if script is run with sudo
if [ "$EUID" -ne 0 ]; then
  echo "This script must be run with sudo to modify root's crontab."
  echo "Please run: sudo $0"
  exit 1
fi

# Get absolute path to the web directory
WEB_DIR=$(pwd)

# Define the comment
CRON_COMMENT="#Service: BLIVE RePlay - Run Activity Log Archiver Daily"
# Define the cron command
CRON_COMMAND="0 0 * * * php ${WEB_DIR}/activity_log_archiver.php > /dev/null 2>&1"

# Check if the cron job already exists in root's crontab
EXISTING_CRON=$(sudo crontab -l 2>/dev/null | grep "${WEB_DIR}/activity_log_archiver.php")

if [ -n "$EXISTING_CRON" ]; then
  echo "Cron job already exists in root's crontab. No changes made."
  exit 0
fi

# Add the new cron job (comment + command) to the existing root crontab
# Use a temporary file to ensure proper line breaks
TEMP_CRON="/tmp/crontab.tmp"
sudo crontab -l 2>/dev/null >"$TEMP_CRON" || touch "$TEMP_CRON"
echo "$CRON_COMMENT" >>"$TEMP_CRON"
echo "$CRON_COMMAND" >>"$TEMP_CRON"
sudo crontab "$TEMP_CRON"
rm -f "$TEMP_CRON"

# Verify the cron job was added
CRON_VERIFICATION=$(sudo crontab -l | grep "${WEB_DIR}/activity_log_archiver.php")

if [ -n "$CRON_VERIFICATION" ]; then
  echo "Cron job successfully installed in root's crontab!"
  echo "The log archiver will run daily at midnight."
  echo "Current root cron jobs:"
  sudo crontab -l
else
  echo "Failed to install cron job. Please check permissions and try again."
  exit 1
fi
