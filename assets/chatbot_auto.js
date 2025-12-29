// assets/chatbot_auto.js - Chat thủ công với admin
document.addEventListener("DOMContentLoaded", function() {
  // Tạo UI chat nếu chưa có
  if (!document.getElementById("chatbot-auto")) {
    const wrapper = document.createElement("div");
    wrapper.id = "chatbot-auto";
    wrapper.innerHTML = `
      <!-- Modal nhập thông tin khách hàng -->
      <div id="customerInfoModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;justify-content:center;align-items:center;">
        <div style="background:white;padding:20px;border-radius:8px;width:300px;max-width:90%;">
          <h3 style="margin:0 0 15px 0;color:#28a745;">Thông tin để chat</h3>
          <div style="margin-bottom:10px;">
            <input id="modalCustomerName" placeholder="Họ tên" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" />
          </div>
          <div style="margin-bottom:15px;">
            <input id="modalCustomerContact" placeholder="Email hoặc SĐT" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" />
          </div>
          <div style="display:flex;gap:10px;">
            <button id="modalSubmitBtn" style="flex:1;padding:8px;background:#28a745;color:white;border:none;border-radius:4px;cursor:pointer;">Bắt đầu chat</button>
            <button id="modalCancelBtn" style="flex:1;padding:8px;background:#ccc;color:black;border:none;border-radius:4px;cursor:pointer;">Hủy</button>
          </div>
        </div>
      </div>

      <div id="chatbotAutoToggle">💬</div>
      <div id="chatbotAutoWindow" style="display:none;flex-direction:column">
        <div id="chatbotAutoHeader" style="padding:10px;background:#28a745;color:white;display:flex;justify-content:space-between;align-items:center">
          <span id="customerInfoDisplay"></span>
          <button id="closeAutoChat" style="background:transparent;border:1px solid white;color:white;padding:2px 8px;cursor:pointer;border-radius:3px;font-size:12px">×</button>
        </div>
        <div id="autoMessages" style="flex:1;overflow-y:auto;padding:10px;max-height:300px;"></div>
        <div class="autoControls" style="padding:10px;border-top:1px solid #eee;">
          <input id="autoChatInput" placeholder="Nhắn tin với quản trị viên..." style="width:80%;padding:8px;border:1px solid #ccc;border-radius:4px;" />
          <button id="autoSendBtn" style="width:15%;padding:8px;background:#28a745;color:white;border:none;border-radius:4px;cursor:pointer;">Gửi</button>
        </div>
      </div>
    `;
    document.body.appendChild(wrapper);
  }

  const toggle = document.getElementById("chatbotAutoToggle");
  const chatWindow = document.getElementById("chatbotAutoWindow");
  const messages = document.getElementById("autoMessages");
  const sendBtn = document.getElementById("autoSendBtn");
  const input = document.getElementById("autoChatInput");
  const closeBtn = document.getElementById("closeAutoChat");
  const customerInfoDisplay = document.getElementById("customerInfoDisplay");

  // Modal elements
  const customerInfoModal = document.getElementById("customerInfoModal");
  const modalCustomerName = document.getElementById("modalCustomerName");
  const modalCustomerContact = document.getElementById("modalCustomerContact");
  const modalSubmitBtn = document.getElementById("modalSubmitBtn");
  const modalCancelBtn = document.getElementById("modalCancelBtn");

  let lastMessageId = 0;
  let pollingInterval = null;
  let customerInfo = null;

  // Hàm cập nhật hiển thị thông tin khách hàng
  function updateCustomerDisplay() {
    if (customerInfo) {
      const displayName = customerInfo.isLoggedIn ?
        `Chat với ${customerInfo.name}` :
        `Chat với ${customerInfo.name}`;
      customerInfoDisplay.textContent = displayName;
    }
  }

  // Hàm thêm tin nhắn
  function addMessage(text, isUser = false) {
    const messageDiv = document.createElement("div");
    messageDiv.style.cssText = `
      margin: 8px 0;
      padding: 8px 12px;
      border-radius: 8px;
      max-width: 80%;
      word-wrap: break-word;
    `;

    if (isUser) {
      messageDiv.style.cssText += `
        background: #28a745;
        color: white;
        margin-left: auto;
        text-align: right;
      `;
    } else {
      messageDiv.style.cssText += `
        background: #f1f1f1;
        color: #333;
        margin-right: auto;
      `;
    }

    messageDiv.innerHTML = text;
    messages.appendChild(messageDiv);
    messages.scrollTop = messages.scrollHeight;
  }

  // Hàm cập nhật hiển thị thông tin khách
  function updateCustomerDisplay() {
    const info = getCustomerInfo();
    if (info && customerInfoDisplay) {
      customerInfoDisplay.textContent = info.name;
    }
  }

  // Lấy thông tin khách hàng từ localStorage hoặc prompt
  function getCustomerInfo() {
    if (customerInfo) return customerInfo;

    // Kiểm tra xem user đã đăng nhập chưa (qua PHP session)
    if (typeof window.userLoggedIn !== 'undefined' && window.userLoggedIn) {
      customerInfo = {
        name: window.loggedUsername || 'User',
        contact: window.loggedUserId || '',
        isLoggedIn: true
      };
      updateCustomerDisplay();
      return customerInfo;
    }

    // Kiểm tra localStorage
    const saved = localStorage.getItem('chat_customer_info');
    if (saved) {
      customerInfo = JSON.parse(saved);
      updateCustomerDisplay();
      return customerInfo;
    }

    // Hiển thị modal nhập thông tin
    return new Promise((resolve, reject) => {
      customerInfoModal.style.display = 'flex';

      modalSubmitBtn.onclick = function() {
        const name = modalCustomerName.value.trim();
        const contact = modalCustomerContact.value.trim();

        if (!name || !contact) {
          alert('Vui lòng nhập đầy đủ thông tin!');
          return;
        }

        customerInfo = { name: name, contact: contact, isLoggedIn: false };
        localStorage.setItem('chat_customer_info', JSON.stringify(customerInfo));

        // Ẩn modal
        customerInfoModal.style.display = 'none';
        modalCustomerName.value = '';
        modalCustomerContact.value = '';

        updateCustomerDisplay();
        resolve(customerInfo);
      };

      modalCancelBtn.onclick = function() {
        customerInfoModal.style.display = 'none';
        modalCustomerName.value = '';
        modalCustomerContact.value = '';
        reject(new Error('User cancelled'));
      };
    });
  }

  // Hàm gửi tin nhắn
  // Hàm gửi tin nhắn
  async function sendMessage() {
    const text = input.value.trim();
    if (!text) return;

    try {
      const info = await getCustomerInfo();
      if (!info) return;

      addMessage(text, true);
      input.value = "";

      fetch("/chat_api_manual.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
          action: "send_message",
          message: text,
          customer_name: info.name,
          customer_contact: info.contact
        })
      })
      .then(function(resp) {
        if (!resp.ok) {
          throw new Error("HTTP " + resp.status);
        }
        return resp.json();
      })
      .then(function(data) {
        if (data.success) {
          // Chỉ hiển thị thông báo tự động nếu admin chưa từng trả lời
          if (data.show_auto_message) {
            addMessage("<b>Hệ thống:</b> Đã gửi! Admin sẽ trả lời sớm.", false);
          }
          lastMessageId = data.message_id;

          if (!pollingInterval) {
            startPolling();
          }
        } else {
          addMessage("<b>Lỗi:</b> Không thể gửi tin nhắn. Vui lòng thử lại.", false);
        }
      })
      .catch(function(error) {
        console.error("Chat error:", error);
        addMessage("<b>Lỗi:</b> Có lỗi xảy ra. Vui lòng thử lại sau.", false);
      });
    } catch (error) {
      console.error("Get customer info error:", error);
      // Không hiển thị lỗi nếu user cancel modal
    }
  }

  // Hàm load lịch sử chat
  // Hàm load lịch sử chat
  async function loadChatHistory() {
    try {
      const info = await getCustomerInfo();
      if (!info) return;

      fetch("/chat_api_manual.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
          action: "get_messages",
          customer_contact: info.contact
        })
      })
      .then(function(resp) { return resp.json(); })
      .then(function(data) {
        if (data.success && data.messages.length > 0) {
          messages.innerHTML = "";
          data.messages.forEach(function(msg) {
            const isUser = msg.from === "customer";
            const label = isUser ? "Bạn" : "Admin";
            addMessage("<b>" + label + ":</b> " + escapeHtml(msg.message), isUser);
            lastMessageId = Math.max(lastMessageId, msg.id);
          });
        } else {
          addMessage("<b>Hệ thống:</b> Xin chào! Hãy gửi câu hỏi, admin sẽ trả lời bạn.", false);
        }
      })
      .catch(function(err) {
        console.error("Load history error:", err);
      });
    } catch (error) {
      console.error("Get customer info error:", error);
    }
  }

  // Hàm bắt đầu polling
  function startPolling() {
    if (pollingInterval) return;

    pollingInterval = setInterval(function() {
      fetch("/chat_api_manual.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
          action: "check_new_replies",
          last_message_id: lastMessageId
        })
      })
      .then(function(resp) { return resp.json(); })
      .then(function(data) {
        if (data.success && data.new_messages.length > 0) {
          data.new_messages.forEach(function(msg) {
            addMessage("<b>Admin:</b> " + escapeHtml(msg.message), false);
            lastMessageId = Math.max(lastMessageId, msg.id);
          });
        }
      })
      .catch(function(err) {
        console.error("Polling error:", err);
      });
    }, 3000);
  }

  // Hàm dừng polling
  function stopPolling() {
    if (pollingInterval) {
      clearInterval(pollingInterval);
      pollingInterval = null;
    }
  }

  // Hàm escape HTML
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Event listeners
  toggle.addEventListener("click", function() {
    if (chatWindow.style.display === "flex") {
      chatWindow.style.display = "none";
      stopPolling();
    } else {
      chatWindow.style.display = "flex";
      updateCustomerDisplay();
      input.focus();
      loadChatHistory();
      startPolling();
    }
  });

  closeBtn.addEventListener("click", function() {
    chatWindow.style.display = "none";
    stopPolling();
  });

  sendBtn.addEventListener("click", sendMessage);

  input.addEventListener("keypress", function(e) {
    if (e.key === "Enter") {
      sendMessage();
    }
  });
});