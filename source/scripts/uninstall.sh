#!/bin/bash

QEMU=/etc/libvirt/hooks/qemu

START=$(grep -n "begin USB_MANAGER" "${QEMU}" | cut -f1 -d:)
END=$(grep -n "end USB_MANAGER" "${QEMU}" | cut -f1 -d:)
[[ -n ${START} ]] && [[ -n ${END} ]] && sed -i "${START},${END}d" "${QEMU}"