<?php
$url = 'http://localhost:8000';
echo "Verificando servidor en $url...\n";
$headers = @get_headers($url);
if($headers) {
    echo "✓ Servidor está respondiendo\n";
    echo "Headers:\n";
    print_r($headers);
} else {
    echo "✗ No se puede conectar al servidor\n";
    echo "Asegúrate de que el servidor Laravel esté corriendo con: php artisan serve\n";
}
