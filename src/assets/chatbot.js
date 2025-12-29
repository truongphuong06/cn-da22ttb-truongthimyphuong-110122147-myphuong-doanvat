// Tooltip cho icon con mắt quick view (san-pham.php)
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.eye-tooltip-wrapper').forEach(function(wrapper) {
    var tooltip = wrapper.querySelector('.eye-tooltip');
    if (!tooltip) return;
    wrapper.addEventListener('mouseenter', function() {
      tooltip.style.display = 'block';
    });
    wrapper.addEventListener('mouseleave', function() {
      tooltip.style.display = 'none';
    });
    wrapper.addEventListener('touchstart', function(e) {
      tooltip.style.display = 'block';
      e.stopPropagation();
    });
    document.body.addEventListener('touchstart', function() {
      tooltip.style.display = 'none';
    });
  });
});
// Tooltip cho icon con mắt quick view
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.eye-tooltip-wrapper').forEach(function(wrapper) {
    var tooltip = wrapper.querySelector('.eye-tooltip');
    if (!tooltip) return;
    wrapper.addEventListener('mouseenter', function() {
      tooltip.style.display = 'block';
    });
    wrapper.addEventListener('mouseleave', function() {
      tooltip.style.display = 'none';
    });
    wrapper.addEventListener('touchstart', function(e) {
      tooltip.style.display = 'block';
      e.stopPropagation();
    });
    document.body.addEventListener('touchstart', function() {
      tooltip.style.display = 'none';
    });
  });
});
// Simple Chat Widget
(function() {
  'use strict';
  
  let sessionId = null;
  let userName = null;
  let userContact = null;
  let lastMessageId = 0;
  let pollingInterval = null;
  
  // Create chat widget HTML
  function createWidget() {
    const widget = document.createElement('div');
    widget.id = 'chatWidget';
    widget.innerHTML = `
      <button id="chatToggle">💬</button>
      <div id="chatWindow">
        <div id="chatHeader">
          <h3 id="chatTitle">Chat với Admin</h3>
          <button id="closeChat">×</button>
        </div>
        <div id="chatMessages"></div>
        <div id="chatInput">
          <input type="text" id="messageInput" placeholder="Nhập tin nhắn...">
          <button id="sendButton">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="22" y1="2" x2="11" y2="13"></line>
              <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
          </button>
        </div>
      </div>
    `;
    document.body.appendChild(widget);
  }
  
  // Initialize session
  function initSession() {
    // Check if user is logged in (from PHP session)
    if (typeof window.loggedUserId !== 'undefined' && window.loggedUserId) {
      sessionId = 'user_' + window.loggedUserId;
      userName = window.loggedUsername || 'Khách hàng';
      userContact = window.loggedUsername || '';
      return true;
    }
    
    // Check localStorage for guest info
    const saved = localStorage.getItem('chat_session');
    if (saved) {
      try {
        const data = JSON.parse(saved);
        sessionId = data.sessionId;
        userName = data.userName;
        userContact = data.userContact;
        return true;
      } catch(e) {}
    }
    
    return false;
  }
  
  // Show user info form
  function showUserInfoForm() {
    const messages = document.getElementById('chatMessages');
    messages.innerHTML = `
      <div id="userInfoForm">
        <h4>👋 Xin chào! Vui lòng nhập thông tin để bắt đầu chat</h4>
        <input type="text" id="userName" placeholder="Tên của bạn" required>
        <input type="text" id="userPhone" placeholder="Số điện thoại hoặc Email" required>
        <button onclick="window.chatWidget.startChat()">Bắt đầu chat</button>
      </div>
    `;
  }
  
  // Start chat after getting user info
  window.chatWidget = {
    startChat: function() {
      const nameInput = document.getElementById('userName');
      const phoneInput = document.getElementById('userPhone');
      
      if (!nameInput || !phoneInput) return;
      
      const name = nameInput.value.trim();
      const phone = phoneInput.value.trim();
      
      if (!name || !phone) {
        alert('Vui lòng nhập đầy đủ thông tin!');
        return;
      }
      
      userName = name;
      userContact = phone;
      sessionId = 'guest_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
      
      // Save to localStorage
      localStorage.setItem('chat_session', JSON.stringify({
        sessionId, userName, userContact
      }));
      
      // Load chat interface
      loadMessages();
      startPolling();
      
      // Update title
      document.getElementById('chatTitle').textContent = 'Chat - ' + userName;
    }
  };
  
  // Add message to UI
  function addMessage(text, isUser, time) {
    const messages = document.getElementById('chatMessages');
    const msg = document.createElement('div');
    msg.className = 'message ' + (isUser ? 'user' : 'admin');
    
    const timeStr = time ? new Date(time).toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'}) : '';
    
    msg.innerHTML = `
      <div class="message-sender">${isUser ? userName : 'Admin'}</div>
      <div class="message-bubble">${escapeHtml(text)}</div>
      ${timeStr ? '<div class="message-time">' + timeStr + '</div>' : ''}
    `;
    
    messages.appendChild(msg);
    messages.scrollTop = messages.scrollHeight;
  }
  
  // Escape HTML
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  // Load messages from server
  function loadMessages() {
    if (!sessionId) return;
    
    fetch('/WebCN/chat_api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        action: 'load_messages',
        session_id: sessionId
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const messages = document.getElementById('chatMessages');
        messages.innerHTML = '';
        
        if (data.messages.length === 0) {
          messages.innerHTML = '<div class="system-message">Gửi tin nhắn để bắt đầu trò chuyện với admin</div>';
        } else {
          data.messages.forEach(msg => {
            addMessage(msg.message, msg.is_from_admin == 0, msg.created_at);
            if (msg.id > lastMessageId) lastMessageId = msg.id;
          });
        }
      }
    })
    .catch(err => console.error('Load messages error:', err));
  }
  
  // Send message
  function sendMessage() {
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    
    if (!text || !sessionId) return;
    
    // Add to UI immediately
    addMessage(text, true);
    input.value = '';
    
    // Send to server
    fetch('/WebCN/chat_api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        action: 'send_message',
        session_id: sessionId,
        customer_name: userName,
        customer_contact: userContact,
        message: text
      })
    })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        console.error('Send error:', data.error);
      }
    })
    .catch(err => console.error('Send message error:', err));
  }
  
  // Poll for new messages
  function startPolling() {
    if (pollingInterval) return;
    
    pollingInterval = setInterval(() => {
      if (!sessionId) return;
      
      fetch('/WebCN/chat_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          action: 'check_new',
          session_id: sessionId,
          last_id: lastMessageId
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.messages.length > 0) {
          data.messages.forEach(msg => {
            if (msg.is_from_admin == 1) {
              addMessage(msg.message, false, msg.created_at);
            }
            if (msg.id > lastMessageId) lastMessageId = msg.id;
          });
        }
      })
      .catch(err => console.error('Polling error:', err));
    }, 3000);
  }
  
  function stopPolling() {
    if (pollingInterval) {
      clearInterval(pollingInterval);
      pollingInterval = null;
    }
  }
  
  // Initialize
  document.addEventListener('DOMContentLoaded', function() {
    createWidget();
    
    const toggle = document.getElementById('chatToggle');
    const chatWindow = document.getElementById('chatWindow');
    const closeBtn = document.getElementById('closeChat');
    const sendBtn = document.getElementById('sendButton');
    const input = document.getElementById('messageInput');
    
    // Toggle chat
    toggle.addEventListener('click', () => {
      const isActive = chatWindow.classList.toggle('active');
      
      if (isActive) {
        if (!initSession()) {
          showUserInfoForm();
        } else {
          loadMessages();
          startPolling();
          document.getElementById('chatTitle').textContent = 'Chat - ' + userName;
        }
      } else {
        stopPolling();
      }
    });
    
    // Close chat
    closeBtn.addEventListener('click', () => {
      chatWindow.classList.remove('active');
      stopPolling();
    });
    
    // Send message
    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') sendMessage();
    });
  });
})();
