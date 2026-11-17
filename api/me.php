<?php
require_once __DIR__ . "/_bootstrap.php";
api_run(function (): void {
    api_method(["GET"]);
    require_login(true);
    $user = one("SELECT user_id, username, nama, nomor_hp, role FROM login WHERE user_id = ? AND is_active = 1", "i", [(int) $_SESSION["user_id"]]);
    if (!$user) {
        throw new HttpException(401, "Sesi pengguna tidak berlaku.");
    }
    json_response(["user" => $user]);
});
