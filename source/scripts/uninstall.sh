#!/bin/bash


function version { echo "$@" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }'; }

QEMU=/etc/libvirt/hooks/qemu
QEMUDFILE=/etc/libvirt/hooks/qemu.d/USB_Manager

VERSION=$(sed -n 's/.*version *= *\"\([^ ]*.*\)\"/\1/p' < /etc/unraid-version)

if [ $(version $VERSION) -ge $(version "6.9.9") ]; then

# Process OS Version > 6.9.9
# Remove QEMU.D/USB_Manager 
    
   rm $QEMUDFILE

else

# Process OS Version < 6.9.9
# Remove embedded code from old QEMU File

START=$(grep -n "begin USB_MANAGER" "${QEMU}" | cut -f1 -d:)
END=$(grep -n "end USB_MANAGER" "${QEMU}" | cut -f1 -d:)
[[ -n ${START} ]] && [[ -n ${END} ]] && sed -i "${START},${END}d" "${QEMU}"

fi