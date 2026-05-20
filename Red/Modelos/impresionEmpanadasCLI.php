<?php
require_once __DIR__ . '/../ConexionBD/conexionBD.php';
require_once __DIR__ . '/../Comandos/comandosZebra.php';

if (isset($argv) && count($argv) >= 3) {
    $codigo = $argv[1];
    $cantidad = (int)$argv[2];
    // Si hay sucursal se toma, si no se deja vacío
    $sucursal = isset($argv[3]) ? $argv[3] : null;
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
    echo "→ Modo automático\n";
    echo "Código: " . $codigo . " | Cantidad: " . $cantidad . "\n";
    echo "Modo impresión: " . $modoImpresion . "\n";

} else {
    // MODO MANUAL
    $codigo = readline("Ingrese el código del producto: ");
    $cantidad = (int) readline("Ingrese la cantidad de etiquetas: ");
    $modoImpresion = strtolower(trim(readline("Modo impresión (auto/usb/red) [auto]: ")));
    if ($modoImpresion === '') {
        $modoImpresion = 'auto';
    }
}


// Arreglo de ingredientes hardcodeados
$ingredientesProductos = array(
    "835105" => array(
        "Jamon Cocido,",
        "Queso Barra,",
        "Masa para empanada."
    ),
    "835103" => array(
        "Vacio, Cebolla, Aji Molido",
        "Pimienta Negra, Sal, Pimenton,",
        "Masa para empanadas"
    ),
    "835108" => array(
        "Matambre, Cebolla, Aji Molido,",
        "Pimiento Rojo, Sal, Pimenton,",
        "Huevo, Cebolla de Verdeo,",
        "Masa para empanadas"
    ),
    "835107" => array(
        "Carne Picada, Cebolla,",
        "Pimiento Rojo, Pimienta Negra,",
        "Sal, Aju Molido, Jugo de Limon,",
        "Tomate, Masa para empanadas"    
    ),
    "835100" => array(
        "Carne Molida, Cebolla, Azucar,",
        "Pasas de uva, Pimienta Negra,",
        "Sal, Aji molido, Pimenton, Comino,",
        "Masa para empanadas"
    ),
    "835101" => array(
        "Carne Molida, Cebolla, Aji Molido,a",
        "Pimienta Negra, Sal, Comino,",
        "Masa para empanadas"
    )
);

if (!$codigo) die("Debe ingresar un código.\n");
if ($cantidad <= 0) die("Cantidad inválida.\n");

// Consultar descripción desde la BD
$stmt = $pdo->prepare("SELECT descripcion FROM articulos WHERE codigo = ?");
$stmt->execute(array($codigo));
$producto = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$producto) die("Código no encontrado.\n");

$titulo = $producto['descripcion'];

if (!isset($ingredientesProductos[$codigo])) die("Ingredientes no definidos.\n");
$ingredientes = $ingredientesProductos[$codigo];

// Datos fijos
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

// FUNCIÓN PARA CORTAR EL TÍTULO EN VARIAS LÍNEAS Y QUE NUNCA SE PISE
function wrapTexto($texto, $maxChars = 22) {
    return explode("\n", wordwrap($texto, $maxChars, "\n", true));
}


//construir y enviar etiquetas
for ($i = 0; $i < $cantidad; $i++) {

    $zpl = $zebra->inicioEtiqueta();
    $zpl .= "^POI\n";
    $zpl .= "^PW{$pw}\n";
    $zpl .= "^LL{$ll}\n";
    $zpl .= "^MD20\n";

    //descripcion del producto
    $tituloLineas = wrapTexto($titulo, 22);
    $cantLineasTitulo = count($tituloLineas);

    $topLineY = 150;
    $zpl .= "^FO{$xo},{$topLineY}^GB{$gbw},3,3^FS\n";

    $yTitulo = $topLineY + 10;

    foreach ($tituloLineas as $linea) {
        $zpl .= $zebra->textoCentrado($linea, $yTitulo, 32, $fb);
        $yTitulo += 35;
    }

    $bottomLineY = $yTitulo + 5;
    $zpl .= "^FO{$xo},{$bottomLineY}^GB{$gbw},3,3^FS\n";


    //informacion de ingredientes centrados
    $y = $bottomLineY + 30;
    $zpl .= $zebra->textoCentrado("Ingredientes:", $y, 28, $fb);
    $y += 35;

    foreach ($ingredientes as $linea) {
        $zpl .= $zebra->textoCentrado($linea, $y, 26, $fb);
        $y += 30;
    }

    $zpl .= "^FO{$xo}," . ($y + 10) . "^GB{$gbw},3,3^FS\n";
    $y += 40;

    //info extra
    $info = array(
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

    // Ajuste final
    $y += 50;

    // CIERRE
    $zpl .= $zebra->finEtiqueta();

    try {
        $zebra->envio($zpl);
    } catch (Exception $e) {
        die("❌ Impresora: " . $e->getMessage() . "\n");
    }
}

echo "Se imprimieron " . $cantidad . " etiquetas para el producto " . $titulo . ".\n";