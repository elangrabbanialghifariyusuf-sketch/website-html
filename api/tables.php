<?php
require_once __DIR__ . "/_bootstrap.php";
api_run(function (): void {
    api_method(["GET"]);
    $floorId = filter_input(INPUT_GET, "floor_id", FILTER_VALIDATE_INT);
    if (!$floorId) {
        throw new HttpException(400, "Lantai belum dipilih.");
    }
    $rows = all_rows("SELECT t.table_id, t.table_number FROM tables_cafe t JOIN floors f ON f.floor_id = t.floor_id WHERE t.floor_id = ? AND f.is_active = 1 AND t.is_active = 1 AND t.is_available = 1 ORDER BY t.table_number", "i", [$floorId]);
    json_response(["tables" => $rows]);
});
