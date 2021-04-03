#!/bin/bash
#
# Copy config files to ram tmpfs.
#
/usr/bin/rm /tmp/unraid.usbip-gui/config/*.cfg
/usr/bin/cp /boot/config/plugins/unraid.usbip-gui/*.cfg /tmp/unraid.usbip-gui/config/ 2>/dev/null
