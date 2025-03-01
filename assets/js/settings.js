/**
 * Settings Management System
 * Replaces form submissions with fetch API calls
 */
class SettingsManager {
    constructor() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Find save settings button
        const settingsForm = document.getElementById('settingsForm');
        const saveButton = document.querySelector('#settingsModal .btn-primary');
        
        if (saveButton && settingsForm) {
            saveButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.saveSettings(settingsForm);
            });
        }
    }

    async saveSettings(form) {
        try {
            // Get form data
            const formData = new FormData(form);
            
            // Add checkboxes that might not be included when unchecked
            const checkboxes = ['show_recordings', 'show_livestream', 'allow_vlc', 'allow_m3u', 'allow_mp4'];
            checkboxes.forEach(name => {
                if (!formData.has(name)) {
                    formData.append(name, '0');
                }
            });
            
            // Add the update_settings flag
            formData.append('update_settings', '1');
            
            // Close the modal first
            const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));
            if (modal) {
                modal.hide();
            }
            
            // Show processing indicator (optional)
            document.body.style.cursor = 'wait';
            
            // Send the request
            const response = await fetch('settings_update.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            // Reset cursor
            document.body.style.cursor = 'default';
            
            if (result.success) {
                console.log('Settings updated successfully');
                
                // Refresh any UI elements that depend on settings
                // This could update UI without a full page reload
                
                // If necessary, reload the page to reflect changes
                if (result.reload) {
                    window.location.reload();
                }
            } else {
                console.error('Failed to update settings:', result.message);
            }
        } catch (error) {
            document.body.style.cursor = 'default';
            console.error('Error saving settings:', error);
        }
    }
}

// Initialize settings manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.settingsManager = new SettingsManager();
});
