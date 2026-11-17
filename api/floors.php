<?php
require_once __DIR__ . "/_bootstrap.php";
api_run(function (): void {
    api_method(["GET"]);
    $rows = all_rows("SELECT floor_id, name FROM floors WHERE is_active = 1 ORDER BY floor_id");
    json_response(["floors" => $rows]);
});
