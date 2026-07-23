#!/bin/bash
#
# imprimirZebra.sh
# ---------------------------------------------------------
# Revisa si el backend (PHP) ya dejó listo un archivo ZPL
# para imprimir. Como el programa corre en el servidor y la
# impresora está por USB en esta PC, este script se ejecuta
# LOCALMENTE (por cron) y es el que realmente manda a imprimir.
#
# Lógica pedida:
#   if existe zebra.zpl
#       if existe zebra.ok
#           -> recién ahí imprimo
#
# ---------------------------------------------------------

# ===================== CONFIGURACIÓN ======================

DIR_ARCHIVOS="/home/usuario/Documentos/Juan/PROYECTOS/topEtiquetas/Red/archivosGenerados"
ARCHIVO_ZPL="$DIR_ARCHIVOS/zebra.zpl"
ARCHIVO_OK="$DIR_ARCHIVOS/zebra.ok"

# Dispositivo USB de la Zebra (mismo que usa comandosZebra.php)
DISPOSITIVO_IMPRESORA="/dev/usb/lp0"

# Carpeta shell (la que creaste vos, fuera del zip)
DIR_SHELL="/home/usuario/Documentos/Juan/PROYECTOS/topEtiquetas/Red/shell"
LOG="$DIR_SHELL/impresion.log"
LOCKFILE="$DIR_SHELL/imprimirZebra.lock"

# ===================== FUNCIONES ======================

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG"
}

# Evita que dos ejecuciones del cron se pisen si una tarda más
# de lo normal (por ejemplo si la impresora está trabada).
exec 200>"$LOCKFILE"
flock -n 200 || { log "Ya hay una ejecución en curso. Salgo."; exit 0; }

# ===================== LÓGICA PRINCIPAL ======================

if [ -f "$ARCHIVO_ZPL" ]; then

    if [ -f "$ARCHIVO_OK" ]; then

        log "Detectados zebra.zpl y zebra.ok. Enviando a imprimir..."

        if [ ! -w "$DISPOSITIVO_IMPRESORA" ]; then
            log "ERROR: no se puede escribir en $DISPOSITIVO_IMPRESORA (¿impresora desconectada/apagada o sin permisos?)."
            exit 1
        fi

        if cat "$ARCHIVO_ZPL" > "$DISPOSITIVO_IMPRESORA" 2>>"$LOG"; then
            log "Impresión enviada correctamente."
            rm -f "$ARCHIVO_ZPL" "$ARCHIVO_OK"
            log "Archivos zebra.zpl y zebra.ok eliminados."
            exit 0
        else
            log "ERROR: falló el envío a la impresora."
            exit 1
        fi

    else
        # Existe el .zpl pero todavía no el .ok -> probablemente
        # el PHP está en el medio de escribir los archivos.
        # No hacemos nada, esperamos a la próxima corrida del cron.
        log "zebra.zpl encontrado pero zebra.ok todavía no está. Espero al próximo ciclo."
        exit 0
    fi

fi

# Si no existe ni siquiera zebra.zpl, no hay nada para hacer.
exit 0