/**
 * User Messaging System
 * Allows administrators to send messages to users
 */

class MessageSystem {
    constructor() {
        this.messageCheckInterval = null;
        this.lastCheckTime = 0;
        this.username = '';
        this.isAdmin = false;
        this.messageModal = null;
        this.messages = [];
        this.currentMessageId = null;
        this.displayedMessageIds = new Set(JSON.parse(localStorage.getItem('displayedMessageIds') || '[]'));
    }

    init(username, isAdmin) {
        this.username = username;
        this.isAdmin = isAdmin;
        
        if (!isAdmin) {
            // Only non-admins check for messages
            this.checkMessagesFile();
            this.messageCheckInterval = setInterval(() => this.checkMessagesFile(), 30000);
            this.createMessageModal();
        } else {
            // Admins only get the UI to send messages
            this.setupAdminUI();
        }
    }

    createMessageModal() {
        if (document.getElementById('userMessageModal')) return;

        const modalHTML = `
            <div class="modal fade" id="userMessageModal" tabindex="-1" aria-labelledby="userMessageModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="userMessageModalLabel">
                                <i class="bi bi-envelope-fill me-2"></i>
                                Message
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="messageContent"></div>
                            <div class="small text-muted mt-3">
                                Sent: <span id="messageSentTime"></span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-success icon-btn" data-bs-dismiss="modal" id="closeMessageBtn">
                                <i class="bi bi-x-lg"></i>
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer.firstElementChild);

        this.messageModal = new bootstrap.Modal(document.getElementById('userMessageModal'));

        document.getElementById('closeMessageBtn').addEventListener('click', () => {
            if (this.currentMessageId) {
                this.markMessageAsRead(this.currentMessageId);
                this.displayedMessageIds.add(this.currentMessageId);
                localStorage.setItem('displayedMessageIds', JSON.stringify([...this.displayedMessageIds]));
                this.currentMessageId = null;
            }
        });

        document.getElementById('userMessageModal').addEventListener('hidden.bs.modal', () => {
            if (this.currentMessageId) {
                this.markMessageAsRead(this.currentMessageId);
                this.displayedMessageIds.add(this.currentMessageId);
                localStorage.setItem('displayedMessageIds', JSON.stringify([...this.displayedMessageIds]));
                this.currentMessageId = null;
            }
        });
    }

    setupAdminUI() {
        this.addBroadcastButton();
        this.addUserMessageButtons();
    }

    addBroadcastButton() {
        const usersModal = document.getElementById('usersModal');
        if (!usersModal) return;

        const modalFooter = usersModal.querySelector('.modal-footer');
        if (!modalFooter) return;

        const graphButton = modalFooter.querySelector('#viewUsageGraphBtn');
        const closeButton = modalFooter.querySelector('.btn-secondary');
        if (!graphButton || !closeButton) return;

        const broadcastButton = document.createElement('button');
        broadcastButton.type = 'button';
        broadcastButton.className = 'btn btn-success icon-btn';
        broadcastButton.innerHTML = '<i class="bi bi-broadcast"></i> Broadcast Message';
        broadcastButton.addEventListener('click', () => this.showSendMessageForm());
        closeButton.parentNode.insertBefore(broadcastButton, closeButton);
    }

    addUserMessageButtons() {
        const usersModal = document.getElementById('usersModal');
        if (!usersModal) return;

        const userRows = usersModal.querySelectorAll('tr[data-username]');
        userRows.forEach(row => this.addMessageButtonToRow(row));

        const tableBody = usersModal.querySelector('table tbody');
        if (tableBody) {
            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(node => {
                            if (node.nodeType === 1 && node.hasAttribute('data-username')) {
                                this.addMessageButtonToRow(node);
                            }
                        });
                    }
                });
            });
            observer.observe(tableBody, { childList: true });
        }
    }

    addMessageButtonToRow(row) {
        const username = row.dataset.username;
        if (!username || row.querySelector('.user-message-btn')) return;

        const actionsCell = row.cells[2];
        if (!actionsCell) return;

        const buttonsContainer = actionsCell.querySelector('.action-buttons-container');
        if (!buttonsContainer) return;

        const passwordBtn = buttonsContainer.querySelector('button:nth-child(2)');
        if (!passwordBtn) return;

        const messageButton = document.createElement('button');
        messageButton.type = 'button';
        messageButton.className = 'btn btn-success btn-sm icon-btn w-100 w-md-auto user-message-btn';
        messageButton.innerHTML = '<i class="bi bi-envelope"></i> Message';
        messageButton.addEventListener('click', () => this.showSendMessageForm(username));
        passwordBtn.insertAdjacentElement('afterend', messageButton);
    }

    showSendMessageForm(recipient = null) {
        if (!document.getElementById('sendMessageModal')) {
            const modalHTML = `
                <div class="modal fade" id="sendMessageModal" tabindex="-1" aria-labelledby="sendMessageModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="sendMessageModalLabel">
                                    <i class="bi bi-envelope-plus me-2"></i>
                                    Send Message
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="sendMessageForm">
                                    <div class="mb-3">
                                        <label for="messageRecipient" class="form-label">Recipient</label>
                                        <input type="text" class="form-control" id="messageRecipient" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label for="messageSubject" class="form-label">Subject</label>
                                        <input type="text" class="form-control" id="messageSubject" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="messageBody" class="form-label">Message</label>
                                        <textarea class="form-control" id="messageBody" rows="5" required></textarea>
                                    </div>
                                </form>
                                <div id="sendMessageResult" style="display: none;"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                                    <i class="bi bi-x-lg"></i>
                                    Cancel
                                </button>
                                <button type="button" class="btn btn-success icon-btn" id="sendMessageBtn">
                                    <i class="bi bi-envelope-paper"></i>
                                    Send Message
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHTML;
            document.body.appendChild(modalContainer.firstElementChild);

            document.getElementById('sendMessageBtn').addEventListener('click', () => this.sendMessage());
        }

        document.getElementById('messageRecipient').value = recipient || 'All users (Broadcast)';
        document.getElementById('messageSubject').value = '';
        document.getElementById('messageBody').value = '';
        document.getElementById('sendMessageResult').style.display = 'none';

        const sendMessageModal = new bootstrap.Modal(document.getElementById('sendMessageModal'));
        sendMessageModal.show();
    }

    async sendMessage() {
        const recipient = document.getElementById('messageRecipient').value;
        const subject = document.getElementById('messageSubject').value;
        const body = document.getElementById('messageBody').value;
        const resultElement = document.getElementById('sendMessageResult');

        if (!subject || !body) {
            resultElement.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Please fill in all fields</div>`;
            resultElement.style.display = 'block';
            return;
        }

        const sendButton = document.getElementById('sendMessageBtn');
        sendButton.disabled = true;
        sendButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';

        try {
            const messageData = {
                recipient: recipient === 'All users (Broadcast)' ? null : recipient,
                subject: subject,
                body: body,
                sender: this.username,
                timestamp: new Date().toISOString()
            };

            const response = await fetch('message_actions.php?action=send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(messageData)
            });

            const result = await response.json();

            if (result.success) {
                resultElement.innerHTML = `<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>${result.message}</div>`;
                resultElement.style.display = 'block';
                document.getElementById('messageSubject').value = '';
                document.getElementById('messageBody').value = '';
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('sendMessageModal'));
                    if (modal) modal.hide();
                }, 1500);
            } else {
                resultElement.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>${result.message || 'Error sending message'}</div>`;
                resultElement.style.display = 'block';
            }
        } catch (error) {
            resultElement.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error: ${error.message || 'Unknown error'}</div>`;
            resultElement.style.display = 'block';
        } finally {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="bi bi-envelope-paper"></i> Send Message';
        }
    }

    async checkMessagesFile() {
        try {
            const timestamp = Date.now();
            const response = await fetch(`check_messages.php?t=${timestamp}`, {
                method: 'GET',
                headers: { 'Cache-Control': 'no-cache' }
            });

            if (!response.ok) throw new Error(`Server returned ${response.status}`);

            const result = await response.json();
            
            if (result.success && result.messages && result.messages.length > 0) {
                this.messages = result.messages;
                const unreadMessages = this.messages.filter(msg => 
                    (!msg.read_by || !msg.read_by.includes(this.username)) &&
                    !this.displayedMessageIds.has(msg.id)
                );
                
                if (unreadMessages.length > 0 && !this.messageModal._isShown) {
                    this.displayMessage(unreadMessages[0]);
                }
            }
        } catch (error) {
            console.error('Error checking for messages:', error);
        }
    }

    displayMessage(message) {
        if (!message || !message.id || this.displayedMessageIds.has(message.id)) return;
        
        this.currentMessageId = message.id;
        
        document.getElementById('messageContent').innerHTML = `
            <h5>${message.subject}</h5>
            <div class="mb-2">
                <span class="badge bg-secondary">From: ${message.sender}</span>
                ${message.recipient ? `<span class="badge bg-info ms-2">To: ${message.recipient}</span>` : ''}
            </div>
            <div class="mt-3">${message.body.replace(/\n/g, '<br>')}</div>
        `;
        
        const sentDate = new Date(message.timestamp);
        document.getElementById('messageSentTime').textContent = sentDate.toLocaleString();
        
        this.messageModal.show();
    }

    async markMessageAsRead(messageId) {
        try {
            const response = await fetch('message_actions.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    messageId: messageId,
                    username: this.username
                })
            });

            if (!response.ok) throw new Error('Failed to mark message as read');

            this.displayedMessageIds.add(messageId);
            localStorage.setItem('displayedMessageIds', JSON.stringify([...this.displayedMessageIds]));
            this.currentMessageId = null;

            setTimeout(() => this.checkMessagesFile(), 500);
        } catch (error) {
            console.error('Error marking message as read:', error);
        }
    }

    destroy() {
        if (this.messageCheckInterval) {
            clearInterval(this.messageCheckInterval);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const username = document.querySelector('[data-username]')?.dataset.username || '';
    const isAdmin = document.body.getAttribute('data-is-admin') === 'true';
    
    window.messageSystem = new MessageSystem();
    window.messageSystem.init(username, isAdmin);
});

window.addEventListener('beforeunload', function() {
    if (window.messageSystem) {
        window.messageSystem.destroy();
    }
});
