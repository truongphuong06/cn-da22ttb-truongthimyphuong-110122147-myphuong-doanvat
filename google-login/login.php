<?php
require 'config.php';

$login_url = $client->createAuthUrl();
?>

<a href="<?php echo $login_url; ?>">
    <button>Đăng nhập bằng Google</button>
</a>
