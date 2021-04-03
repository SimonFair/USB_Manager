#!/bin/bash

DEBUG=false ;

$DEBUG && Log "debug: usbip syslog filter triggered"

/usr/local/emhttp/plugins/usb_manager/scripts/rc.usb_manager usb_syslog "$1" >/dev/null 2>&1 & disown

exit 0
