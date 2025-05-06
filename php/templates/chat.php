<?php 
// Set page title
$title = "AI Asistan";
// Include the header
include 'header.php';
?>

<div class="chat-container">
    <div class="chat-header">
        <h1><i class="fas fa-robot"></i> ThinkOrbit AI Asistan</h1>
        <p>Eğitim yolculuğunuzda size yardımcı olabilecek yapay zeka asistanınız.</p>
        <div class="navigation-buttons" style="margin-top: 15px;">
            <a href="<?php echo $base_path; ?>/" class="btn btn-primary">
                <i class="fas fa-home"></i> Ana Sayfaya Dön
            </a>
            <a href="<?php echo $base_path; ?>/generate" class="btn btn-success">
                <i class="fas fa-plus"></i> Yeni Quiz Oluştur
            </a>
        </div>
    </div>
    
    <div class="chat-messages" id="chatMessages" style="height: 500px; overflow-y: auto; padding: 20px; background-color: var(--background-dark);">
        <!-- Welcome message -->
        <div class="message ai-message" style="margin-bottom: 20px; padding: 15px; border-radius: 10px; background-color: rgba(52, 152, 219, 0.1); border-left: 4px solid var(--accent-color); max-width: 80%;">
            <div class="message-content">
                <p>👋 Merhaba! Ben ThinkOrbit AI asistanınızım. Size nasıl yardımcı olabilirim?</p>
                <p>Eğitim, öğrenme stratejileri, çalışma teknikleri veya belirli konular hakkında sorular sorabilirsiniz.</p>
                <p>📚 İşte bazı örnekler:</p>
                <ul>
                    <li>"Matematik öğrenmek için en iyi kaynaklar nelerdir?"</li>
                    <li>"Tarih sınavına nasıl hazırlanmalıyım?"</li>
                    <li>"Programlama öğrenmek için tavsiyeleriniz var mı?"</li>
                    <li>"Etkili not alma teknikleri nelerdir?"</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="chat-input" style="padding: 20px; background-color: var(--background-medium); border-top: 1px solid var(--border-color);">
        <form id="chatForm" class="chat-form" style="display: flex; gap: 10px;">
            <input type="text" id="messageInput" class="form-control" placeholder="Mesajınızı yazın..." style="flex: 1;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const chatMessages = document.getElementById('chatMessages');
    const basePath = '<?php echo $base_path; ?>';
    
    // Chat form submit
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;
        
        // Clear input
        messageInput.value = '';
        
        // Add user message to chat
        addMessage(message, 'user');
        
        // Show loading indicator
        const loadingId = showLoading();
        
        // Create form data
        const formData = new FormData();
        formData.append('message', message);
        
        // Add token if available
        const token = localStorage.getItem('access_token');
        if (token) {
            formData.append('token', token);
        }
        
        // Send message to server
        fetch(basePath + '/chat/send', {
            method: 'POST',
            headers: {
                'Authorization': token ? `Bearer ${token}` : ''
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Remove loading indicator
            removeLoading(loadingId);
            
            if (data.status === 'success') {
                // Add AI response to chat
                addMessage(data.response, 'ai');
            } else {
                // Add error message
                addMessage('Üzgünüm, bir hata oluştu. Lütfen tekrar deneyin.', 'error');
            }
            
            // Scroll to bottom
            scrollToBottom();
        })
        .catch(error => {
            console.error('Error:', error);
            // Remove loading indicator
            removeLoading(loadingId);
            // Add error message
            addMessage('Bağlantı hatası. Lütfen internet bağlantınızı kontrol edin ve tekrar deneyin.', 'error');
            // Scroll to bottom
            scrollToBottom();
        });
    });
    
    // Add message to chat
    function addMessage(message, type) {
        const messageElement = document.createElement('div');
        messageElement.className = `message ${type}-message`;
        messageElement.style.marginBottom = '20px';
        messageElement.style.padding = '15px';
        messageElement.style.borderRadius = '10px';
        messageElement.style.maxWidth = '80%';
        
        if (type === 'user') {
            messageElement.style.backgroundColor = 'rgba(39, 174, 96, 0.1)';
            messageElement.style.borderLeft = '4px solid var(--success-color)';
            messageElement.style.marginLeft = 'auto';
        } else if (type === 'ai') {
            messageElement.style.backgroundColor = 'rgba(52, 152, 219, 0.1)';
            messageElement.style.borderLeft = '4px solid var(--accent-color)';
        } else if (type === 'error') {
            messageElement.style.backgroundColor = 'rgba(231, 76, 60, 0.1)';
            messageElement.style.borderLeft = '4px solid var(--error-color)';
        }
        
        const contentElement = document.createElement('div');
        contentElement.className = 'message-content';
        
        // Process message text (handle line breaks, links, etc.)
        const processedMessage = message.replace(/\n/g, '<br>');
        contentElement.innerHTML = processedMessage;
        
        messageElement.appendChild(contentElement);
        chatMessages.appendChild(messageElement);
        
        // Scroll to bottom
        scrollToBottom();
    }
    
    // Show loading indicator
    function showLoading() {
        const id = 'loading-' + Date.now();
        const loadingElement = document.createElement('div');
        loadingElement.id = id;
        loadingElement.className = 'message ai-message loading';
        loadingElement.style.marginBottom = '20px';
        loadingElement.style.padding = '15px';
        loadingElement.style.borderRadius = '10px';
        loadingElement.style.backgroundColor = 'rgba(52, 152, 219, 0.05)';
        loadingElement.style.borderLeft = '4px solid var(--accent-color)';
        loadingElement.style.maxWidth = '80%';
        
        loadingElement.innerHTML = `
            <div class="message-content">
                <div class="typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        
        chatMessages.appendChild(loadingElement);
        scrollToBottom();
        
        return id;
    }
    
    // Remove loading indicator
    function removeLoading(id) {
        const loadingElement = document.getElementById(id);
        if (loadingElement) {
            loadingElement.remove();
        }
    }
    
    // Scroll chat to bottom
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Add typing indicator style
    const style = document.createElement('style');
    style.textContent = `
        .typing-indicator {
            display: flex;
            align-items: center;
        }
        
        .typing-indicator span {
            height: 8px;
            width: 8px;
            background-color: var(--accent-color);
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            animation: typing 1s infinite ease-in-out;
        }
        
        .typing-indicator span:nth-child(1) {
            animation-delay: 0s;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
            margin-right: 0;
        }
        
        @keyframes typing {
            0% {
                transform: translateY(0px);
                opacity: 0.5;
            }
            50% {
                transform: translateY(-5px);
                opacity: 1;
            }
            100% {
                transform: translateY(0px);
                opacity: 0.5;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php 
// Include footer
include 'footer.php'; 
?> 