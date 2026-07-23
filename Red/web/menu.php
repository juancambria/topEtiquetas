<?php
session_start();

// Verificar que esté logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$nombre = $_SESSION['nombre'];
$sucursal = $_SESSION['sucursal'];
$opciones = $_SESSION['opciones'];

// Opciones disponibles con información
$infoOpciones = array(
    'empanada' => array(
        'titulo' => 'Empanadas',
        'descripcion' => 'Impresión de etiquetas para empanadas',
        'icono' => '🥟',
        'codigos' => 'Códigos que empiezan con 835'
    ),
    'picada' => array(
        'titulo' => 'Picadas',
        'descripcion' => 'Impresión de etiquetas para picadas',
        'icono' => '🍽️',
        'codigos' => 'Códigos que empiezan con 817'
    ),
    'carne' => array(
        'titulo' => 'Carnes',
        'descripcion' => 'Impresión de etiquetas para cortes de carne',
        'icono' => '🥩',
        'codigos' => 'Códigos que empiezan con 813 o 850'
    )
);

// Cerrar sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú - Sistema de Impresiones</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #777777ff 0%, #1aaef3ff 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        .user-details h2 {
            color: #333;
            font-size: 18px;
        }
        .user-details p {
            color: #666;
            font-size: 14px;
        }
        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background 0.3s;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .opciones-container {
            max-width: 800px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .opcion-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .opcion-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .opcion-icono {
            font-size: 60px;
            margin-bottom: 15px;
        }
        .opcion-titulo {
            font-size: 22px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .opcion-descripcion {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .opcion-codigos {
            background: #f5f5f5;
            padding: 8px;
            border-radius: 5px;
            font-size: 12px;
            color: #555;
        }
        .mensaje {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="user-info">
            <div class="user-avatar"><?php echo substr($sucursal, 0, 2); ?></div>
            <div class="user-details">
                <h2><?php echo htmlspecialchars($nombre); ?></h2>
                <p>Sucursal: <?php echo htmlspecialchars($sucursal); ?></p>
            </div>
        </div>
        <a href="?logout=1" class="logout-btn">Cerrar Sesión</a>
    </div>
    
    <h1>Seleccione el tipo de impresión</h1>
    
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="mensaje <?php echo isset($_SESSION['mensaje_tipo']) ? $_SESSION['mensaje_tipo'] : ''; ?>">
            <?php 
                echo $_SESSION['mensaje'];
                unset($_SESSION['mensaje']);
                unset($_SESSION['mensaje_tipo']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="opciones-container">
        <?php foreach ($opciones as $opcion): ?>
            <?php if (isset($infoOpciones[$opcion])): ?>
                <a href="imprimir.php?tipo=<?php echo $opcion; ?>" class="opcion-card">
                    <div class="opcion-icono"><?php echo $infoOpciones[$opcion]['icono']; ?></div>
                    <div class="opcion-titulo"><?php echo $infoOpciones[$opcion]['titulo']; ?></div>
                    <div class="opcion-descripcion"><?php echo $infoOpciones[$opcion]['descripcion']; ?></div>
                    <div class="opcion-codigos"><?php echo $infoOpciones[$opcion]['codigos']; ?></div>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</body>
</html>