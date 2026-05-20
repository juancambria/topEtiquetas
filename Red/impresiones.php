<?php

echo "===== SISTEMA DE IMPRESIONES =====\n";
echo "Sucursales disponibles: 01, 02, 18, 19, 25, 99\n\n";

// 1) INGRESAR SUCURSAL
$sucursal = trim(readline("Ingrese sucursal: "));

if (!in_array($sucursal, array("01", "02", "18", "19", "25", "99"))) {
    die("Sucursal inválida.\n");
}

// 2) DETERMINAR OPCIONES DISPONIBLES
$soloEmpanadas     = array("01", "02", "18");
$empanadasYPicadas = array("19", "25");
$soloCarnes        = array("99");

if (in_array($sucursal, $soloEmpanadas)) {

    echo "\nSucursal $sucursal permite imprimir SOLO EMPANADAS.\n";
    $tipo = "empanada";

} elseif (in_array($sucursal, $soloCarnes)) {

    echo "\nSucursal $sucursal permite imprimir SOLO CARNES.\n";
    $tipo = "carne";

} else {

    echo "\nSucursal $sucursal permite imprimir EMPANADAS y PICADAS.\n";
    echo "Seleccione tipo:\n";
    echo "1 - Empanadas\n";
    echo "2 - Picadas\n";

    $op = trim(readline("Opción: "));

    if ($op == "1") {
        $tipo = "empanada";
    } elseif ($op == "2") {
        $tipo = "picada";
    } else {
        die("Opción inválida.\n");
    }
}

// 3) PEDIR CÓDIGO Y VALIDAR SEGÚN TIPO
$codigo = trim(readline("\nIngrese código del producto: "));

if (!$codigo) {
    die("Debe ingresar un código.\n");
}

// Reglas de validación
$esPicada   = substr($codigo, 0, 3) == "817";
$esEmpanada = substr($codigo, 0, 3) == "835";
$esCarne    = substr($codigo, 0, 3) == "813" || substr($codigo, 0, 3) == "850";

// Validaciones por tipo
if ($tipo == "picada" && !$esPicada) {
    die("ERROR: Ese código no corresponde a PICADAS.\n");
}

if ($tipo == "empanada" && !$esEmpanada) {
    die("ERROR: Ese código no corresponde a EMPANADAS.\n");
}

if ($tipo == "carne" && !$esCarne) {
    die("ERROR: Ese código no corresponde a CARNES.\n");
}

// Validación: sucursal que NO debe imprimir picadas
if (in_array($sucursal, $soloEmpanadas) && $esPicada) {
    die("ERROR: En esta sucursal NO se pueden imprimir PICADAS.\n");
}

// Validación: sucursal 99 solo carnes
if (in_array($sucursal, $soloCarnes) && !$esCarne) {
    die("ERROR: En esta sucursal SOLO se imprimen CARNES.\n");
}

// 4) PEDIR CANTIDAD
$cantidad = (int) trim(readline("Ingrese cantidad de etiquetas: "));

if ($cantidad <= 0) {
    die("Cantidad inválida.\n");
}

// 5) LLAMAR AL SCRIPT CORRESPONDIENTE
echo "\nGenerando impresión...\n";

if ($tipo == "empanada") {

    system("php Modelos/impresionEmpanadasCLI.php $codigo $cantidad $sucursal");

} elseif ($tipo == "picada") {

    system("php Modelos/impresionPicadasCLI.php $codigo $cantidad $sucursal");

} else {

    system("php Modelos/impresionCarnesCLI.php $codigo $cantidad $sucursal");
}

echo "\nProceso finalizado.\n";