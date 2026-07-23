# Cambios de Impresión por Red - impresionCarnesCLI.php

## Resumen
Se ha refactorizado completamente el sistema de impresión de etiquetas de carne para usar **impresión directa por red** con `fsockopen`, eliminando la dependencia de la clase `comandosZebra`.

La solución implementada se basa en el método que funciona perfectamente en el otro programa.

## Cambios Principales

### 1. Configuración Simplificada (Líneas 3-10)
```php
define('ZEBRA_PRINTER_IP', '192.168.11.29');
define('ZEBRA_PRINTER_PORT', 9100);
define('ZEBRA_PRINTER_TIMEOUT', 5);
```

**Dimensiones de etiqueta (10cm x 8cm a 203 DPI):**
- Ancho: 10cm = 799 dots
- Alto: 8cm = 639 dots

### 2. Eliminación de Dependencia a `comandosZebra`
- ❌ Removido: `require_once __DIR__ . "/../Comandos/comandosZebra.php";`
- ✅ Nuevo: Funciones locales simples para generar ZPL

### 3. Método de Envío Directo
**Antes:**
```php
$zebra = comandosZebra::crearSegunConfiguracion($modoImpresion);
$zebra->envio($zpl);
```

**Ahora:**
```php
$enviado = imprimirZebraPorRed($zebraIp, $zebraPuerto, $zebraTimeout, $zpl);
```

### 4. Funciones Nuevas

#### `imprimirZebraPorRed($ip, $puerto, $timeoutSegundos, $zpl)`
- Conecta directamente a la impresora usando `fsockopen`
- Envía el ZPL sin capas intermedias
- Retorna `true` si el envío fue exitoso, `false` si falló

#### `sanitizarTextoZpl($texto)`
- Remueve caracteres especiales que podrían romper ZPL
- Reemplaza `^` y `~` por espacios
- Elimina caracteres de control

#### `generarInicioEtiquetaCarne()`
- Genera comandos ZPL para inicializar la etiqueta
- Configura dimensiones: 799 x 639 dots (10cm x 8cm)
- Velocidad de impresión: 3 ips
- Oscuridad (darkness): 5

#### `generarFinEtiqueta()`
- Retorna `^XZ\n` (cierre de etiqueta ZPL)

#### `generarTextoRotado($texto, $x, $y, $tam, $ancho)`
- Genera texto rotado 90° (comando `^A0R`)
- Posición en X, Y (en dots)

#### `generarTextoRotadoCentrado($texto, $x, $y, $altoColumna, $tam, $ancho)`
- Genera texto rotado y centrado en una columna
- Usa `^FB` para centrado

## Ventajas de la Nueva Implementación

1. **Simplicidad**: Menos código, más directo
2. **Confiabilidad**: Usa el método que funciona perfectamente en otro programa
3. **Sin dependencias**: No depende de `comandosZebra`
4. **Diagnóstico claro**: Muestra IP, puerto, timeout y bytes enviados
5. **Sanitización**: Protege contra caracteres especiales en ZPL

## Configuración

Para cambiar la IP de la impresora, edita la línea 3:
```php
define('ZEBRA_PRINTER_IP', 'TU_IP_AQUI');
```

## Prueba de Funcionamiento

```bash
php impresionCarnesCLI.php 813197 1
```

Parámetros:
- `813197`: Código del corte
- `1`: Cantidad de etiquetas

## Diagnóstico

El script muestra:
- ✅ Conexión exitosa a IP:Puerto
- ✅ Bytes ZPL generados y configuración
- ✅ Producto: Código, corte, SENASA, fechas, lote
- ✅ ZPL completo generado

## Notas Técnicas

- **203 DPI**: Resolución de impresora Zebra ZD220
- **Conversión**: 1cm = 0.393701 pulgadas × 203 DPI = ~79.9 dots
- **Codificación**: ^CI28 (UTF-8)
- **Rotación**: ^A0R (90° clockwise)

## Migración Completada ✅

- [x] Eliminada dependencia de `comandosZebra`
- [x] Implementado `fsockopen` directo
- [x] Funciones ZPL locales
- [x] Sanitización de texto
- [x] Diagnóstico mejorado
- [x] Verificación de sintaxis PHP
