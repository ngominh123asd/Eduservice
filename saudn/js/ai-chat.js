// AI Chat Widget Functions

class AIChatWidget {
    constructor() {
        this.widget = document.getElementById('ai-chat-widget');
        this.toggle = document.getElementById('ai-widget-toggle');
        this.container = document.getElementById('ai-chat-container');
        this.closeBtn = document.getElementById('ai-chat-close');
        this.messagesContainer = document.getElementById('ai-chat-messages');
        this.form = document.getElementById('ai-chat-form');
        this.input = document.getElementById('ai-chat-input');
        this.typingIndicator = document.getElementById('ai-typing-indicator');
        
        this.isOpen = false;
        this.conversationHistory = [];
        
        this.init();
    }
    
    init() {
        // Toggle chat
        this.toggle.addEventListener('click', () => this.toggleChat());
        this.closeBtn.addEventListener('click', () => this.closeChat());
        
        // Handle form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Handle suggestion buttons
        document.querySelectorAll('.ai-suggestion-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const suggestion = btn.dataset.suggestion;
                this.input.value = suggestion;
                this.handleSubmit(new Event('submit'));
            });
        });
        
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (this.isOpen && 
                !this.container.contains(e.target) && 
                !this.toggle.contains(e.target)) {
                this.closeChat();
            }
        });
        
        // Load conversation history
        this.loadConversationHistory();
    }
    
    toggleChat() {
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }
    
    openChat() {
        this.container.classList.add('active');
        this.isOpen = true;
        this.input.focus();
        
        // Animation
        this.container.style.animation = 'slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
    }
    
    closeChat() {
        this.container.classList.remove('active');
        this.isOpen = false;
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        const message = this.input.value.trim();
        if (!message) return;
        
        // Add user message
        this.addMessage(message, 'user');
        this.input.value = '';
        
        // Show typing indicator
        this.showTyping();
        
        // Send to AI
        try {
            const response = await this.sendToAI(message);
            this.hideTyping();
            this.addMessage(response, 'bot');
        } catch (error) {
            console.error('AI Error:', error);
            this.hideTyping();
            this.addMessage('Xin lỗi, tôi đang gặp sự cố. Vui lòng thử lại sau.', 'bot');
        }
        
        // Save conversation
        this.saveConversationHistory();
    }
    
    addMessage(content, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `ai-message ai-message-${sender}`;
        
        const avatar = document.createElement('div');
        avatar.className = 'ai-message-avatar';
        avatar.innerHTML = sender === 'bot' ? '<i class="fas fa-robot"></i>' : '<i class="fas fa-user"></i>';
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'ai-message-content';
        
        if (typeof content === 'string') {
            contentDiv.innerHTML = this.formatMessage(content);
        } else {
            contentDiv.appendChild(content);
        }
        
        messageDiv.appendChild(avatar);
        messageDiv.appendChild(contentDiv);
        
        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
        
        // Add to history
        this.conversationHistory.push({
            sender,
            content,
            timestamp: new Date().toISOString()
        });
    }
    
    formatMessage(message) {
        // Convert markdown-like syntax to HTML
        message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        message = message.replace(/\*(.*?)\*/g, '<em>$1</em>');
        message = message.replace(/\n/g, '<br>');
        
        // Convert lists
        if (message.includes('- ')) {
            const lines = message.split('<br>');
            let inList = false;
            let result = '';
            
            lines.forEach(line => {
                if (line.trim().startsWith('- ')) {
                    if (!inList) {
                        result += '<ul>';
                        inList = true;
                    }
                    result += `<li>${line.trim().substring(2)}</li>`;
                } else {
                    if (inList) {
                        result += '</ul>';
                        inList = false;
                    }
                    result += line + '<br>';
                }
            });
            
            if (inList) result += '</ul>';
            message = result;
        }
        
        return message;
    }
    
    showTyping() {
        this.typingIndicator.classList.add('active');
    }
    
    hideTyping() {
        this.typingIndicator.classList.remove('active');
    }
    
    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }
    
    async sendToAI(message) {
        // Call AI API
        const response = await fetch('api/ai-chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: message,
                history: this.conversationHistory.slice(-5) // Last 5 messages for context
            })
        });
        
        if (!response.ok) {
            throw new Error('AI service unavailable');
        }
        
        const data = await response.json();
        return data.response;
    }
    
    saveConversationHistory() {
        try {
            localStorage.setItem('ai_chat_history', JSON.stringify(this.conversationHistory));
        } catch (error) {
            console.error('Error saving history:', error);
        }
    }
    
    loadConversationHistory() {
        try {
            const history = localStorage.getItem('ai_chat_history');
            if (history) {
                this.conversationHistory = JSON.parse(history);
                
                // Restore messages (skip welcome message)
                this.conversationHistory.forEach(msg => {
                    if (msg.sender !== 'bot' || this.messagesContainer.children.length > 1) {
                        this.addMessageToUI(msg.content, msg.sender);
                    }
                });
            }
        } catch (error) {
            console.error('Error loading history:', error);
        }
    }
    
    addMessageToUI(content, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `ai-message ai-message-${sender}`;
        
        const avatar = document.createElement('div');
        avatar.className = 'ai-message-avatar';
        avatar.innerHTML = sender === 'bot' ? '<i class="fas fa-robot"></i>' : '<i class="fas fa-user"></i>';
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'ai-message-content';
        contentDiv.innerHTML = this.formatMessage(content);
        
        messageDiv.appendChild(avatar);
        messageDiv.appendChild(contentDiv);
        
        this.messagesContainer.appendChild(messageDiv);
    }
    
    clearHistory() {
        this.conversationHistory = [];
        this.messagesContainer.innerHTML = `
            <div class="ai-message ai-message-bot">
                <div class="ai-message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="ai-message-content">
                    <p>Xin chào! Tôi là trợ lý AI của bạn. Tôi có thể giúp bạn:</p>
                    <ul>
                        <li>📚 Tìm kiếm thông tin về lớp học</li>
                        <li>📝 Hỗ trợ làm bài tập</li>
                        <li>❓ Trả lời câu hỏi về nội dung học</li>
                        <li>📊 Theo dõi tiến độ học tập</li>
                    </ul>
                    <p>Bạn cần giúp gì không? 😊</p>
                </div>
            </div>
        `;
        localStorage.removeItem('ai_chat_history');
    }
}

// Initialize AI Chat Widget
document.addEventListener('DOMContentLoaded', () => {
    window.aiChatWidget = new AIChatWidget();
});