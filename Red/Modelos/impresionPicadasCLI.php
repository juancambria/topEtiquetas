<?php
require_once __DIR__ . '/../ConexionBD/conexionBD.php';
require_once __DIR__ . '/../Comandos/comandosZebra.php';

// 1) PARÁMETROS OBLIGATORIOS
if (!isset($argv) || count($argv) < 4) {
    die("USO CORRECTO:\nphp impresionPicadas.php CODIGO CANTIDAD SUCURSAL [auto|usb|red]\n");
}

$codigo    = $argv[1];
$cantidad  = (int)$argv[2];
$sucursal  = $argv[3];
$modoImpresion = 'auto';
for ($i = 4; isset($argv[$i]); $i++) {
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

echo "→ Impresión etiquetas PICADAS\n";
echo "Código: " . $codigo . " | Cantidad: " . $cantidad . " | Sucursal: " . $sucursal . "\n";
echo "Modo impresión: " . $modoImpresion . "\n";

// INGREDIENTES
$ingredientesPicadas = array(
    "817007" => array(
        "Mortadela, Jamon Cocido,",
        "Salamin,",
        "Fiambre de cerdo, Queso"
    )
);

if (!isset($ingredientesPicadas[$codigo])) die("Ingredientes no definidos para este código.\n");
$ingredientes = $ingredientesPicadas[$codigo];

// CONFIG RMPA
$rmpa = ($sucursal === "25") ? "R.M.P.A: 7064/23" : "R.M.P.A: 7065/23";

// CONSULTA BD
$stmt = $pdo->prepare("SELECT descripcion FROM articulos WHERE codigo = ?");
$stmt->execute(array($codigo));
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) die("Código no encontrado en BD.\n");

$titulo = $producto['descripcion'];

// DATOS FIJOS
$lote = "coincide con fecha de elaboracion";
$habilitacion = "S 861 0 14";
$conservacion = "Refrigerado";
$domicilio = "Francisco Muniz 750";
$empresa = "SUPERIMPERIO S.A.";
$pais = "Industria Argentina";

try {
    $zebra = comandosZebra::crearSegunConfiguracion($modoImpresion);
} catch (Exception $e) {
    die("❌ Impresora: " . $e->getMessage() . "\n");
}

$pw = comandosZebra::puntosAnchoEtiqueta();
$ll = comandosZebra::puntosAltoEtiqueta();
$fb = comandosZebra::puntosAnchoBloqueCentrado(40);
$xo = (int) round(($pw - $fb) / 2);
$gbw = $fb;

function wrapTexto($texto, $maxChars) {
    if ($maxChars === null) {
        $maxChars = 22;
    }
    return explode("\n", wordwrap($texto, $maxChars, "\n", true));
}

// IMPRESIÓN ZPL
for ($i = 0; $i < $cantidad; $i++) {

    $zpl = $zebra->inicioEtiqueta();

    // ROTACIÓN 90° FIJA EN TODA LA ETIQUETA
    $zpl .= "^FWN\n";

    $zpl .= "^PW{$pw}\n";
    $zpl .= "^LL{$ll}\n";
    $zpl .= "^MD20\n";

    // TÍTULO
    $tituloLineas = wrapTexto($titulo, 22);
    $topLineY = 150;
    $zpl .= "^FO{$xo},{$topLineY}^GB{$gbw},3,3^FS\n";

    $yTitulo = $topLineY + 10;

    foreach ($tituloLineas as $linea) {
        $zpl .= $zebra->textoCentrado($linea, $yTitulo, 32, $fb);
        $yTitulo += 35;
    }

    $bottomLineY = $yTitulo + 5;
    $zpl .= "^FO{$xo},{$bottomLineY}^GB{$gbw},3,3^FS\n";

    // INGREDIENTES
    $y = $bottomLineY + 30;
    $zpl .= $zebra->textoCentrado("Ingredientes:", $y, 28, $fb);
    $y += 35;

    foreach ($ingredientes as $linea) {
        $zpl .= $zebra->textoCentrado($linea, $y, 26, $fb);
        $y += 30;
    }

    $zpl .= "^FO{$xo}," . ($y + 10) . "^GB{$gbw},3,3^FS\n";
    $y += 40;

    // BLOQUE INFO
    $info = array(
        $rmpa,
        "Nro Lote: " . $lote,
        "Nro Habilitacion Municipal: " . $habilitacion,
        "Forma de conservacion: " . $conservacion,
        "Domicilio: " . $domicilio
    );

    foreach ($info as $linea) {
        $zpl .= $zebra->textoCentrado($linea, $y, 24, $fb);
        $y += 35;
    }

    $y += 7;

    $marcoY = $y - 10;
    $marcoAltura = 90;

    $zpl .= "^FO{$xo},{$marcoY}^GB{$gbw},{$marcoAltura},3^FS\n";

    $zpl .= $zebra->textoCentrado($empresa, $y, 32, $fb);
    $y += 38;

    $zpl .= $zebra->textoCentrado($pais, $y, 24, $fb);

    $zpl .= $zebra->finEtiqueta();
    try {
        $zebra->envio($zpl);
    } catch (Exception $e) {
        die("❌ Impresora: " . $e->getMessage() . "\n");
    }
}

echo "\n✔ Se imprimieron " . $cantidad . " etiquetas para " . $titulo . " (Sucursal " . $sucursal . ")\n";