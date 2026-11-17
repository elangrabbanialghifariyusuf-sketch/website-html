<?php
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    http_response_code(405);
    exit("Gunakan formulir di register.php.");
}
header("Location: register.php", true, 302);
exit;
