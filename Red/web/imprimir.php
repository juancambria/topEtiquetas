<?php
session_start();

// Verificar que esté logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Verificar que venga el tipo
if (!isset($_GET['tipo'])) {
    header("Location: menu.php");
    exit;
}

$tipo = $_GET['tipo'];
$opcionesPermitidas = $_SESSION['opciones'];

// Verificar que el tipo sea válido para esta sucursal
if (!in_array($tipo, $opcionesPermitidas)) {
    $_SESSION['mensaje'] = "Tipo de impresión no permitido para esta sucursal";
    $_SESSION['mensaje_tipo'] = "mensaje-error";
    header("Location: menu.php");
    exit;
}

$sucursal = $_SESSION['sucursal'];

// Información del tipo
$infoTipos = array(
    'empanada' => array(
        'titulo' => 'Empanadas',
        'icono' => '🥟',
        'prefijo' => '835',
        'script' => 'impresionEmpanadasCLI.php',
        'placeholder' => 'Ej: 835001'
    ),
    'picada' => array(
        'titulo' => 'Picadas',
        'icono' => '🍽️',
        'prefijo' => '817',
        'script' => 'impresionPicadasCLI.php',
        'placeholder' => 'Ej: 817001'
    ),
    'carne' => array(
        'titulo' => 'Carnes',
        'icono' => '🥩',
        'prefijos' => array('813', '850'),
        'prefijo' => '813' . " o " . '850', // Para mostrar en el mensaje
        'script' => 'impresionCarnesCLI.php',
        'placeholder' => 'Ej: 813001 o 850001'
    )
);

$info = $infoTipos[$tipo];
$resultado = "";
$error = "";
$valorModo = isset($_POST['modo_impresion']) ? strtolower(trim($_POST['modo_impresion'])) : 'auto';
if ($valorModo !== 'usb' && $valorModo !== 'red' && $valorModo !== 'auto') {
    $valorModo = 'auto';
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = isset($_POST['codigo']) ? $_POST['codigo'] : '';
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;
    $modoImpresion = $valorModo;

    // Validaciones básicas
    if (empty($codigo)) {
        $error = "Debe ingresar un código";
    } elseif ($cantidad <= 0) {
        $error = "La cantidad debe ser mayor a 0";
    } else {

        // Validar que el código corresponda al tipo (PHP 5.6 compatible)
        $prefijos = isset($info['prefijos']) ? $info['prefijos'] : array($info['prefijo']);
        $validPrefix = false;
        foreach ($prefijos as $prefijo) {
            if (strpos($codigo, $prefijo) === 0) {
                $validPrefix = true;
                break;
            }
        }

        if (!$validPrefix) {
            $error = "El código debe empezar con " . implode(" o ", $prefijos);
        } elseif ($modoImpresion !== 'usb' && $modoImpresion !== 'red' && $modoImpresion !== 'auto') {
            $error = "Modo de impresión inválido";
        } else {

            // Ejecutar el script PHP existente
            $scriptPath = __DIR__ . '/../Modelos/' . $info['script'];
            $comando = "php "
                . escapeshellarg($scriptPath) . " "
                . escapeshellarg($codigo) . " "
                . escapeshellarg((string)$cantidad) . " "
                . escapeshellarg((string)$sucursal) . " "
                . escapeshellarg($modoImpresion);

            $output = shell_exec($comando . " 2>&1");

            if ($output) {
                $resultado = $output;
            } else {
                $error = "Error al ejecutar la impresión";
            }
        }
    }
}

// Valores para mantener formulario
$valorCodigo = isset($_POST['codigo']) ? $_POST['codigo'] : '';
$valorCantidad = isset($_POST['cantidad']) ? $_POST['cantidad'] : '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir - <?php echo $info['titulo']; ?></title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg,#777777ff 0%, #1aaef3ff 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .back-btn {
            padding: 8px 15px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .content {
            background: white;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 5px;
            font-size: 24px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #218838;
        }

        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1565c0;
        }

        .resultado {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            font-family: monospace;
            white-space: pre-wrap;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .sucursal-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }

    </style>
</head>

<body>

    <div class="container">

        <div class="header">

            <a href="menu.php" class="back-btn">← Volver</a>

            <div>
                <span style="font-size:30px;"><?php echo $info['icono']; ?></span>
            </div>

            <div>
                <h1><?php echo $info['titulo']; ?></h1>
            </div>

        </div>

        <div class="content">

            <p class="subtitle">Complete los datos para imprimir</p>

            <div class="sucursal-info">
                <strong>Sucursal:</strong>
                <?php echo htmlspecialchars($_SESSION['sucursal']); ?>
                -
                <!-- <?php echo htmlspecialchars($_SESSION['nombre']); ?> -->
            </div>

            <?php if ($error): ?>
                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($resultado): ?>

                <div class="resultado">
                    <?php echo htmlspecialchars($resultado); ?>
                </div>

                <a href="imprimir.php?tipo=<?php echo $tipo; ?>"
                   class="btn"
                   style="margin-top:15px;display:inline-block;text-decoration:none;">

                    Imprimir otra vez

                </a>

            <?php else: ?>

                <form method="POST">

                    <div class="info-box">
                        💡 Los códigos para
                        <strong><?php echo $info['titulo']; ?></strong>
                        deben empezar con:
                        <strong><?php echo $info['prefijo']; ?></strong>
                    </div>

                    <div class="form-group">

                        <label for="codigo">Código del producto:</label>

                        <input
                            type="text"
                            id="codigo"
                            name="codigo"
                            required
                            placeholder="<?php echo $info['placeholder']; ?>"
                            value="<?php echo htmlspecialchars($valorCodigo); ?>"
                        >

                    </div>

                    <div class="form-group">
                        <label for="modo_impresion">Modo de impresión:</label>
                        <select id="modo_impresion" name="modo_impresion" required>
                            <option value="auto" <?php echo $valorModo === 'auto' ? 'selected' : ''; ?>>Auto</option>
                            <option value="usb" <?php echo $valorModo === 'usb' ? 'selected' : ''; ?>>USB</option>
                            <option value="red" <?php echo $valorModo === 'red' ? 'selected' : ''; ?>>Red</option>
                        </select>
                    </div>

                    <div class="form-group">

                        <label for="cantidad">Cantidad de etiquetas:</label>

                        <input
                            type="number"
                            id="cantidad"
                            name="cantidad"
                            required
                            min="1"
                            value="<?php echo htmlspecialchars($valorCantidad); ?>"
                        >

                    </div>

                    <button type="submit" class="btn">
                        🖨️ Imprimir Etiquetas
                    </button>

                </form>

            <?php endif; ?>

        </div>

    </div>

</body>
</html>