<?php

/**
 * Driver ZPL para impresoras Zebra (USB / red).
 *
 * @method static comandosZebra crearSegunConfiguracion(string $modo = null, string $ip = null, int $puerto = null)
 * @method static comandosZebra crear()
 */
class comandosZebra
{
    const IMPRESORA_IP = '192.168.11.29';
    const IMPRESORA_PUERTO = 9100;
    const IMPRESORA_USB_DEVICE = '/dev/usb/lp0';

    // Empanadas / picadas (ZD220 203 dpi)
    const ETIQUETA_ANCHO_MM = 80;
    const ETIQUETA_ALTO_MM = 78;
    const ETIQUETA_ALTO_DOTS = 628;
    const ETIQUETA_MARGEN_EXTRA_MM_ALTO = 1.0;
    const ETIQUETA_HOME_X = 0;
    const ETIQUETA_PRINT_SPEED_IPS = 2;
    const ETIQUETA_LABEL_TOP_OFFSET = 0;
    const ETIQUETA_MEDIA_TRACKING = 'N';

    // Carnes: 100 mm x 80 mm
    const ETIQUETA_CARNES_ANCHO_MM = 100;
    const ETIQUETA_CARNES_ALTO_MM = 80;

    const IMPRESORA_USB_ID = 'Bus 001 Device 004: ID 0a5f:0164 Zebra Technologies ZTC ZD220-203dpi ZPL';

    private $device;
    private $hostRed = null;
    private $puertoRed = null;

    public function __construct($devicePath)
    {
        $this->device = $devicePath;
    }

    public static function crear()
    {
        return self::crearSegunConfiguracion('auto');
    }

    public static function desdeRed($ip, $puerto = null)
    {
        $ip = trim($ip);
        if ($ip === '') {
            throw new Exception('IP/host de impresora vacio');
        }
        if ($puerto === null) {
            $puerto = self::IMPRESORA_PUERTO;
        }
        $z = new self(null);
        $z->hostRed = $ip;
        $z->puertoRed = (int) $puerto;
        return $z;
    }

    public static function desdeUsbHardcodeado($devicePath = null)
    {
        if ($devicePath === null || trim($devicePath) === '') {
            $devicePath = self::IMPRESORA_USB_DEVICE;
        }
        return new self($devicePath);
    }

    public static function crearSegunConfiguracion($modo = null, $ip = null, $puerto = null)
    {
        if ($modo !== null) {
            $modo = strtolower(trim($modo));
        }
        if ($modo !== null && $modo !== '' && $modo !== 'auto' && $modo !== 'usb' && $modo !== 'red') {
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

    public static function mmAPuntos($mm)
    {
        return (int) round($mm * 8);
    }

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
        $altoBase = (int) self::ETIQUETA_ALTO_DOTS;
        $extra = self::mmAPuntos203(self::ETIQUETA_MARGEN_EXTRA_MM_ALTO);
        return $altoBase + $extra;
    }

    public static function puntosAnchoBloqueCentrado($margenCadaLado)
    {
        return max(100, self::puntosAnchoEtiqueta() - 2 * (int) $margenCadaLado);
    }

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
        file_put_contents('/tmp/zebra.zpl', $comando);

        if ($this->hostRed !== null) {
            return $this->envioPorRed($comando);
        }
        if ($this->device === null || $this->device === '') {
            throw new Exception('No se configuro dispositivo de impresion');
        }

        clearstatcache();

        $resultado = @file_put_contents($this->device, $comando);
        if ($resultado !== false) {
            return true;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'zpl_');
        file_put_contents($tempFile, $comando);
        $cmd = "sudo /usr/bin/tee " . escapeshellarg($this->device) . " < " . escapeshellarg($tempFile) . " > /dev/null 2>&1";
        exec($cmd, $out, $ret);
        unlink($tempFile);

        if ($ret !== 0) {
            throw new Exception('No se pudo imprimir por USB');
        }

        return true;
    }

    private function envioPorRed($comando)
    {
        $fp = @fsockopen($this->hostRed, $this->puertoRed, $errno, $errstr, 15);
        if (!$fp) {
            throw new Exception(
                'No se pudo conectar a la impresora '
                . $this->hostRed . ':' . $this->puertoRed
                . ' - ' . $errstr . ' (' . $errno . ')'
            );
        }
        stream_set_timeout($fp, 15);
        $len = strlen($comando);
        $ok = @fwrite($fp, $comando);
        fclose($fp);
        if ($ok === false || $ok !== $len) {
            throw new Exception('Envio incompleto a la impresora por red');
        }
        return true;
    }

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

    public function textoRotado($texto, $x, $y, $tam = 30)
    {
        return "^FO{$x},{$y}^A0R,{$tam},{$tam}^FD{$texto}^FS\n";
    }

    public function textoRotadoCentrado($texto, $x, $y, $altoColumna, $tam)
    {
        return "^FO{$x},{$y}"
            . "^FB{$altoColumna},1,0,C"
            . "^A0R,{$tam},{$tam}"
            . "^FD{$texto}^FS\n";
    }

    public function inicioEtiqueta()
    {
        $z = "^XA\n^CI28\n";
        $pw = self::puntosAnchoEtiqueta();
        $ll = self::puntosAltoEtiqueta();
        $lt = (int) self::ETIQUETA_LABEL_TOP_OFFSET;
        $lhx = (int) self::ETIQUETA_HOME_X;
        $pr = (int) self::ETIQUETA_PRINT_SPEED_IPS;
        $mn = self::ETIQUETA_MEDIA_TRACKING;
        $z .= "^LH{$lhx},0\n";
        $z .= "^LS0\n";
        $z .= "^FWN\n";
        $z .= "^PW{$pw}\n";
        $z .= "^MN{$mn}\n";
        $z .= "^PR{$pr}\n";
        $z .= "^LT{$lt}\n";
        $z .= "^LL{$ll}\n";
        return $z;
    }

    public function inicioEtiquetaCarne()
    {
        $pw = self::mmAPuntos(self::ETIQUETA_CARNES_ANCHO_MM);
        $ll = self::mmAPuntos(self::ETIQUETA_CARNES_ALTO_MM);

        $zpl  = "^XA\n";
        $zpl .= "^CI28\n";
        $zpl .= "^PW{$pw}\n";
        $zpl .= "^LL{$ll}\n";
        $zpl .= "^PR3\n";
        $zpl .= "^MD8\n";

        return $zpl;
    }

    public function finEtiqueta()
    {
        return "^XZ\n";
    }
}
