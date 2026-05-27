<?php

require_once __DIR__ . "/../Comandos/comandosZebra.php";

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

// Con límite de 1 a 5 etiquetas:
// $maxEtiquetasPorLote = 5;
// if ($cantidad > $maxEtiquetasPorLote) {
//     die("❌ Máximo {$maxEtiquetasPorLote} etiquetas por lote para evitar deriva acumulada\n");
// }

// Sin límite de etiquetas:
// no agregar validación extra

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

//configuracion de fechas para calcular envasado con vencimiento
$hoy = new DateTime();

$fechaEnvasado = $hoy->format("d/m/y");
$fechaVencObj = clone $hoy;
$fechaVencObj->modify("+30 days");
$fechaVenc = $fechaVencObj->format("d/m/y");

//generacion de lote unico por dia (formato: ddmm + letra año + contador diario)

// dia y mes actual
$ddmm = $hoy->format("dm");

// letra por año (2026 = A)
$anobase = 2026;
$letra = chr(ord('A') + ($hoy->format("Y") - $anobase));

// archivo persistencia
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

// si cambia el dia de envasado → incrementar contador
if ($estado["ultimo_dia"] !== $ddmm) {
    $estado["contador"]++;
    $estado["ultimo_dia"] = $ddmm;
}

// guardar estado actualizado
file_put_contents($ruta, json_encode($estado));

// formatear contador
$contador = str_pad($estado["contador"], 3, "0", STR_PAD_LEFT);

// lote final
$lote = $ddmm . $letra . "-" . $contador;

//datos general de la impresion
$datos = array(
    "corte"       => $corte,
    "envasado"    => $fechaEnvasado,
    "vencimiento" => $fechaVenc,
    "senasa"      => $senasa,
    "lote"        => $lote,
);

try {
    $zebra = comandosZebra::crearSegunConfiguracion($modoImpresion);
} catch (Exception $e) {
    die("❌ Impresora: " . $e->getMessage() . "\n");
}
echo "→ Imprimiendo etiquetas de carne\n";
echo "Corte: " . $datos['corte'] . "\n";
echo "Cantidad: " . $cantidad . "\n\n";
echo "Modo impresión: " . $modoImpresion . "\n\n";

//configuracion de posiciones de datos (texto rotado 90°, ajustado arriba e izquierda)
$layout = array(
    "corte" => array(
        "x" => 200,
        "y" => 0,
        "alto" => 600,
        "tam" => 50,
    ),
    "envasado" => array(
        "x" => 110,
        "y" => 75,
        "tam" => 34,
    ),
    "vencimiento" => array(
        "x" => 110,
        "y" => 375,
        "tam" => 34,
    ),
    "senasa" => array(
        "x" => 81,
        "y" => 160,
        "tam" => 22,
    ),
    "lote" => array(
        "x" => 81,
        "y" => 412,
        "tam" => 22,
    ),
);

// Compensacion progresiva para tiradas largas en medio continuo sin gap.
// Ajustar de a 1 punto segun pruebas (0 desactiva).
// Con compensación progresiva:
// $compensacionDerivaPorEtiqueta = 2;

// Sin compensación progresiva:
// no definir compensación extra

try {
    // Resincroniza el inicio del lote en medio continuo (consume 1 etiqueta de ajuste).
    $zplSync = $zebra->inicioEtiqueta();
    $zplSync .= "^FXSYNC-INICIO-LOTE^FS\n";
    $zplSync .= $zebra->finEtiqueta();
    $zebra->envio($zplSync);
    usleep(200000);

    for ($i = 0; $i < $cantidad; $i++) {
        // Con compensación progresiva:
        // $offsetDeriva = $i * $compensacionDerivaPorEtiqueta;

        // Sin compensación progresiva:
        $offsetDeriva = 0;

        //configuracion de impresion para los datos
        $zpl  = $zebra->inicioEtiqueta();
        $zpl .= "^PON\n";
        $zpl .= "^MD10\n";

        //datos del corte (rotado 90° para compensar etiqueta de costado)
        $zpl .= $zebra->textoRotadoCentrado(
            $datos['corte'] . " X KG",
            $layout['corte']['x'],
            $layout['corte']['y'] + $offsetDeriva,
            $layout['corte']['alto'],
            $layout['corte']['tam']
        );

        //datos del envasado(solo la fecha) (rotado 90°)
        $zpl .= $zebra->textoRotado(
            $datos['envasado'],
            $layout['envasado']['x'],
            $layout['envasado']['y'] + $offsetDeriva,
            $layout['envasado']['tam']
        );

        //datos del vencimiento(solo la fecha) (rotado 90°)
        $zpl .= $zebra->textoRotado(
            $datos['vencimiento'],
            $layout['vencimiento']['x'],
            $layout['vencimiento']['y'] + $offsetDeriva,
            $layout['vencimiento']['tam']
        );

        //datos del senasa (rotado 90°)
        $zpl .= $zebra->textoRotado(
            $datos['senasa'],
            $layout['senasa']['x'],
            $layout['senasa']['y'] + $offsetDeriva,
            $layout['senasa']['tam']
        );

        $zpl .= $zebra->textoRotado(
            $datos['lote'],
            $layout['lote']['x'],
            $layout['lote']['y'] + $offsetDeriva,
            $layout['lote']['tam']
        );

        $zpl .= $zebra->finEtiqueta();

        $zebra->envio($zpl);
        usleep(150000);
    }
} catch (Exception $e) {
    die("❌ Impresora: " . $e->getMessage() . "\n");
}

echo "✔ Se imprimieron " . $cantidad . " etiquetas correctamente\n";
