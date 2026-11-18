<?php
declare(strict_types=1);

/**
 * Satu pintu untuk semua API:  api.php?r=<resource>[&id=..]
 * Respons selalu JSON: { "ok": true, "data": ... } atau { "ok": false, "error": "pesan" }.
 */

use App\Support\Auth;
use App\Support\HttpError;
use App\Support\Input;

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $route = trim((string) ($_GET['r'] ?? ''), '/');
    $routes = require __DIR__ . '/../src/Api/routes.php';
    $key = "$method $route";

    if (!isset($routes[$key])) {
        $known = array_filter(array_keys($routes), static fn ($k) => str_ends_with($k, " $route"));
        throw $known ? new HttpError(405, 'Metode tidak diizinkan') : HttpError::notFound('Endpoint tidak ditemukan');
    }
    [$access, $handler] = $routes[$key];

    Auth::start();
    if ($method !== 'GET') {
        Auth::checkCsrf();
    }
    $user = $access === 'public' ? Auth::user() : Auth::require($access === 'admin' ? 'admin' : null);

    $body = [];
    if ($method !== 'GET') {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_starts_with($type, 'application/json')) {
            $raw = file_get_contents('php://input');
            $body = $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($body)) {
                throw HttpError::badRequest('Body JSON harus berupa objek');
            }
        } else {
            $body = $_POST;
        }
    }

    $data = $handler(new Input($_GET), new Input($body), $user);
    respond(200, ['ok' => true, 'data' => $data]);
} catch (HttpError $e) {
    respond($e->status, ['ok' => false, 'error' => $e->getMessage()]);
} catch (JsonException) {
    respond(400, ['ok' => false, 'error' => 'Body JSON tidak valid']);
} catch (PDOException $e) {
    error_log('[toko-sembako] ' . $e);
    if (($e->errorInfo[0] ?? '') === '23000') {
        respond(409, ['ok' => false, 'error' => 'Data masih dipakai atau bertabrakan dengan data lain']);
    }
    respond(500, ['ok' => false, 'error' => 'Terjadi kesalahan database' . (app_config()['debug'] ? ': ' . $e->getMessage() : '')]);
} catch (Throwable $e) {
    error_log('[toko-sembako] ' . $e);
    respond(500, ['ok' => false, 'error' => 'Terjadi kesalahan server' . (app_config()['debug'] ? ': ' . $e->getMessage() : '')]);
}
