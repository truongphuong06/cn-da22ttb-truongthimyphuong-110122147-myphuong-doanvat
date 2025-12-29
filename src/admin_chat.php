<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat - Trả Lời Khách Hàng</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            height: 100vh;
        }
        
        /* Sidebar danh sách session */
        .sidebar {
            width: 300px;
            background: #fff;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 20px;
            background: #00aaff;
            color: white;
            font-size: 18px;
            font-weight: bold;
        }
        .session-list {
            flex: 1;
            overflow-y: auto;
        }
        .session-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        .session-item:hover {
            background: #f9f9f9;
        }
        .session-item.active {
            background: #e6f7ff;
            border-left: 4px solid #00aaff;
        }
        .session-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .session-preview {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .session-time {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        .unread-badge {
            background: #ff4444;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            float: right;
        }
        
        /* Main chat area */
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 20px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            font-size: 18px;
            font-weight: bold;
        }
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #fafafa;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        .message.customer {
            align-items: flex-start;
        }
        .message.admin {
            align-items: flex-end;
        }
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 12px;
            word-wrap: break-word;
        }
        .message.customer .message-bubble {
            background: #fff;
            border: 1px solid #ddd;
        }
        .message.admin .message-bubble {
            background: #00aaff;
            color: white;
        }
        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        
        /* Reply form */
        .reply-form {
            padding: 20px;
            background: #fff;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
        }
        .reply-form textarea {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: none;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }
        .reply-form button {
            padding: 12px 24px;
            background: #00aaff;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        .reply-form button:hover {
            background: #0088cc;
        }
        
        .no-session {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
            color: #999;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-comments"></i> Danh Sách Chat
        </div>
        <div class="session-list" id="sessionList">
            <div style="padding: 20px; text-align: center; color: #999;">
                Đang tải...
            </div>
        </div>
    </div>

    <div class="chat-container">
        <div class="chat-header" id="chatHeader">
            Chọn khách hàng để xem chat
        </div>
        <div class="chat-messages" id="chatMessages">
            <div class="no-session">
                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd;"></i>
            </div>
        </div>
        <div class="reply-form" style="display: none;" id="replyForm">
            <textarea id="replyText" placeholder="Nhập câu trả lời..." rows="3"></textarea>
            <button onclick="sendReply()">
                <i class="fas fa-paper-plane"></i> Gửi
            </button>
        </div>
    </div>

    <script>
        let currentSessionId = null;
        let lastMessageId = 0;

        // Load danh sách sessions
        async function loadSessions() {
            try {
                const resp = await fetch('admin_chat_api.php?action=get_sessions');
                
                if (!resp.ok) {
                    throw new Error(`HTTP error! status: ${resp.status}`);
                }
                
                const data = await resp.json();
                const list = document.getElementById('sessionList');
                
                if (!data.sessions || data.sessions.length === 0) {
                    list.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Chưa có chat nào</div>';
                    return;
                }
                
                list.innerHTML = '';
                data.sessions.forEach(session => {
                    const item = document.createElement('div');
                    item.className = 'session-item';
                    if (session.session_id === currentSessionId) {
                        item.classList.add('active');
                    }
                    
                    item.innerHTML = `
                        <div class="session-name">
                            ${escapeHtml(session.customer_name)}
                            ${session.unread_count > 0 ? `<span class="unread-badge">${session.unread_count}</span>` : ''}
                        </div>
                        <div class="session-preview">${escapeHtml(session.last_message || '')}</div>
                        <div class="session-time">${session.last_message_time}</div>
                    `;
                    
                    item.onclick = () => selectSession(session.session_id, session.customer_name);
                    list.appendChild(item);
                });
            } catch (err) {
                console.error('Load sessions error:', err);
                const list = document.getElementById('sessionList');
                list.innerHTML = '<div style="padding: 20px; text-align: center; color: #f44;">Lỗi: ' + err.message + '</div>';
            }
        }

        // Chọn session để xem chat
        async function selectSession(sessionId, customerName) {
            currentSessionId = sessionId;
            lastMessageId = 0;
            
            document.getElementById('chatHeader').innerHTML = `
                <i class="fas fa-user"></i> ${escapeHtml(customerName)}
            `;
            document.getElementById('replyForm').style.display = 'flex';
            
            // Load tin nhắn
            await loadMessages();
            
            // Đánh dấu đã đọc
            await markAsRead(sessionId);
            
            // Refresh danh sách
            loadSessions();
        }

        // Load tin nhắn của session
        async function loadMessages() {
            try {
                const resp = await fetch(`admin_chat_api.php?action=get_messages&session_id=${currentSessionId}`);
                
                if (!resp.ok) {
                    throw new Error(`HTTP error! status: ${resp.status}`);
                }
                
                const data = await resp.json();
                
                if (!data.messages) {
                    throw new Error('Invalid response format');
                }
                
                const container = document.getElementById('chatMessages');
                container.innerHTML = '';
                
                if (data.messages.length === 0) {
                    container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Chưa có tin nhắn</div>';
                    return;
                }
                
                data.messages.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = 'message ' + (msg.is_from_admin ? 'admin' : 'customer');
                    div.innerHTML = `
                        <div class="message-bubble">${escapeHtml(msg.message)}</div>
                        <div class="message-time">${msg.created_at}</div>
                    `;
                    container.appendChild(div);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                });
                
                container.scrollTop = container.scrollHeight;
            } catch (err) {
                console.error('Load messages error:', err);
            }
        }

        // Gửi reply
        async function sendReply() {
            const text = document.getElementById('replyText').value.trim();
            if (!text || !currentSessionId) return;
            
            try {
                const resp = await fetch('admin_chat_api.php?action=send_reply', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        session_id: currentSessionId,
                        message: text
                    })
                });
                
                if (!resp.ok) {
                    throw new Error(`HTTP error! status: ${resp.status}`);
                }
                
                const data = await resp.json();
                
                if (data.success) {
                    document.getElementById('replyText').value = '';
                    await loadMessages();
                } else {
                    alert('Lỗi: ' + (data.message || data.error || 'Không thể gửi tin nhắn'));
                }
            } catch (err) {
                console.error('Send reply error:', err);
                alert('Lỗi gửi tin: ' + err.message);
            }
        }

        // Đánh dấu đã đọc
        async function markAsRead(sessionId) {
            try {
                await fetch(`admin_chat_api.php?action=mark_read`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        session_id: sessionId
                    })
                });
            } catch (err) {
                console.error('Mark read error:', err);
            }
        }

        // Auto refresh
        setInterval(() => {
            loadSessions();
            if (currentSessionId) {
                loadMessages();
            }
        }, 5000); // Refresh mỗi 5 giây

        // Enter để gửi
        document.getElementById('replyText').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendReply();
            }
        });

        function escapeHtml(s) {
            return (s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        }

        // Load lần đầu
        loadSessions();
    </script>
</body>
</html>
