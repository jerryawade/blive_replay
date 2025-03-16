#!/bin/bash
# Install the Cleanup Messages cron job as root
# This script adds a cron job to run cleanup_messages.php every night at midnight in the root crontab

# Check if script is run with sudo
if [ "$EUID" -ne 0 ]; then
  echo "This script must be run with sudo to modify root's crontab."
  echo "Please run: sudo $0"
  exit 1
fi

# Define the fixed path for the cleanup messages script
CLEANUP_MESSAGES_PATH="/var/www/replay/cleanup_messages.php"

# Define the comment
CRON_COMMENT="#Service: BLIVE RePlay - Run Cleanup Messages Every Night at Midnight"
# Define the cron command (runs at 00:00 every day)
CRON_COMMAND="0 0 * * * php ${CLEANUP_MESSAGES_PATH} >> /var/www/replay/logs/cron_cleanup_messages.log 2>&1"

# Check if the cron job already exists in root's crontab
EXISTING_CRON=$(sudo crontab -l 2>/dev/null | grep "${CLEANUP_MESSAGES_PATH}")

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
CRON_VERIFICATION=$(sudo crontab -l | grep "${CLEANUP_MESSAGES_PATH}")

if [ -n "$CRON_VERIFICATION" ]; then
  echo "Cron job successfully installed in root's crontab!"
  echo "The Cleanup Messages script will run every night at midnight to clear all messages."
  echo "Current root cron jobs:"
  sudo crontab -l
else
  echo "Failed to install cron job. Please check permissions and try again."
  exit 1
fi