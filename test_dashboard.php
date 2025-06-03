<?php

// Script de prueba para verificar el endpoint del dashboard
// Ejecutar con: php test_dashboard.php

$baseUrl = 'http://localhost:8000/api';

// Función para hacer peticiones HTTP
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

echo "=== PRUEBA DEL ENDPOINT DASHBOARD ===\n\n";

// 1. Probar login con usuario docente
echo "1. Probando login con usuario docente...\n";
$loginData = [
    'user' => '9876543',
    'password' => 'admin1234'
];

$loginResponse = makeRequest($baseUrl . '/login', 'POST', $loginData);
echo "Status: " . $loginResponse['status'] . "\n";

if ($loginResponse['status'] === 200 && isset($loginResponse['body']['token'])) {
    $token = $loginResponse['body']['token'];
    $user = $loginResponse['body']['user'];

    echo "✓ Login exitoso\n";
    echo "Token: " . substr($token, 0, 20) . "...\n";
    echo "Usuario: " . $user['name'] . " (Rol: " . $user['role'] . ")\n\n";

    // 2. Probar endpoint dashboard
    echo "2. Probando endpoint dashboard...\n";
    $dashboardResponse = makeRequest(
        $baseUrl . '/dashboard',
        'GET',
        null,
        ['Authorization: Bearer ' . $token]
    );

    echo "Status: " . $dashboardResponse['status'] . "\n";

    if ($dashboardResponse['status'] === 200) {
        echo "✓ Dashboard cargado exitosamente\n";
        echo "Datos recibidos:\n";
        print_r($dashboardResponse['body']);
    } else {
        echo "✗ Error en dashboard\n";
        echo "Respuesta: " . $dashboardResponse['raw'] . "\n";
    }

} else {
    echo "✗ Error en login\n";
    echo "Respuesta: " . $loginResponse['raw'] . "\n";
}

echo "\n=== FIN DE PRUEBAS ===\n";
