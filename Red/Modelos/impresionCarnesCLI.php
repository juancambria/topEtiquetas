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
$correccionYporEtiqueta = 4;
$offsetInicialY = 0;
$modoDetectado = false;

for ($i = 3; isset($argv[$i]); $i++) {

    $argModo = strtolower(trim($argv[$i]));

    if ($argModo === 'usb' || $argModo === 'red' || $argModo === 'auto') {
        $modoImpresion = $argModo;
        $modoDetectado = true;
        continue;
    }

    if ($argModo === '--usb') {
        $modoImpresion = 'usb';
        $modoDetectado = true;
        continue;
    }

    if ($argModo === '--red') {
        $modoImpresion = 'red';
        $modoDetectado = true;
        continue;
    }

    if ($argModo === '--auto') {
        $modoImpresion = 'auto';
        $modoDetectado = true;
        continue;
    }

    if (strpos($argModo, '--modo=') === 0) {

        $valor = substr($argModo, 7);

        if ($valor === 'usb' || $valor === 'red' || $valor === 'auto') {
            $modoImpresion = $valor;
            $modoDetectado = true;
            continue;
        }
    }

    if (strpos($argModo, '--correccion=') === 0 || strpos($argModo, '--correcciony=') === 0) {
        $valorCorreccion = explode('=', $argModo, 2);
        $valorCorreccion = isset($valorCorreccion[1]) ? trim($valorCorreccion[1]) : '';
        if ($valorCorreccion !== '' && preg_match('/^-?\d+$/', $valorCorreccion)) {
            $correccionYporEtiqueta = (int) $valorCorreccion;
        }
        continue;
    }

    if ($modoDetectado && preg_match('/^-?\d+$/', $argModo)) {
        $correccionYporEtiqueta = (int) $argModo;
        continue;
    }

    if (strpos($argModo, '--offset=') === 0 || strpos($argModo, '--offsety=') === 0) {
        $valorOffset = explode('=', $argModo, 2);
        $valorOffset = isset($valorOffset[1]) ? trim($valorOffset[1]) : '';
        if ($valorOffset !== '' && preg_match('/^-?\d+$/', $valorOffset)) {
            $offsetInicialY = (int) $valorOffset;
        }
        continue;
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

$infoModo = $zebra->ultimoEnvio();
$modoEfectivo = ($modoImpresion === 'auto' && isset($infoModo['modo']) && $infoModo['modo'] !== null)
    ? $infoModo['modo']
    : $modoImpresion;

echo "→ Imprimiendo etiquetas de carne\n";
echo "Corte: " . $datos['corte'] . "\n";
echo "Cantidad: " . $cantidad . "\n";
echo "Modo impresión solicitado: " . $modoImpresion . "\n";
echo "Modo impresión efectivo: " . $modoEfectivo . "\n\n";

// correccion por deriva vertical al imprimir varias etiquetas seguidas
// Se usa la misma base para USB y RED (offset inicial 0) para mantener
// un comportamiento lo más similar posible entre modos.
// Si hace falta microcalibrar RED, usar --offset=valor.

$camposEtiqueta = array(
    'CORTE' => array('x' => 285, 'y' => 100, 'bloque' => 600, 'fuente' => 'A0R', 'tamano' => 46, 'ancho' => 46),
    'ENVASADO' => array('x' => 191, 'y' => 190, 'fuente' => 'A0R', 'tamano' => 34, 'ancho' => 34),
    'VENCIMIENTO' => array('x' => 191, 'y' => 480, 'fuente' => 'A0R', 'tamano' => 34, 'ancho' => 34),
    'SENASA' => array('x' => 161, 'y' => 270, 'fuente' => 'A0R', 'tamano' => 20, 'ancho' => 20),
    'LOTE' => array('x' => 161, 'y' => 523, 'fuente' => 'A0R', 'tamano' => 20, 'ancho' => 20),
);

try {
    $zpl  = "";

    for ($i = 0; $i < $cantidad; $i++) {

        $ajusteY = $offsetInicialY - ($correccionYporEtiqueta * $i);
        $zpl  .= $zebra->inicioEtiquetaCarne();

        // CORTE (textoRotadoCentrado):
        // 1) texto, 2) x (columna), 3) y (inicio vertical),
        // 4) altoColumna (área para centrar), 5) tam (tamaño fuente)
        $zpl .= $zebra->textoRotadoCentrado(
            $datos['corte'] . " X KG",
            $camposEtiqueta['CORTE']['x'],
            $camposEtiqueta['CORTE']['y'] + $ajusteY,
            $camposEtiqueta['CORTE']['bloque'],
            $camposEtiqueta['CORTE']['tamano'],
            $camposEtiqueta['CORTE']['ancho']
        );

        // ENVASADO (textoRotado):
        // 1) texto, 2) x (posición horizontal), 3) y (posición vertical), 4) tam (fuente)
        $zpl .= $zebra->textoRotado(
            $datos['envasado'],
            $camposEtiqueta['ENVASADO']['x'],
            $camposEtiqueta['ENVASADO']['y'] + $ajusteY,
            $camposEtiqueta['ENVASADO']['tamano'],
            $camposEtiqueta['ENVASADO']['ancho']
        );

        // VENCIMIENTO (textoRotado):
        // 1) texto, 2) x, 3) y, 4) tam
        $zpl .= $zebra->textoRotado(
            $datos['vencimiento'],
            $camposEtiqueta['VENCIMIENTO']['x'],
            $camposEtiqueta['VENCIMIENTO']['y'] + $ajusteY,
            $camposEtiqueta['VENCIMIENTO']['tamano'],
            $camposEtiqueta['VENCIMIENTO']['ancho']
        );

        // SENASA (textoRotado):
        // 1) texto, 2) x, 3) y, 4) tam
        $zpl .= $zebra->textoRotado(
            $datos['senasa'],
            $camposEtiqueta['SENASA']['x'],
            $camposEtiqueta['SENASA']['y'] + $ajusteY,
            $camposEtiqueta['SENASA']['tamano'],
            $camposEtiqueta['SENASA']['ancho']
        );

        // LOTE (textoRotado):
        // 1) texto, 2) x, 3) y, 4) tam
        $zpl .= $zebra->textoRotado(
            $datos['lote'],
            $camposEtiqueta['LOTE']['x'],
            $camposEtiqueta['LOTE']['y'] + $ajusteY,
            $camposEtiqueta['LOTE']['tamano'],
            $camposEtiqueta['LOTE']['ancho']
        );

        $zpl .= $zebra->finEtiqueta();
    }

    $zebra->envio($zpl);

} catch (Exception $e) {

    die("❌ Impresora: " . $e->getMessage() . "\n");
}

echo "✔ Se imprimieron " . $cantidad . " etiquetas correctamente\n";
echo "\n";
echo "========== DIAGNOSTICO IMPRESION ==========\n";
echo "Fecha servidor: " . date('d/m/Y H:i:s') . "\n";
echo "Script usado: " . basename(__FILE__) . "\n";
echo "Modo impresión solicitado: " . $modoImpresion . "\n";
echo "Modo impresión efectivo: " . $modoEfectivo . "\n";
$ultimoEnvio = $zebra->ultimoEnvio();
echo "IP impresora: " . ($ultimoEnvio['host'] !== null ? $ultimoEnvio['host'] : 'N/A') . "\n";
echo "Puerto impresora: " . ($ultimoEnvio['puerto'] !== null ? $ultimoEnvio['puerto'] : 'N/A') . "\n";
echo "Bytes ZPL generados: " . strlen($zpl) . "\n";
echo "Bytes enviados: " . ($ultimoEnvio['bytes_enviados'] !== null ? $ultimoEnvio['bytes_enviados'] : 'N/A') . "\n";
echo "Envio completo: " . ($ultimoEnvio['envio_completo'] !== null ? $ultimoEnvio['envio_completo'] : 'N/A') . "\n";
echo "Log red: " . ($ultimoEnvio['log_red'] !== null ? $ultimoEnvio['log_red'] : 'N/A') . "\n";
echo "Print tone configurado antes de imprimir: "
    . ($ultimoEnvio['print_tone_configurado'] !== null ? $ultimoEnvio['print_tone_configurado'] : 'N/A')
    . "\n";
echo "Resultado configuracion print.tone: "
    . ($ultimoEnvio['resultado_config_red'] !== null ? $ultimoEnvio['resultado_config_red'] : 'N/A')
    . "\n";
echo "Correccion Y por etiqueta: -" . $correccionYporEtiqueta . " dots\n";
echo "Offset inicial aplicado: " . $offsetInicialY . " dots\n";
echo "\n";
echo "Producto:\n";
echo "Codigo: " . $codigo . "\n";
echo "Corte: " . $datos['corte'] . "\n";
echo "SENASA: " . $datos['senasa'] . "\n";
echo "Envasado: " . $datos['envasado'] . "\n";
echo "Vencimiento: " . $datos['vencimiento'] . "\n";
echo "Lote: " . $datos['lote'] . "\n";
echo "Cantidad: " . $cantidad . "\n";
echo "\n";
echo "Etiqueta:\n";
echo "Ancho mm: " . comandosZebra::ETIQUETA_CARNES_ANCHO_MM . "\n";
echo "Alto mm: " . comandosZebra::ETIQUETA_CARNES_ALTO_MM . "\n";
echo "Print width ZPL: " . comandosZebra::mmAPuntos(comandosZebra::ETIQUETA_CARNES_ANCHO_MM) . "\n";
echo "Label length ZPL: " . comandosZebra::mmAPuntos(comandosZebra::ETIQUETA_CARNES_ALTO_MM) . "\n";
echo "Velocidad ZPL: ^PR" . comandosZebra::ETIQUETA_CARNES_PRINT_SPEED_IPS . "\n";
echo "Darkness ZPL: ^MD" . comandosZebra::ETIQUETA_CARNES_DARKNESS . "\n";
echo "Codificacion: ^CI28\n";
echo "\n";
echo "Campos ZPL:\n";
foreach ($camposEtiqueta as $nombreCampo => $campo) {
    echo $nombreCampo . ":\n";
    echo "  x: " . $campo['x'] . "\n";
    echo "  y: " . $campo['y'] . "\n";
    echo "  fuente: " . $campo['fuente'] . "\n";
    echo "  tamano: " . $campo['tamano'] . "\n";
    echo "  ancho: " . $campo['ancho'] . "\n";
    if (isset($campo['bloque'])) {
        echo "  bloque: " . $campo['bloque'] . "\n";
    }
}
echo "======== FIN DIAGNOSTICO IMPRESION ========\n";
echo "\n";

if ($modoEfectivo === 'red') {
    echo "========== ESTADO IMPRESORA RED ==========\n";
    echo "Conexion consulta: " . comandosZebra::IMPRESORA_IP . ":" . comandosZebra::IMPRESORA_PUERTO . "\n";
    $variablesZebra = array(
        'device.languages',
        'head.resolution.in_dpi',
        'print.tone',
        'print.speed',
        'media.type',
        'ezpl.print_width',
        'zpl.label_length',
        'media.ribbon_out',
        'media.paper_out',
        'head.open',
        'device.pause'
    );
    foreach ($variablesZebra as $variableZebra) {
        echo $variableZebra . ": "
            . comandosZebra::consultarVariableRed(
                comandosZebra::IMPRESORA_IP,
                comandosZebra::IMPRESORA_PUERTO,
                $variableZebra
            )
            . "\n";
    }
    echo "======== FIN ESTADO IMPRESORA RED ========\n";
    echo "\n";
}

echo "========== ZPL GENERADO ==========\n";
echo $zpl;
echo "======== FIN ZPL GENERADO ========\n";
