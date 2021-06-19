#!/bin/bash

QEMU=/etc/libvirt/hooks/qemu

if ! ( grep -q "usb_manager/scripts/rc.usb_manager" "${QEMU}" ); then

FINDLINE=\<\?php
NEWLINE=$(cat<<'END_HEREDOC'
#begin USB_MANAGER\nif ($argv[2] == 'prepare' || $argv[2] == 'stopped'){\n  shell_exec("/usr/local/emhttp/plugins/usb_manager/scripts/rc.usb_manager vm_action '{$argv[1]}' {$argv[2]} {$argv[3]} {$argv[4]}  >/dev/null 2>&1 & disown") ;\n}\n#end USB_MANAGER
END_HEREDOC
)
sed -i "/${FINDLINE}/a ${NEWLINE}" "${QEMU}"

fi