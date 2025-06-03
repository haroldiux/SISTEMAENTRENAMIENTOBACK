<?php

// Script simple para debuggear la API
echo "=== DEBUG API DASHBOARD ===\n\n";

// Verificar si el servidor responde
echo "1. Verificando conectividad básica...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "✗ Error de conexión: $error\n";
    echo "Asegúrate de que el servidor Laravel esté ejecutándose con: php artisan serve\n";
    exit(1);
} else {
    echo "✓ Servidor responde (HTTP $httpCode)\n\n";
}

// Probar ruta de login
echo "2. Probando ruta de login...\n";
$loginData = json_encode([
    'user' => '9876543',
    'password' => 'admin1234'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$loginResponse = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$loginError = curl_error($ch);
curl_close($ch);

if ($loginError) {
    echo "✗ Error en login: $loginError\n";
    exit(1);
}

echo "Status login: $loginHttpCode\n";
echo "Respuesta login: $loginResponse\n\n";

$loginData = json_decode($loginResponse, true);

if ($loginHttpCode === 200 && isset($loginData['token'])) {
    $token = $loginData['token'];
    echo "✓ Login exitoso, token obtenido\n\n";

    // Probar dashboard
    echo "3. Probando dashboard...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/dashboard');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $dashboardResponse = curl_exec($ch);
    $dashboardHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $dashboardError = curl_error($ch);
    curl_close($ch);

    if ($dashboardError) {
        echo "✗ Error en dashboard: $dashboardError\n";
    } else {
        echo "Status dashboard: $dashboardHttpCode\n";
        echo "Respuesta dashboard: $dashboardResponse\n";

        if ($dashboardHttpCode === 200) {
            echo "✓ Dashboard funciona correctamente\n";
        } else {
            echo "✗ Dashboard retorna error\n";
        }
    }
} else {
    echo "✗ Login falló\n";
    echo "Verifica las credenciales o la configuración de la base de datos\n";
}

echo "\n=== FIN DEBUG ===\n";
