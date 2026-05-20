<?php
class comandosZebra
{
    /**
     * IP/host Zebra por red (RAW puerto 9100).
     */
    const IMPRESORA_IP = '192.168.11.29';

    const IMPRESORA_PUERTO = 9100;

    /**
     * Ruta hardcodeada para USB local (modo simple).
     */
    const IMPRESORA_USB_DEVICE = '/dev/usb/lp0';

    /**
     * ZD220 203 dpi: puntos por mm = 203/25.4
     * Etiqueta física 80 mm × 100 mm.
     */
    const ETIQUETA_ANCHO_MM = 80;
    const ETIQUETA_ALTO_MM = 100;

    /**
     * Largo lógico extra en mm.
     * Debe quedar en 0 para no acumular corrimiento entre etiquetas consecutivas.
     */
    const ETIQUETA_MARGEN_EXTRA_MM_ALTO = 0;

    /**
     * ^LT en puntos. Positivo baja la impresión, negativo la sube (probar de a poco).
     */
    const ETIQUETA_LABEL_TOP_OFFSET = 0;

    /**
     * ^MN define el tipo de media.
     * Y = etiquetas no continuas con sensor de gap/notch.
     * Cambiar a M si el rollo usa black mark.
     */
    const ETIQUETA_MEDIA_TRACKING = 'Y';

    /**
     * Línea típica de lsusb para Zebra ZD220 USB (si IMPRESORA_IP está vacío).
     */
    const IMPRESORA_USB_ID = 'Bus 001 Device 004: ID 0a5f:0164 Zebra Technologies ZTC ZD220-203dpi ZPL';

    private $device;
    private $hostRed = null;
    private $puertoRed = null;

    public function __construct($devicePath)
    {
        $this->device = $devicePath;
    }

    /**
     * Red RAW (típico puerto 9100). El servidor debe poder abrir TCP hasta esta IP.
     *
     * @param string $ip
     * @param int|null $puerto null = IMPRESORA_PUERTO
     * @return comandosZebra
     */
    public static function desdeRed($ip, $puerto = null)
    {
        $ip = trim($ip);
        if ($ip === '') {
            throw new Exception('IP/host de impresora vacío');
        }
        if ($puerto === null) {
            $puerto = self::IMPRESORA_PUERTO;
        }
        $z = new self(null);
        $z->hostRed = $ip;
        $z->puertoRed = (int) $puerto;
        return $z;
    }

    /**
     * USB simple hardcodeado (sin detección por lsusb/sysfs).
     *
     * @param string|null $devicePath null = IMPRESORA_USB_DEVICE
     * @return comandosZebra
     */
    public static function desdeUsbHardcodeado($devicePath = null)
    {
        if ($devicePath === null || trim($devicePath) === '') {
            $devicePath = self::IMPRESORA_USB_DEVICE;
        }
        return new self($devicePath);
    }

    /**
     * Decide conexión en tiempo de impresión.
     * - "red": usa IMPRESORA_IP/PUERTO o los parámetros enviados.
     * - "usb": usa IMPRESORA_USB_DEVICE hardcodeado.
     * - null/"auto": mantiene fallback por configuración.
     *
     * @param string|null $modo "red"|"usb"|"auto"|null
     * @param string|null $ip
     * @param int|null $puerto
     * @return comandosZebra
     */
    public static function crearSegunConfiguracion($modo = null, $ip = null, $puerto = null)
    {
        if ($modo !== null) {
            $modo = strtolower(trim($modo));
        }
        if ($modo !== null && $modo !== '' && $modo !== 'auto' && $modo !== 'usb' && $modo !== 'red') {
            // Mantener compatibilidad hacia atrás: cualquier valor no reconocido cae a auto.
            $modo = 'auto';
        }
        if ($modo === 'usb') {
            return self::desdeUsbHardcodeado();
        }
        if ($modo === 'red') {
            if ($ip === null || trim($ip) === '') {
                $ip = self::IMPRESORA_IP;
            }
            if ($puerto === null) {
                $puerto = self::IMPRESORA_PUERTO;
            }
            return self::desdeRed($ip, $puerto);
        }

        $ip = trim(self::IMPRESORA_IP);
        if ($ip !== '') {
            return self::desdeRed($ip, self::IMPRESORA_PUERTO);
        }
        return self::desdeUsbHardcodeado();
    }

    /** @return int puntos a 203 dpi */
    public static function mmAPuntos203($mm)
    {
        return (int) round((float) $mm * 203.0 / 25.4);
    }

    public static function puntosAnchoEtiqueta()
    {
        return self::mmAPuntos203(self::ETIQUETA_ANCHO_MM);
    }

    public static function puntosAltoEtiqueta()
    {
        return self::mmAPuntos203(self::ETIQUETA_ALTO_MM + self::ETIQUETA_MARGEN_EXTRA_MM_ALTO);
    }

    /** Ancho útil para ^FB centrado con márgenes laterales simétricos (puntos). */
    public static function puntosAnchoBloqueCentrado($margenCadaLado)
    {
        return max(100, self::puntosAnchoEtiqueta() - 2 * (int) $margenCadaLado);
    }

    /**
     * Crea la instancia resolviendo /dev/usb/lpN por vendor:product (Linux, driver usblp).
     *
     * @param string $identificacion Línea completa de lsusb, o "0a5f:0164"
     * @return comandosZebra
     */
    public static function desdeIdentificacionUsb($identificacion)
    {
        $id = self::extraerVendorProductUsb($identificacion);
        if ($id === null) {
            throw new Exception('No se pudo obtener vendor:product del texto: ' . $identificacion);
        }
        $ruta = self::buscarRutaUsbLp($id);
        if ($ruta === null) {
            throw new Exception(
                'No hay dispositivo /dev/usb/lp* con ID USB ' . $id
                . '. Conecte la Zebra y compruebe con lsusb.'
            );
        }
        return new self($ruta);
    }

    /**
     * @param string $texto
     * @return string|null vendor:product en minúsculas, p.ej. "0a5f:0164"
     */
    public static function extraerVendorProductUsb($texto)
    {
        $texto = trim($texto);
        if (preg_match('/^[0-9a-f]{4}:[0-9a-f]{4}$/i', $texto)) {
            return strtolower($texto);
        }
        if (preg_match('/ID\s+([0-9a-f]{4}):([0-9a-f]{4})/i', $texto, $m)) {
            return strtolower($m[1] . ':' . $m[2]);
        }
        return null;
    }

    /**
     * Sube por sysfs desde la ruta del enlace "device" del lp hasta encontrar idVendor/idProduct.
     * Un solo dirname() falla en algunos kernels / rutas USB (hubs, nombres 1-1.2:1.0, etc.).
     *
     * @param string $dir ruta absoluta (p.ej. .../1-2:1.0)
     * @return string|null vendor:product en minúsculas
     */
    private static function vendorProductDesdeRutaSysfs($dir)
    {
        $dir = str_replace('\\', '/', $dir);
        $max = 16;
        while ($max-- > 0 && $dir !== '' && $dir !== '/') {
            $vf = $dir . '/idVendor';
            $pf = $dir . '/idProduct';
            if (is_readable($vf) && is_readable($pf)) {
                $v = strtolower(trim(file_get_contents($vf)));
                $p = strtolower(trim(file_get_contents($pf)));
                return $v . ':' . $p;
            }
            $dir = dirname($dir);
        }
        return null;
    }

    /**
     * @param string $vendorProduct vendor:product en cualquier mayúsculas
     * @return string|null ruta tipo /dev/usb/lp0
     */
    public static function buscarRutaUsbLp($vendorProduct)
    {
        $want = strtolower($vendorProduct);
        $sysfsBases = array();
        $g1 = glob('/sys/class/usbmisc/lp*');
        if (is_array($g1)) {
            $sysfsBases = array_merge($sysfsBases, $g1);
        }
        $g2 = glob('/sys/class/usb/lp*');
        if (is_array($g2)) {
            $sysfsBases = array_merge($sysfsBases, $g2);
        }
        $vistos = array();
        foreach ($sysfsBases as $lpSys) {
            if (!is_dir($lpSys)) {
                continue;
            }
            $nombre = basename($lpSys);
            if (isset($vistos[$nombre])) {
                continue;
            }
            $vistos[$nombre] = true;
            $deviceLink = $lpSys . '/device';
            if (!file_exists($deviceLink)) {
                continue;
            }
            $iface = realpath($deviceLink);
            if ($iface === false) {
                continue;
            }
            $idEnSys = self::vendorProductDesdeRutaSysfs($iface);
            if ($idEnSys !== null && $idEnSys === $want) {
                return '/dev/usb/' . $nombre;
            }
        }
        return null;
    }

    public function envio($comando)
    {
        if ($this->hostRed !== null) {
            return $this->envioPorRed($comando);
        }
        if ($this->device === null || $this->device === '') {
            return false;
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'zpl_');
        file_put_contents($tempFile, $comando);
        $cmd = "sudo /usr/bin/tee " . escapeshellarg($this->device) . " < " . escapeshellarg($tempFile) . " > /dev/null 2>&1";
        exec($cmd, $out, $ret);
        unlink($tempFile);
        return $ret === 0;
    }

    /**
     * @param string $comando ZPL completo (UTF-8 en texto; la impresora usa ^CI28)
     * @return bool
     */
    private function envioPorRed($comando)
    {
        $fp = @fsockopen($this->hostRed, $this->puertoRed, $errno, $errstr, 15);
        if (!$fp) {
            throw new Exception('No se pudo conectar a la impresora ' . $this->hostRed . ':' . $this->puertoRed . ' — ' . $errstr . ' (' . $errno . ')');
        }
        stream_set_timeout($fp, 15);
        $len = strlen($comando);
        $ok = @fwrite($fp, $comando);
        fclose($fp);
        if ($ok === false || $ok !== $len) {
            throw new Exception('Envío incompleto a la impresora por red');
        }
        return true;
    }

    //FUNCIONES GENERALES

    public function texto($texto, $x, $y, $tam = 10)
    {
        return "^FO{$x},{$y}^A0N,{$tam},{$tam}^FD{$texto}^FS\n";
    }

    public function negrita($texto, $x, $y, $tam = 10)
    {
        return "^FO{$x},{$y}^A0B,{$tam},{$tam}^FD{$texto}^FS\n";
    }

    public function textoCentrado($texto, $y, $tam = 10, $anchoBloque = 464)
    {
        return "^FO0,{$y}^FB{$anchoBloque},1,0,C,0^A0N,{$tam},{$tam}^FD{$texto}^FS\n";
    }

    public function subrayado($texto, $x, $y, $tam = 10)
    {
        return "^FO{$x},{$y}^A0N,{$tam},{$tam}^FB500,1,0,L,0^FD{$texto}^FS\n"
             . "^FO{$x}," . ($y + $tam + 5) . "^GB500,3,3^FS\n";
    }

    //FUNCIONES AUXILIARES (PARA CARNES)

    // Texto rotado (simple)
    public function textoRotado($texto, $x, $y, $tam)
    {
        return "^FO{$x},{$y}^A0R,{$tam},{$tam}^FD{$texto}^FS\n";
    }

    // Texto rotado centrado dentro de una columna vertical
    public function textoRotadoCentrado($texto, $x, $y, $altoColumna, $tam)
    {
        return "^FO{$x},{$y}"
            . "^FB{$altoColumna},1,0,C"
            . "^A0R,{$tam},{$tam}"
            . "^FD{$texto}^FS\n";
    }

    //CONTROL DE ETIQUETA

    public function inicioEtiqueta()
    {
        $z = "^XA\n^CI28\n";
        $ll = self::puntosAltoEtiqueta();
        $lt = (int) self::ETIQUETA_LABEL_TOP_OFFSET;
        $mn = self::ETIQUETA_MEDIA_TRACKING;
        $z .= "^LH0,0\n";
        $z .= "^LS0\n";
        $z .= "^MN{$mn}\n";
        $z .= "^LT{$lt}\n";
        $z .= "^LL{$ll}\n";
        return $z;
    }

    public function finEtiqueta()
    {
        return "^XZ\n";
    }
}
