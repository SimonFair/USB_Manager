#!/bin/bash

function version { echo "$@" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }'; }

QEMU=/etc/libvirt/hooks/qemu
QEMUDFILE=/etc/libvirt/hooks/qemu.d/USB_Manager

VERSION=$(sed -n 's/.*version *= *\"\([^ ]*.*\)\"/\1/p' < /etc/unraid-version)



if [ $(version $VERSION) -ge $(version "6.9.9") ]; then
    echo "Version is for qemu.d $VERSION"

# Process OS Version > 6.9.9
# Remove Previous embedded code from old QEMU File

if  ( grep -q "usb_manager/scripts/rc.usb_manager" "${QEMU}" ); then    
START=$(grep -n "begin USB_MANAGER" "${QEMU}" | cut -f1 -d:)
END=$(grep -n "end USB_MANAGER" "${QEMU}" | cut -f1 -d:)
[[ -n ${START} ]] && [[ -n ${END} ]] && sed -i "${START},${END}d" "${QEMU}"
fi

sed -i "s@^[^#]\(.*rc.usb_manager\)@#\1@" "${QEMU}"

# Check qemu.d exists if not create.

[ ! -d "/etc/libvirt/hooks/qemu.d" ] && mkdir /etc/libvirt/hooks/qemu.d

# Create USB_Manager File.

cat << EOF > $QEMUDFILE
#!/usr/bin/env php

<?php

#begin USB_MANAGER
if (\$argv[2] == 'prepare' || \$argv[2] == 'stopped'){
      shell_exec("/usr/local/emhttp/plugins/usb_manager/scripts/rc.usb_manager vm_action '{\$argv[1]}' {\$argv[2]} {\$argv[3]} {\$argv[4]}  >/dev/null 2>&1 & disown") ;
}
#end USB_MANAGER
?>
EOF

chmod +x $QEMUDFILE

else

# Process OS Version < 6.9.9

echo "Version is not for qemu.d $VERSION"

if ! ( grep -q "usb_manager/scripts/rc.usb_manager" "${QEMU}" ); then

FINDLINE=\<\?php
NEWLINE=$(cat<<'END_HEREDOC'
#begin USB_MANAGER\nif ($argv[2] == 'prepare' || $argv[2] == 'stopped'){\n  shell_exec("/usr/local/emhttp/plugins/usb_manager/scripts/rc.usb_manager vm_action '{$argv[1]}' {$argv[2]} {$argv[3]} {$argv[4]}  >/dev/null 2>&1 & disown") ;\n}\n#end USB_MANAGER
END_HEREDOC
)
sed -i "/${FINDLINE}/a ${NEWLINE}" "${QEMU}"

fi
fi

