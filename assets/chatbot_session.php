<?php
// Inject user session data to JavaScript for chatbot
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<script>
// User login information for chatbot
window.userLoggedIn = <?php echo (!empty($_SESSION['user_id']) || !empty($_SESSION['username'])) ? 'true' : 'false'; ?>;
window.loggedUsername = <?php echo json_encode($_SESSION['username'] ?? $_SESSION['email'] ?? ''); ?>;
window.loggedUserId = <?php echo json_encode($_SESSION['user_id'] ?? ''); ?>;
</script>
