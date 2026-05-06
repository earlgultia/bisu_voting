<?php
if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'] ?? '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'voting_db';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die('Connection failed.');
}

mysqli_set_charset($conn, 'utf8mb4');

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('normalize_position_label')) {
    function normalize_position_label($value): string
    {
        $position = strtoupper(trim((string) $value));
        $position = preg_replace('/^(SSG|FTP)\s*[-:]?\s*/', '', $position);
        $position = str_replace(['-', '_'], ' ', $position);
        $position = preg_replace('/\s+/', ' ', $position);
        return strtoupper($position);
    }
}

if (!function_exists('candidate_position_sql_normalized')) {
    function candidate_position_sql_normalized(string $column): string
    {
        $expression = 'UPPER(TRIM(' . $column . '))';
        $expression = 'REPLACE(' . $expression . ", 'SSG ', '')";
        $expression = 'REPLACE(' . $expression . ", 'FTP ', '')";
        $expression = 'REPLACE(' . $expression . ", 'SSG-', '')";
        $expression = 'REPLACE(' . $expression . ", 'FTP-', '')";
        $expression = 'REPLACE(' . $expression . ", ' - ', ' ')";
        $expression = 'REPLACE(' . $expression . ", '-', ' ')";
        $expression = 'TRIM(REPLACE(REPLACE(' . $expression . ", '  ', ' '), '  ', ' '))";

        return $expression;
    }
}

if (!function_exists('candidate_position_rank')) {
    function candidate_position_rank(string $electionType, string $position): int
    {
        static $orders = [
            'SSG' => [
                'PRESIDENT' => 0,
                'VICE PRESIDENT' => 1,
                'SENATORS' => 2,
                'GOVERNORS' => 3,
                'VICE GOVERNORS' => 4,
                'CONGRESSMEN/WOMEN' => 5,
            ],
            'FTP' => [
                'PRESIDENT' => 0,
                'VICE PRESIDENT' => 1,
                'AUDITOR' => 2,
                'EXECUTIVE SECRETARY' => 3,
                'SECRETARY OF HEALTH & SANITATION' => 4,
                'SKILLS AND TRAINING' => 5,
                'BUDGET & FINANCE' => 6,
                'REPRESENTATIVE' => 7,
                'INDEPENDENT' => 8,
            ],
        ];

        $electionKey = normalize_position_label($electionType);
        $positionKey = normalize_position_label($position);

        return $orders[$electionKey][$positionKey] ?? 999;
    }
}

if (!function_exists('candidate_position_order_sql')) {
    function candidate_position_order_sql(?string $electionColumn = 'election_type', string $positionColumn = 'position'): string
    {
        static $orders = [
            'SSG' => [
                'PRESIDENT',
                'VICE PRESIDENT',
                'SENATORS',
                'GOVERNORS',
                'VICE GOVERNORS',
                'CONGRESSMEN/WOMEN',
            ],
            'FTP' => [
                'PRESIDENT',
                'VICE PRESIDENT',
                'AUDITOR',
                'EXECUTIVE SECRETARY',
                'SECRETARY OF HEALTH & SANITATION',
                'SKILLS AND TRAINING',
                'BUDGET & FINANCE',
                'REPRESENTATIVE',
                'INDEPENDENT',
            ],
        ];

        $buildCase = function (string $column, array $labels): string {
            $case = 'CASE ' . candidate_position_sql_normalized($column) . ' ';
            foreach ($labels as $rank => $label) {
                $case .= 'WHEN ' . var_export($label, true) . ' THEN ' . (int) $rank . ' ';
            }
            $case .= 'ELSE 999 END';
            return $case;
        };

        if ($electionColumn === null || $electionColumn === '') {
            return $buildCase($positionColumn, $orders['SSG']);
        }

        return 'CASE '
            . 'WHEN UPPER(TRIM(' . $electionColumn . ')) = \'' . 'SSG' . '\' THEN ' . $buildCase($positionColumn, $orders['SSG']) . ' '
            . 'WHEN UPPER(TRIM(' . $electionColumn . ')) = \'' . 'FTP' . '\' THEN ' . $buildCase($positionColumn, $orders['FTP']) . ' '
            . 'ELSE ' . $buildCase($positionColumn, $orders['SSG']) . ' '
            . 'END';
    }
}
?>