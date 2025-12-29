// Notification Bell mới cho tất cả các trang
// Tối giản, dễ nhúng, dễ bảo trì

function renderNotificationBell() {
  if (document.querySelector('.notification-bell')) return;
  const bellHtml = `
    <button class="btn btn-secondary notification-bell" id="notificationToggle" style="position:relative;">
      <i class="fas fa-bell"></i>
      <span class="notification-badge" id="notificationBadge" style="display:none;">0</span>
    </button>
    <div class="notification-list-simple" id="notificationListSimple" style="display:none;position:absolute;right:0;top:40px;z-index:9999;background:#fff;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,0.12);min-width:320px;max-width:90vw;max-height:400px;overflow:auto;padding:0.5rem 0.5rem 0.5rem 0.5rem;">
      <div class="notification-empty"><i class="fas fa-bell-slash"></i> Không có thông báo mới</div>
    </div>
  `;
  const headerActions = document.querySelector('.header-actions');
  if (headerActions) {
    headerActions.insertAdjacentHTML('beforeend', bellHtml);
  }

  const toggle = document.getElementById('notificationToggle');
  const badge = document.getElementById('notificationBadge');
  const list = document.getElementById('notificationListSimple');

  toggle.addEventListener('click', function(e) {
    e.stopPropagation();
    if (list.style.display === 'none' || !list.style.display) {
      list.style.display = 'block';
      loadNotifications();
    } else {
      list.style.display = 'none';
    }
  });
  document.addEventListener('click', function(e) {
    if (!list.contains(e.target) && !toggle.contains(e.target)) {
      list.style.display = 'none';
    }
  });

  async function loadNotifications() {
    try {
      console.log('[Notification] Loading notifications...');
      const resp = await fetch('/WebCN/notifications_api.php?action=get_notifications');
      
      // Kiểm tra response status
      if (!resp.ok) {
        throw new Error(`HTTP error! status: ${resp.status}`);
      }
      
      const data = await resp.json();
      console.log('[Notification] Response:', data);
      
      if (data.success) {
        const notifs = data.notifications || [];
        const unreadCount = data.unread_count || 0;
        console.log('[Notification] Total:', notifs.length, 'Unread:', unreadCount);
        
        // Hiển thị badge CHỈ khi có thông báo CHƯA ĐỌC
        if (unreadCount > 0) {
          badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
          badge.style.display = 'block';
        } else {
          badge.style.display = 'none';
        }
        
        if (notifs.length > 0) {
          // Thêm nút "Đánh dấu tất cả đã đọc" và "Xóa đã đọc"
          let html = '<div style="padding:8px;border-bottom:2px solid #007bff;display:flex;justify-content:space-between;align-items:center;background:#f8f9fa;">';
          html += '<strong style="font-size:14px;">Thông báo</strong>';
          html += '<div style="display:flex;gap:6px;">';
          if (unreadCount > 0) {
            html += '<button onclick="markAllRead()" style="padding:4px 10px;background:#007bff;color:white;border:none;border-radius:4px;cursor:pointer;font-size:11px;">Đánh dấu tất cả đã đọc</button>';
          }
          // Hiện nút xóa đã đọc nếu có thông báo đã đọc
          const readCount = notifs.length - unreadCount;
          if (readCount > 0) {
            html += '<button onclick="hideReadNotifications()" style="padding:4px 10px;background:#dc3545;color:white;border:none;border-radius:4px;cursor:pointer;font-size:11px;">Xóa đã đọc</button>';
          }
          html += '</div></div>';
          
          // Hiển thị danh sách thông báo
          html += notifs.map(n => {
            const isRead = n.is_read == 1 || n.is_read == true;
            const bgColor = isRead ? '#ffffff' : '#e3f2fd';
            const fontWeight = isRead ? 'normal' : '600';
            
            return `
              <div class="notification-item-simple" 
                   data-id="${n.id}"
                   style="padding:10px;border-bottom:1px solid #eee;background:${bgColor};position:relative;display:flex;align-items:start;gap:8px;">
                <div style="flex:1;cursor:pointer;" onclick="markReadAndGo(${n.id}, '${n.link || '#'}')">
                  <div style="font-weight:${fontWeight};font-size:15px;">${n.title}</div>
                  <div style="font-size:13px;color:#666;">${n.message}</div>
                  <div style="font-size:12px;color:#aaa;">${formatTime(n.created_at)}</div>
                </div>
                <button onclick="hideNotification(${n.id}); event.stopPropagation();" 
                        style="background:transparent;border:none;color:#999;font-size:18px;cursor:pointer;padding:0;width:24px;height:24px;line-height:24px;flex-shrink:0;"
                        title="Xóa thông báo">×</button>
              </div>
            `;
          }).join('');
          
          list.innerHTML = html;
        } else {
          badge.style.display = 'none';
          list.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i> Không có thông báo mới</div>';
        }
      } else {
        console.error('[Notification] API returned error:', data.error);
        badge.style.display = 'none';
        list.innerHTML = '<div class="notification-empty">Lỗi: ' + (data.error || 'Unknown error') + '</div>';
      }
    } catch (err) {
      console.error('[Notification] Load failed:', err);
      badge.style.display = 'none';
      list.innerHTML = '<div class="notification-empty">Lỗi tải thông báo: ' + err.message + '</div>';
    }
  }

  // Đánh dấu đã đọc và chuyển trang
  window.markReadAndGo = async function(notificationId, link) {
    try {
      // Gọi API đánh dấu đã đọc
      const formData = new FormData();
      formData.append('notification_id', notificationId);
      
      await fetch('/WebCN/notifications_api.php?action=mark_read', {
        method: 'POST',
        body: formData
      });
      
      // Reload danh sách thông báo
      loadNotifications();
      
      // Chuyển trang nếu có link
      if (link && link !== '#') {
        window.location.href = link;
      }
    } catch (err) {
      console.error('Mark read error:', err);
      // Vẫn chuyển trang dù lỗi
      if (link && link !== '#') {
        window.location.href = link;
      }
    }
  };

  // Đánh dấu tất cả đã đọc
  window.markAllRead = async function() {
    try {
      const resp = await fetch('/WebCN/notifications_api.php?action=mark_all_read', {
        method: 'POST'
      });
      
      if (resp.ok) {
        loadNotifications();
      }
    } catch (err) {
      console.error('Mark all read error:', err);
    }
  };

  // Ẩn 1 thông báo
  window.hideNotification = async function(notificationId) {
    try {
      const formData = new FormData();
      formData.append('notification_id', notificationId);
      
      await fetch('/WebCN/notifications_api.php?action=hide_notification', {
        method: 'POST',
        body: formData
      });
      
      // Reload danh sách thông báo
      loadNotifications();
    } catch (err) {
      console.error('Hide notification error:', err);
    }
  };

  // Xóa tất cả thông báo đã đọc
  window.hideReadNotifications = async function() {
    if (!confirm('Xóa tất cả thông báo đã đọc?')) return;
    
    try {
      const resp = await fetch('/WebCN/notifications_api.php?action=hide_read_notifications', {
        method: 'POST'
      });
      
      if (resp.ok) {
        loadNotifications();
      }
    } catch (err) {
      console.error('Hide read notifications error:', err);
    }
  };

  function formatTime(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'Vừa xong';
    if (diff < 3600) return Math.floor(diff / 60) + ' phút trước';
    if (diff < 86400) return Math.floor(diff / 3600) + ' giờ trước';
    if (diff < 2592000) return Math.floor(diff / 86400) + ' ngày trước';
    return date.toLocaleDateString('vi-VN');
  }

  // Tải thông báo mỗi 10 giây
  loadNotifications();
  setInterval(loadNotifications, 10000);
}

document.addEventListener('DOMContentLoaded', renderNotificationBell);
