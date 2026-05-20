<?php
// conexionBD.php

$host = "192.168.10.204";
$dbname = "ventas";
$user = "soporte";
$pass = "soportedesarrollo975";

try {
    $dsn = "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8";

    $pdo = new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ));

} catch (PDOException $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}