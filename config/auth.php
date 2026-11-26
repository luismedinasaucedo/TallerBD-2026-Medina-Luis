<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no hay sesión activa, regresar al login
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

// Función auxiliar para saber si el usuario actual es administrador
function esAdmin(): bool {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador';
}

// Bloquea modificaciones (POST) si el usuario solo tiene rol 'consulta'
function exigirAdmin(): void {
    if (!esAdmin()) {
        die('Acceso denegado: tu perfil es de solo consulta.');
    }
}