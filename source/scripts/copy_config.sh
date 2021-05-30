#!/bin/bash
#
# Copy config files to ram tmpfs.
#
/usr/bin/rm /tmp/usb_manager/config/*.cfg
/usr/bin/cp /boot/config/plugins/usb_manager/*.cfg /tmp/usb_manager/config/ 2>/dev/null
