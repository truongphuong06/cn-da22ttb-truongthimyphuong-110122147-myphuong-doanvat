// Notification Bell đơn giản
document.addEventListener("DOMContentLoaded", function() {

    if (diff < 60) return 'Vừa xong';
    if (diff < 3600) return Math.floor(diff / 60) + ' phút trước';
    if (diff < 86400) return Math.floor(diff / 3600) + ' giờ trước';
    if (diff < 2592000) return Math.floor(diff / 86400) + ' ngày trước';
    return date.toLocaleDateString('vi-VN');
  }

  // Tải thông báo mỗi 10 giây
  loadNotifications();
  setInterval(loadNotifications, 10000);
});
