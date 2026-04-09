<?php
declare(strict_types=1);

function eventforge_password_min_length(): int
{
    return 12;
}

function eventforge_validate_new_password(string $password): array
{
    $errors = [];
    $minLength = eventforge_password_min_length();

    if ($password === '') {
        $errors[] = 'Password is required.';
        return $errors;
    }

    if (strlen($password) < $minLength) {
        $errors[] = 'Password must be at least ' . $minLength . ' characters.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    return $errors;
}

function eventforge_generate_password(int $length = 16): string
{
    $length = max(12, $length);

    $lower = 'abcdefghjkmnpqrstuvwxyz';
    $upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    $numbers = '23456789';
    $symbols = '!@#$%^&*()-_=+[]{}';
    $all = $lower . $upper . $numbers . $symbols;

    $passwordChars = [];
    $passwordChars[] = $lower[random_int(0, strlen($lower) - 1)];
    $passwordChars[] = $upper[random_int(0, strlen($upper) - 1)];
    $passwordChars[] = $numbers[random_int(0, strlen($numbers) - 1)];
    $passwordChars[] = $symbols[random_int(0, strlen($symbols) - 1)];

    while (count($passwordChars) < $length) {
        $passwordChars[] = $all[random_int(0, strlen($all) - 1)];
    }

    for ($i = count($passwordChars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $temp = $passwordChars[$i];
        $passwordChars[$i] = $passwordChars[$j];
        $passwordChars[$j] = $temp;
    }

    return implode('', $passwordChars);
}

function eventforge_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['eventforge_csrf_token'])) {
        $_SESSION['eventforge_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['eventforge_csrf_token'];
}

function eventforge_verify_csrf_token(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['eventforge_csrf_token']) || !is_string($_SESSION['eventforge_csrf_token'])) {
        return false;
    }

    if (!is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($_SESSION['eventforge_csrf_token'], $token);
}