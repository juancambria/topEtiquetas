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
$valorModo = isset($_POST['modo_impresion']) ? strtolower(trim($_POST['modo_impresion'])) : '';
if ($valorModo !== 'usb' && $valorModo !== 'red') {
    $valorModo = '';
}
$valorCorreccionAltura = isset($_POST['correccion_altura']) ? trim($_POST['correccion_altura']) : '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = isset($_POST['codigo']) ? $_POST['codigo'] : '';
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;
    $modoImpresion = $valorModo;
    $correccionAltura = null;

    if ($tipo === 'carne' && ($modoImpresion === 'usb' || $modoImpresion === 'red')) {
        if ($valorCorreccionAltura === '' || !preg_match('/^-?\d+$/', $valorCorreccionAltura)) {
            $error = "Corrección de altura inválida";
        } else {
            $correccionAltura = (int) $valorCorreccionAltura;
        }
    }

    // Validaciones básicas
    if ($error !== "") {
        // Error de validación ya definido arriba.
    } elseif (empty($codigo)) {
        $error = "Debe ingresar un código";
    // Con límite de 1 a 5 etiquetas:
    // } elseif ($cantidad < 1 || $cantidad > 5) {
    //     $error = "La cantidad debe estar entre 1 y 5 etiquetas";
    // Sin límite de etiquetas:
    } elseif ($cantidad < 1) {
        $error = "La cantidad debe ser mayor o igual a 1";
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
        } elseif ($modoImpresion !== 'usb' && $modoImpresion !== 'red') {
            $error = "Debe seleccionar tipo de conexión: USB o Red";
        } else {

            // Ejecutar el script PHP existente
            $scriptPath = __DIR__ . '/../Modelos/' . $info['script'];
            $comando = "php "
                . escapeshellarg($scriptPath) . " "
                . escapeshellarg($codigo) . " "
                . escapeshellarg((string)$cantidad) . " "
                . escapeshellarg((string)$sucursal) . " "
                . escapeshellarg($modoImpresion);
            if ($tipo === 'carne' && ($modoImpresion === 'usb' || $modoImpresion === 'red')) {
                $comando .= " " . escapeshellarg((string)$correccionAltura);
            }

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
                            <option value="" <?php echo $valorModo === '' ? 'selected' : ''; ?>>Seleccione conexión...</option>
                            <option value="usb" <?php echo $valorModo === 'usb' ? 'selected' : ''; ?>>USB</option>
                            <option value="red" <?php echo $valorModo === 'red' ? 'selected' : ''; ?>>Red</option>
                        </select>
                    </div>

                    <?php if ($tipo === 'carne'): ?>
                    <div class="form-group" id="grupo_correccion_altura" style="<?php echo ($valorModo === 'usb' || $valorModo === 'red') ? '' : 'display:none;'; ?>">
                        <label for="correccion_altura">Corrección de altura (dots por etiqueta):</label>
                        <input
                            type="number"
                            id="correccion_altura"
                            name="correccion_altura"
                            step="1"
                            value="<?php echo htmlspecialchars($valorCorreccionAltura); ?>"
                        >
                    </div>
                    <?php endif; ?>

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
                        <small style="display:block;margin-top:6px;color:#666;">
                            Cantidad mínima permitida: 1 etiqueta.
                        </small>

                    </div>

                    <button type="submit" class="btn">
                        🖨️ Imprimir Etiquetas
                    </button>

                </form>

            <?php endif; ?>

        </div>

    </div>

</body>

<script>
(function() {
    var tipo = <?php echo json_encode($tipo); ?>;
    if (tipo !== 'carne') {
        return;
    }

    var selectModo = document.getElementById('modo_impresion');
    var grupoCorreccion = document.getElementById('grupo_correccion_altura');
    var inputCorreccion = document.getElementById('correccion_altura');

    if (!selectModo || !grupoCorreccion || !inputCorreccion) {
        return;
    }

    function actualizarVisibilidadCorreccion() {
        var mostrarCorreccion = (selectModo.value === 'usb' || selectModo.value === 'red');
        grupoCorreccion.style.display = mostrarCorreccion ? '' : 'none';
        inputCorreccion.required = mostrarCorreccion;
    }

    actualizarVisibilidadCorreccion();
    selectModo.addEventListener('change', actualizarVisibilidadCorreccion);
})();
</script>
</html>
