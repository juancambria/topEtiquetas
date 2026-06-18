<?php

require_once __DIR__ . "/../Comandos/comandosZebra.php";

if (!method_exists('comandosZebra', 'crearSegunConfiguracion')) {
    die(
        "Error: comandosZebra.php desactualizado en este equipo.\n"
        . "Copie la version completa de Red/Comandos/comandosZebra.php (debe incluir crearSegunConfiguracion).\n"
    );
}

//parametros CLI
$codigo   = isset($argv[1]) ? $argv[1] : null;
$cantidad = isset($argv[2]) ? (int)$argv[2] : 1;

$modoImpresion = 'auto';

for ($i = 3; isset($argv[$i]); $i++) {

    $argModo = strtolower(trim($argv[$i]));

    if ($argModo === 'usb' || $argModo === 'red' || $argModo === 'auto') {
        $modoImpresion = $argModo;
        break;
    }

    if ($argModo === '--usb') {
        $modoImpresion = 'usb';
        break;
    }

    if ($argModo === '--red') {
        $modoImpresion = 'red';
        break;
    }

    if ($argModo === '--auto') {
        $modoImpresion = 'auto';
        break;
    }

    if (strpos($argModo, '--modo=') === 0) {

        $valor = substr($argModo, 7);

        if ($valor === 'usb' || $valor === 'red' || $valor === 'auto') {
            $modoImpresion = $valor;
            break;
        }
    }
}

if (!$codigo) {
    die("❌ Falta el código del corte\n");
}

if ($cantidad <= 0) {
    die("❌ Cantidad inválida\n");
}

//descripcion que se mostrara en la etiqueta segun codigo interno
$descripcionCortes = array(

    "813197" => "Tapa de nalga",
    "813146" => "Colita de cuadril",
    "813144" => "Lomo",
    "813147" => "Peceto",
    "813196" => "Picaña",
    "813145" => "Ojo de bife",
    "813195" => "Bife angosto",
    "813090" => "Bola de lomo",
    "850001" => "Costilla",
    "813091" => "Cuadrada",
    "813092" => "Cuadril",
    "850002" => "Entraña",
    "850003" => "Falda deshuesada",
    "850004" => "Matambre",
    "813089" => "Nalga",
    "850005" => "Tapa de asado",
    "850006" => "Vacio",

);

if (!isset($descripcionCortes[$codigo])) {
    die("❌ Código de corte no encontrado\n");
}

$corte = function_exists('mb_strtoupper')
    ? mb_strtoupper($descripcionCortes[$codigo], 'UTF-8')
    : strtoupper($descripcionCortes[$codigo]);

//senasa por corte
$senasaPorCorte = array(

    "813197" => "4292/128037/9",
    "813146" => "4292/128037/2",
    "813144" => "4292/128037/5",
    "813147" => "4292/128037/8",
    "813196" => "4292/128037/12",
    "813145" => "4292/128037/14",
    "813195" => "4292/128037/13",
    "813090" => "4292/128037/1",
    "850001" => "4292/128037/18",
    "813091" => "4292/128037/3",
    "813092" => "4292/128037/4",
    "850002" => "4292/128037/15",
    "850003" => "4292/128037/16",
    "850004" => "4292/128037/6",
    "813089" => "4292/128037/7",
    "850005" => "4292/128037/17",
    "850006" => "4292/128037/10",

);

if (!isset($senasaPorCorte[$codigo])) {
    die("❌ SENASA no definido para este corte\n");
}

$senasa = $senasaPorCorte[$codigo];

// dias de vencimiento por codigo (los no listados usan 30 dias)
$diasVencimientoPorCorte = array(

    "813197" => 60,  // Tapa de nalga
    "813146" => 60,  // Colita de cuadril
    "813144" => 60,  // Lomo
    "813147" => 60,  // Peceto
    "813196" => 60,  // Picaña
    "813090" => 60,  // Bola de lomo
    "813091" => 60,  // Cuadrada
    "813092" => 60,  // Cuadril
    "813089" => 60,  // Nalga

);

$diasVencimiento = isset($diasVencimientoPorCorte[$codigo])
    ? (int) $diasVencimientoPorCorte[$codigo]
    : 30;

//configuracion de fechas
$hoy = new DateTime();

$fechaEnvasado = $hoy->format("d/m/y");

$fechaVencObj = clone $hoy;
$fechaVencObj->modify("+{$diasVencimiento} days");

$fechaVenc = $fechaVencObj->format("d/m/y");

// lote
// Dejar vacío para contador automático. Ej: "015" fuerza solo los 3 dígitos del lote.
$numeroLoteForzado = "";

$ddmm = $hoy->format("dm");

$anobase = 2026;
$letra = chr(ord('A') + ($hoy->format("Y") - $anobase));

if ($numeroLoteForzado !== "") {

    $contador = str_pad($numeroLoteForzado, 3, "0", STR_PAD_LEFT);

} else {

    $ruta = __DIR__ . "/lote_estado.json";

    // leer estado previo
    if (file_exists($ruta)) {

        $estado = json_decode(file_get_contents($ruta), true);

    } else {

        $estado = array(
            "ultimo_dia" => null,
            "contador" => 0
        );
    }

    // si cambia el dia
    if ($estado["ultimo_dia"] !== $ddmm) {

        $estado["contador"]++;
        $estado["ultimo_dia"] = $ddmm;
    }

    // guardar estado
    file_put_contents($ruta, json_encode($estado));

    // contador formateado
    $contador = str_pad($estado["contador"], 3, "0", STR_PAD_LEFT);
}

// lote final
$lote = $ddmm . $letra . "-" . $contador;

//datos generales
$datos = array(

    "corte"       => $corte,
    "envasado"    => $fechaEnvasado,
    "vencimiento" => $fechaVenc,
    "senasa"      => $senasa,
    "lote"        => $lote,

);

//crear impresora
try {

    $zebra = comandosZebra::crearSegunConfiguracion($modoImpresion);
} catch (Exception $e) {

    die("❌ Impresora: " . $e->getMessage() . "\n");
}

echo "→ Imprimiendo etiquetas de carne\n";
echo "Corte: " . $datos['corte'] . "\n";
echo "Cantidad: " . $cantidad . "\n";
echo "Modo impresión: " . $modoImpresion . "\n\n";

// correccion por deriva vertical al imprimir varias etiquetas seguidas
$correccionYporEtiqueta = 7;

try {

    for ($i = 0; $i < $cantidad; $i++) {

        $ajusteY = -$correccionYporEtiqueta * $i;

        $zpl  = $zebra->inicioEtiquetaCarne();



        // CORTE (textoRotadoCentrado):
        // 1) texto, 2) x (columna), 3) y (inicio vertical),
        // 4) altoColumna (área para centrar), 5) tam (tamaño fuente)
        $zpl .= $zebra->textoRotadoCentrado(
            $datos['corte'] . " X KG",
            285,
            100 + $ajusteY,
            600,
            50
        );

        // ENVASADO (textoRotado):
        // 1) texto, 2) x (posición horizontal), 3) y (posición vertical), 4) tam (fuente)
        $zpl .= $zebra->textoRotado(
            $datos['envasado'],
            190,
            185 + $ajusteY,//75 antes
            34
        );

        // VENCIMIENTO (textoRotado):
        // 1) texto, 2) x, 3) y, 4) tam
        $zpl .= $zebra->textoRotado(
            $datos['vencimiento'],
            190,
            470 + $ajusteY,//375 antes
            34
        );

        // SENASA (textoRotado):
        // 1) texto, 2) x, 3) y, 4) tam
        $zpl .= $zebra->textoRotado(
            $datos['senasa'],
            160,
            265 + $ajusteY,//160 antes
            20
        );

        // LOTE (textoRotado):
        // 1) texto, 2) x, 3) y, 4) tam
        $zpl .= $zebra->textoRotado(
            $datos['lote'],
            160,
            518 + $ajusteY,//412 antes
            20
        );

        $zpl .= $zebra->finEtiqueta();

        $zebra->envio($zpl);
    }

} catch (Exception $e) {

    die("❌ Impresora: " . $e->getMessage() . "\n");
}

echo "✔ Se imprimieron " . $cantidad . " etiquetas correctamente\n";