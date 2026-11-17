<?php
declare(strict_types=1);

date_default_timezone_set("Asia/Jakarta");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        "httponly" => true,
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "samesite" => "Lax",
    ]);
    session_start();
}

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

final class HttpException extends RuntimeException
{
    public int $httpStatus;

    public function __construct(int $httpStatus, string $message)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    $provided = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? ($_POST["csrf_token"] ?? "");
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        throw new HttpException(403, "Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
    }
}

function request_json(): array
{
    $raw = file_get_contents("php://input");
    $payload = json_decode($raw === false ? "" : $raw, true);
    if (!is_array($payload)) {
        throw new HttpException(400, "Format data tidak valid.");
    }
    return $payload;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_method(array $allowed): string
{
    $method = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
    if (!in_array($method, $allowed, true)) {
        header("Allow: " . implode(", ", $allowed));
        throw new HttpException(405, "Metode permintaan tidak diizinkan.");
    }
    if (in_array($method, ["POST", "PUT", "DELETE"], true)) {
        require_csrf();
    }
    return $method;
}

function require_login(bool $json = false): void
{
    if (!isset($_SESSION["user_id"])) {
        if ($json) {
            throw new HttpException(401, "Silakan masuk terlebih dahulu.");
        }
        $path = basename(parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "checkout.php");
        $target = $path === "checkout.php" ? "checkout" : "dashboard";
        header("Location: login.php?redirect=" . rawurlencode($target));
        exit;
    }
}

function require_role(array $roles): void
{
    require_login();
    $role = (string) ($_SESSION["role"] ?? "");
    if (($role === "admin" && in_array("kasir", $roles, true)) || in_array($role, $roles, true)) {
        return;
    }
    http_response_code(403);
    exit("Akses ditolak.");
}

function redirect_targets(): array
{
    return [
        "checkout" => "checkout.php",
        "menu" => "menu.php",
        "riwayat" => "riwayat.php",
        "dashboard" => "dashboard.php",
        "kasir" => "kasir/dashboard.php",
        "admin" => "admin/index.php",
    ];
}

function safe_redirect_target(?string $target): string
{
    $targets = redirect_targets();
    return $targets[$target ?? ""] ?? "dashboard.php";
}

function stmt(string $sql, string $types = "", array $values = []): mysqli_stmt
{
    global $conn;
    $statement = mysqli_prepare($conn, $sql);
    if ($types !== "") {
        $arguments = [$statement, $types];
        foreach ($values as $index => &$value) {
            $arguments[] = &$value;
        }
        unset($value);
        call_user_func_array("mysqli_stmt_bind_param", $arguments);
    }
    mysqli_stmt_execute($statement);
    return $statement;
}

function one(string $sql, string $types = "", array $values = []): ?array
{
    $result = mysqli_stmt_get_result(stmt($sql, $types, $values));
    $row = mysqli_fetch_assoc($result);
    return $row ?: null;
}

function all_rows(string $sql, string $types = "", array $values = []): array
{
    $result = mysqli_stmt_get_result(stmt($sql, $types, $values));
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function form_value(string $key, string $default = ""): string
{
    return e($_POST[$key] ?? $default);
}

function api_run(callable $handler): never
{
    try {
        $handler();
    } catch (HttpException $exception) {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        json_response(["error" => $exception->getMessage()], $exception->httpStatus);
    } catch (mysqli_sql_exception $exception) {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        error_log("Kesalahan basis data: " . $exception->getMessage());
        json_response(["error" => "Terjadi kesalahan pada server."], 500);
    }
}

function settings_map(): array
{
    $values = all_rows("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    foreach ($values as $value) {
        $settings[$value["setting_key"]] = $value["setting_value"];
    }
    return $settings;
}
