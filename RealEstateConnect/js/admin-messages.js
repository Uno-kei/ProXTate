document.addEventListener('DOMContentLoaded', function() {
    const messageContainer = document.getElementById('messageContainer');
    const messageForm = document.getElementById('messageForm');

    // Scroll to bottom of messages
    function scrollToBottom() {
        if (messageContainer) {
            messageContainer.scrollTop = messageContainer.scrollHeight;
        }
    }

    // Format message time
    function formatTime(timestamp) {
        const now = new Date();
        const msgDate = new Date(timestamp);
        return msgDate.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit'
        });
    }

    // Add new message to container
    function addMessage(message, isSent) {
        // Remove duplicate messages first
        const existingMessages = messageContainer.querySelectorAll('.message-content p');
        const isDuplicate = Array.from(existingMessages).some(msg => 
            msg.textContent.trim() === message.trim() && 
            msg.closest('.message-item').classList.contains(isSent ? 'message-sent' : 'message-received')
        );

        if (isDuplicate) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = `message-item ${isSent ? 'message-sent' : 'message-received'}`;

        const sanitizedMessage = message
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        messageDiv.innerHTML = `
            <div class="message-content">
                <p>${sanitizedMessage}</p>
                <small class="message-time">${formatTime(new Date())}</small>
            </div>
        `;

        messageContainer.appendChild(messageDiv);
        scrollToBottom();
    }

    // Initial scroll
    scrollToBottom();

    // Handle form submission
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const textarea = this.querySelector('textarea[name="message"]');
            const message = textarea.value.trim();

            if (!message) return;

            // Get receiver ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const receiverId = urlParams.get('user');

            if (!receiverId) return;

            // Create FormData
            const formData = new FormData(this);

            // Add message immediately to UI
            addMessage(message, true);

            // Clear input
            textarea.value = '';

            // Send to server
            fetch('../api/messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to send message:', data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }

    // Poll for new messages every 3 seconds
    if (messageContainer) {
        const urlParams = new URLSearchParams(window.location.search);
        const partnerId = urlParams.get('user');

        if (partnerId) {
            setInterval(() => {
                fetch(`../api/messages.php?action=get_messages&partner_id=${partnerId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Clear container and reload all messages
                            messageContainer.innerHTML = '';
                            data.data.messages.forEach(msg => {
                                addMessage(msg.message, msg.is_sent_by_me);
                            });
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }, 3000);
        }
    }
});