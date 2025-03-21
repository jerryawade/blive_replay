<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal"
     tabindex="-1"
     role="dialog"
     aria-labelledby="deleteModalLabel"
     aria-describedby="deleteModalDescription">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                    Confirm Delete
                </h5>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close delete confirmation dialog">
                </button>
            </div>
            <div class="modal-body py-4">
                <div class="d-flex align-items-center">
                    <div class="text-danger fs-3 me-3" aria-hidden="true">
                        <i class="bi bi-question-circle-fill"></i>
                    </div>
                    <div>
                        <p class="mb-0 fs-5" id="deleteModalDescription">
                            Are you sure you want to delete this recording?
                        </p>
                        <p class="text-muted small mb-0 mt-2">
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-outline-secondary icon-btn"
                        data-bs-dismiss="modal"
                        aria-label="Cancel deletion">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                    Cancel
                </button>
                <a href="#"
                   class="btn btn-danger icon-btn"
                   id="confirmDelete"
                   role="button"
                   aria-label="Confirm deletion of recording">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = document.getElementById('deleteModal');
        if (!deleteModal) return;

        deleteModal.addEventListener('show.bs.modal', function(event) {
            const triggerButton = event.relatedTarget;
            const fileToDelete = triggerButton ? triggerButton.getAttribute('data-file') : null;

            const confirmDeleteBtn = this.querySelector('#confirmDelete');
            if (fileToDelete) {
                confirmDeleteBtn.href = `?delete=${fileToDelete}`;
            }

            // Focus management
            const focusableElements = this.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            // Trap focus
            const trapFocus = (event) => {
                if (event.key === 'Escape') {
                    const modalInstance = bootstrap.Modal.getInstance(this);
                    modalInstance?.hide();
                    return;
                }

                if (event.key === 'Tab') {
                    if (event.shiftKey && document.activeElement === firstElement) {
                        lastElement.focus();
                        event.preventDefault();
                    } else if (!event.shiftKey && document.activeElement === lastElement) {
                        firstElement.focus();
                        event.preventDefault();
                    }
                }
            };

            // Add and remove event listeners
            this.addEventListener('keydown', trapFocus);

            this.addEventListener('hidden.bs.modal', () => {
                this.removeEventListener('keydown', trapFocus);
            }, { once: true });

            // Add loading state when delete is clicked
            confirmDeleteBtn.addEventListener('click', function() {
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Deleting...';
                this.classList.add('disabled');
            }, { once: true });
        });
    });
</script>
