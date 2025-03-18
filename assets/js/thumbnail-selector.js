/**
 * Thumbnail Selector JavaScript
 * Handles the thumbnail selection functionality
 */

// Store current video file being processed
let currentVideoFile = null;
let currentThumbnailModal = null;
let selectedThumbnailId = null;

// Initialize the thumbnail selector when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Add the modal to the page if it doesn't exist
    if (!document.getElementById('thumbnailSelectorModal')) {
        // Load the modal template from server or embed it directly
        fetch('thumbnail_modal_template.php')
            .then(response => response.text())
            .then(html => {
                document.body.insertAdjacentHTML('beforeend', html);
                setupThumbnailModalEvents();
            })
            .catch(error => {
                console.error('Error loading thumbnail modal template:', error);
            });
    } else {
        setupThumbnailModalEvents();
    }
    
    // Attach click handler to thumbnails
    document.querySelectorAll('.regenerate-thumbnail').forEach(function(thumbnail) {
        thumbnail.addEventListener('click', function(e) {
            e.preventDefault();
            showThumbnailSelector(this.dataset.file);
        });
        
        // Add hover effect to indicate clickability
        thumbnail.style.cursor = 'pointer';
        thumbnail.title = "Click to select a different thumbnail";
    });
});

/**
 * Set up event handlers for the thumbnail modal
 */
function setupThumbnailModalEvents() {
    const modal = document.getElementById('thumbnailSelectorModal');
    if (!modal) return;
    
    // Initialize Bootstrap modal
    currentThumbnailModal = new bootstrap.Modal(modal);
    
    // Handle regenerate button click
    const regenerateBtn = document.getElementById('regenerateThumbnailsBtn');
    if (regenerateBtn) {
        regenerateBtn.addEventListener('click', function() {
            if (currentVideoFile) {
                loadThumbnailOptions(currentVideoFile);
            }
        });
    }
    
    // Clean up when the modal is hidden
    modal.addEventListener('hidden.bs.modal', function() {
        cleanupTemporaryThumbnails();
    });
}

/**
 * Show the thumbnail selector modal for a video
 */
function showThumbnailSelector(videoFile) {
    currentVideoFile = videoFile;
    
    // Reset the modal state
    const spinnerElement = document.getElementById('thumbnailSelectorSpinner');
    const contentElement = document.getElementById('thumbnailSelectorContent');
    const errorElement = document.getElementById('thumbnailSelectorError');
    
    if (spinnerElement) spinnerElement.style.display = 'block';
    if (contentElement) contentElement.style.display = 'none';
    if (errorElement) errorElement.style.display = 'none';
    
    // Show the modal
    if (currentThumbnailModal) {
        currentThumbnailModal.show();
    } else {
        // If modal isn't initialized yet, try again after a short delay
        setTimeout(() => {
            currentThumbnailModal = new bootstrap.Modal(document.getElementById('thumbnailSelectorModal'));
            currentThumbnailModal.show();
        }, 100);
    }
    
    // Load thumbnail options
    loadThumbnailOptions(videoFile);
}

/**
 * Load thumbnail options for the video
 */
async function loadThumbnailOptions(videoFile) {
    // Update UI to show loading state
    const spinnerElement = document.getElementById('thumbnailSelectorSpinner');
    const contentElement = document.getElementById('thumbnailSelectorContent');
    const errorElement = document.getElementById('thumbnailSelectorError');
    const optionsContainer = document.querySelector('.thumbnail-options');
    
    if (spinnerElement) spinnerElement.style.display = 'block';
    if (contentElement) contentElement.style.display = 'none';
    if (errorElement) errorElement.style.display = 'none';
    
    try {
        // Make request to server to generate thumbnails
        const formData = new FormData();
        formData.append('videoFile', videoFile);
        formData.append('action', 'generate');
        
        const response = await fetch('thumbnail_selector.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update the options container with thumbnail choices
            if (optionsContainer) {
                optionsContainer.innerHTML = '';
                
                result.thumbnails.forEach(thumbnail => {
                    const thumbnailHtml = `
                        <div class="col-md-4">
                            <div class="thumbnail-option" data-id="${thumbnail.id}" data-type="${thumbnail.type}">
                                <div class="thumbnail-image-container">
                                    <img src="${thumbnail.src}" class="thumbnail-image" alt="${thumbnail.label}">
                                </div>
                                <div class="thumbnail-label">${thumbnail.label}</div>
                            </div>
                        </div>
                    `;
                    
                    optionsContainer.insertAdjacentHTML('beforeend', thumbnailHtml);
                });
                
                // Add click handlers to options
                document.querySelectorAll('.thumbnail-option').forEach(option => {
                    option.addEventListener('click', function() {
                        // Remove selected class from all options
                        document.querySelectorAll('.thumbnail-option').forEach(opt => {
                            opt.classList.remove('selected');
                        });
                        
                        // Add selected class to this option
                        this.classList.add('selected');
                        
                        // Save the selected thumbnail ID
                        selectedThumbnailId = this.dataset.id;
                        
                        // Apply the selected thumbnail
                        applySelectedThumbnail();
                    });
                });
            }
            
            // Show the content
            if (spinnerElement) spinnerElement.style.display = 'none';
            if (contentElement) contentElement.style.display = 'block';
        } else {
            throw new Error(result.message || 'Failed to generate thumbnails');
        }
    } catch (error) {
        console.error('Error loading thumbnail options:', error);
        
        // Show error message
        if (spinnerElement) spinnerElement.style.display = 'none';
        if (errorElement) {
            errorElement.style.display = 'block';
            const errorMessageElement = errorElement.querySelector('.error-message');
            if (errorMessageElement) {
                errorMessageElement.textContent = error.message || 'Failed to generate thumbnails';
            }
        }
    }
}

/**
 * Apply the selected thumbnail
 */
async function applySelectedThumbnail() {
    if (!selectedThumbnailId || !currentVideoFile) return;
    
    try {
        // Show loading state
        const optionsContainer = document.querySelector('.thumbnail-options');
        if (optionsContainer) {
            optionsContainer.style.opacity = '0.5';
            optionsContainer.style.pointerEvents = 'none';
        }
        
        // Submit selection to server
        const formData = new FormData();
        formData.append('videoFile', currentVideoFile);
        formData.append('selected', selectedThumbnailId);
        formData.append('action', 'select');
        
        const response = await fetch('thumbnail_selector.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update the thumbnail on the page
            const thumbnailImg = document.querySelector(`img[data-file="${currentVideoFile}"]`);
            if (thumbnailImg) {
                thumbnailImg.src = result.thumbnail;
            }
            
            // Close the modal after a short delay
            setTimeout(() => {
                if (currentThumbnailModal) {
                    currentThumbnailModal.hide();
                }
            }, 1000);
        } else {
            throw new Error(result.message || 'Failed to apply thumbnail');
        }
    } catch (error) {
        console.error('Error applying thumbnail:', error);
        
        // Show error message
        const errorElement = document.getElementById('thumbnailSelectorError');
        if (errorElement) {
            errorElement.style.display = 'block';
            const errorMessageElement = errorElement.querySelector('.error-message');
            if (errorMessageElement) {
                errorMessageElement.textContent = error.message || 'Failed to apply thumbnail';
            }
        }
    } finally {
        // Reset UI state
        const optionsContainer = document.querySelector('.thumbnail-options');
        if (optionsContainer) {
            optionsContainer.style.opacity = '1';
            optionsContainer.style.pointerEvents = 'auto';
        }
    }
}

/**
 * Clean up temporary thumbnails
 */
async function cleanupTemporaryThumbnails() {
    if (!currentVideoFile) return;
    
    try {
        const formData = new FormData();
        formData.append('videoFile', currentVideoFile);
        formData.append('action', 'cleanup');
        
        await fetch('thumbnail_selector.php', {
            method: 'POST',
            body: formData
        });
        
        // Reset variables
        selectedThumbnailId = null;
    } catch (error) {
        console.error('Error cleaning up thumbnails:', error);
    }
}
