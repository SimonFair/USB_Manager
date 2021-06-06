<?php
/* Copyright 2021, Simon Fairweather
 *
 * Based on original code from Guilherme Jardim and Dan Landon
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$plugin = "usb_manager";
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$translations = file_exists("$docroot/webGui/include/Translations.php");

if ($translations) {
	/* add translations */
	$_SERVER['REQUEST_URI'] = 'USBDevices' ;
	require_once "$docroot/webGui/include/Translations.php";
} else {
	/* legacy support (without javascript) */
	$noscript = true;
	require_once "$docroot/plugins/$plugin/include/Legacy.php";
}
global $libvirtd_running, $usb_state ;
require_once("plugins/{$plugin}/include/lib_usb_manager.php");
require_once("webGui/include/Helpers.php");
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

$libvirtd_running = is_file('/var/run/libvirt/libvirtd.pid') ;
if ($libvirtd_running) $vms = $lv->get_domains();
#$arrValidUSBDevices = getValidUSBDevices();



if (isset($_POST['display'])) $display = $_POST['display'];
if (isset($_POST['var'])) $var = $_POST['var'];
check_usbip_modules() ;

load_usbstate() ;



/*
function netmasks($netmask, $rev = false)
{
	$netmasks = [	"255.255.255.252"	=> "30",
					"255.255.255.248"	=> "29",
					"255.255.255.240"	=> "28",
					"255.255.255.224"	=> "27",
					"255.255.255.192"	=> "26",
					"255.255.255.128"	=> "25",
					"255.255.255.0"		=> "24",
					"255.255.254.0"		=> "23",
					"255.255.252.0"		=> "22",
					"255.255.248.0"		=> "21",
					"255.255.240.0" 	=> "20",
					"255.255.224.0" 	=> "19",
					"255.255.192.0" 	=> "18",
					"255.255.128.0" 	=> "17",
					"255.255.0.0"		=> "16",
				];
	return $rev ? array_flip($netmasks)[$netmask] : $netmasks[$netmask];
}
*/

function make_mount_button($device) {
	global $paths, $Preclear, $loaded_usbip_host,$usb_state;

	$button = "<span><button device='{$device["BUSID"]}' class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";
	$connected=$usb_state[$device["ID_SERIAL"]]["connected"] ;
	

		if ($device["isflash"] == true ) {
		 $disabled = "disabled"	;
		 $button = sprintf($button, $context, 'urflash', $disabled, 'fa fa-erase', _('UnRaid Flash'));
		} 

			if ($loaded_usbip_host == "0" || !$device["islocal"] )
			 {
			$disabled = "disabled <a href=\"#\" title='"._("usbip_host module not loaded")."'" ;
		 } 
			else 
			{
				if ($connected == '1' ) $disabled="disabled" ; else $disabled = "enabled"; 
			}

		if ($device["DRIVER"] == "usbip-host") {
		$context = "disk";
		$button = sprintf($button, $context, 'unbind', $disabled, 'fa fa-erase', _('Unbind'));
		}
		else {
			$context = "disk";
			$button = sprintf($button, $context, 'bind', $disabled, 'fa fa-import', _('Bind'));
		}
	
	return $button;
}
function make_attach_button($device,$busid) {
	global $paths, $Preclear , $loaded_vhci_hcd, $usbip_cmds_exist ;

	$button = "<span><button hostport='".$device.";".ltrim($busid)."' class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";

	if ($loaded_vhci_hcd == "0")
		{
			$disabled = "disabled <a href=\"#\" title='"._("vhci_hcd module not loaded")."'" ;
		} else {
			$disabled = "enabled"; 
		}

	$context = "disk";
	$button = sprintf($button, $context, 'attach', $disabled, 'fa fa-import', _('Attach'));
	
	return $button;
}

function make_detach_button($port) {
	global $paths, $Preclear;

	$button = "<span><button port='{$port}' class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";

	if ($device["DRIVER"] == "usbip-host") {
		$context = "disk";
		$button = sprintf($button, $context, 'detach', $disabled, 'fa fa-erase', _('detact'));
		}
		else {
			$context = "disk";
			$button = sprintf($button, $context, 'detach', $disabled, 'fa fa-import', _('detach'));
		}

	return $button;
}

function make_vm_button($vm,$busid,$devid,$srlnbr,$vmstate,$isflash,$usbip_status,$map) {
	global $paths, $Preclear , $loaded_vhci_hcd, $usbip_cmds_exist, $usb_state;

	$connected_method=	$usb_state[$srlnbr]["connectmethod"] ;
	$connected_map=	$usb_state[$srlnbr]["connectmap"] ;
	if ($connected_map=="") $connected_map="Device" ;

	$button = "<span><button vm='".$vm.";".ltrim($busid).";".ltrim($devid).";".$srlnbr.";Manual;".$map."' class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";

	if ($isflash == true ) {
		$disabled = "disabled"	;
		$button = sprintf($button, $context, 'urflash', $disabled, 'fa fa-erase', _('UnRaid Flash'));
		return $button;
	   } 

	if ($usbip_status >0  ) return sprintf($button, $context, 'usbip', "disabled", 'fa fa-erase', _('Inuse USBIP'));


	$buttontext= 'VM Attach' ;
	if ($vm == "" || $vmstate == "shutoff" || $vmstate == "Disabled."  )
		{
			$disabled = "disabled  " ;
		} else {
			$disabled = "enabled"; 
			
		}

	$context = "disk";
	if ($usb_state[$srlnbr]["connected"] == '1' ) {
		$buttontext= 'VM Detach';
		if ($map!=$connected_map) 	$disabled = "disabled  " ; 
		$button = sprintf($button, $context, 'vm_disconnect', $disabled, 'fa fa-import', _($buttontext));
	} else {
		
	$buttontext= 'VM Attach' ;
	$button = sprintf($button, $context, 'vm_connect', $disabled, 'fa fa-import', _($buttontext));
	}
	return $button;
}

switch ($_POST['action']) {
	case 'get_content':
		global $paths, $usbip_cmds_exist, $usbip_enabled, $usbip_local;
   
		if ($usbip_enabled == "enabled") {
		if (!$usbip_cmds_exist || !$loaded_usbip_host || !$loaded_vhci_hcd) {

			$notice="Following are missing or not loaded:" ;
			if (!$usbip_cmds_exist) $notice.=" USBIP Commands" ;
			if (!$loaded_usbip_host) $notice.=" usbip_host module" ;
			if (!$loaded_vhci_hcd) $notice.=" vhci_hcd module" ;
		    echo "<p class='notice 	'>"._($notice).".</p>";
		   }
        }
		$usb_connects = load_usb_connects() ;
		usb_manager_log("Starting page render [get_content]", "DEBUG");
		$time		 = -microtime(true);
		$config_file = $paths['vm_mappings'];
		$vm_maps = @parse_ini_file($config_file, true);
		/* Check for a recent hot plug event. */
		$tc = $paths['hotplug_status'];
		$hotplug = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : "no";

		/* Disk devices */
		$usbip = get_all_usb_info();
		ksort($usbip,SORT_NATURAL  ) ;
		$optionempty = $_POST["empty"] ;
		$topology = $_POST["topo"] ;
		if ($optionempty =="false") {
		foreach ($usbip as $busid => $detail) {
			#var_dump($busid) ;
			if ($detail["ishub"] == "interface") continue ;
			#if ($detail["ishub"] == "hub") continue ;
		  if ($detail["ishub"] == "roothub" || $detail["ishub"] == "hub") {
		#	if ( $detail["ishub"] == "hub") {
				
			$level=0 ;
			$children = $detail["maxchildren"] ;
			$bus = explode("-", $busid) ;
			
			for ($x=1; $x <= $children; $x++) {
			  if ($detail["ishub"] == "roothub") { $newbusid = $bus[0]."-".$x ; $level=0 ; }
			  if ($detail["ishub"] == "hub") { $newbusid = $busid.".".$x ; $level=1 ;}
			 # var_dump( $newbusid );
			  if (!isset($usbip[$newbusid])) {
				  $usbip[$newbusid]["ishub"] = "emptyport" ;
				  $usbip[$newbusid]["level"] = 0 ;
				  if ($detail["ishub"] == "roothub")	  $usbip[$newbusid]["class"] = "roothub" ;
				  if ($detail["ishub"] == "hub")	  $usbip[$newbusid]["class"] = "hub" ;
				  #add
			    }
			 }
		  }
		}

		#Build levels.



        ksort($usbip,SORT_NATURAL  ) ;
		#ksort($usbip,SORT_STRING  ) ;
		
	}
	
	if ($topology == "true") {
	foreach ($usbip as $busid => $detail) {
		$usbip[$busid]['level'] = substr_count($busid, '-') ;
		$usbip[$busid]['level'] += substr_count($busid, '.') ;
		if ($detail["ishub"] == "roothub") {$usbip[$busid]['level'] = 0 ;} 

	}}
		
		echo "<div id='usb_tab' class='show-disks'>";
		echo "<table class='usb_status wide local_usb'><thead><tr><td>"._("Setting")."<td>"._('Physical BusID')."</td><td>"._('Class')."</td><td>"._('Vendor:Product').".</td><td>"._('Serial Numbers')."</td><td>"._('Mapping')."</td><td>"._('VM')."</td><td>"._('VM State')."</td><td>"._('VM Action')."</td><td>"._('Status')."</td>" ;

		if ($usbip_enabled == "enabled") echo "<td>"._('USBIP Action')."</td><td>"._('USBIP Status')."</td><td>"._('Host Name/IP')."</td>" ;
		echo "<td>"._('')."</td></tr></thead>";
$optionroot = false ;		
$optionhub = false ;

		
		echo "<tbody><tr>";
		#var_dump( $usbip);
		if ( count($usbip) ) {
			foreach ($usbip as $disk => $detail) {
				if ($detail["ishub"] == "emptyport" && $optionempty == "true") continue ;
				if ($detail["ishub"] == "hub" && $optionhub == true) continue ;
				if ($detail["ishub"] == "roothub" && $optionroot == true) continue ;



				$srlnbr=$detail["ID_SERIAL"] ;
				if (isset($detail["ID_SERIAL_SHORT"])) $srlnbr_short=$detail["ID_SERIAL_SHORT"] ; else $srlnbr_short="" ;
				$vm_name="" ;
				$vm_name=$vm_maps[$srlnbr]["VM"] ;
				$port_name="Port:".$disk ;
				$port_map_vm=$vm_maps[$port_name]["VM"] ;
				


				if ($detail["isflash"]) {		
					$bus_id =  "<i class='fa fa-usb'></i></a>";
					$bus_id .= "<title='"._("Not Supported for Unraid Flash Drive")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'><i class='fa fa-minus-circle orb red-orb'></i></a>" ;
					
				} else {
				$vm_port="Port:".$disk;	
				$vm_port_name=$vm_maps[$vm_port]["VM"] ;
				$port_title = _("Edit Port Settings").".";
				$port_title .= "   "._("Auto Connect").": ";
				$port_title .= (is_autoconnect($vm_port) == 'Yes') ? "On" : "Off";
				$port_title .= "   "._("Auto Connect on VM Start").": ";
				$port_title .= (is_autoconnectstart($vm_port) == 'yes') ? "On" : "Off";
				$port_title .=  "   ";
				$dev_title = _("Edit Device Settings").".";
				$dev_title .= "   "._("Auto Connect").": ";
				$dev_title .= (is_autoconnect($srlnbr) == 'Yes') ? "On" : "Off";
				$dev_title .= "   "._("Auto Connect on VM Start").": ";
				$dev_title .= (is_autoconnectstart($srlnbr) == 'yes') ? "On" : "Off";
				$dev_title .=  "   ";

				$bus_id = "" ;
				$bus_id .= "<a title='$port_title'  href='/USB/USBEditSettings?s=".urlencode($vm_port)."&v=".urlencode($vm_port_name)."&f=".urlencode($detail["isflash"])."'><i class='fa fa-usb' aria-hidden=true></i></a>&nbsp;"; 	
				$bus_id .= "<a title='$dev_title' href='/USB/USBEditSettings?s=".urlencode($srlnbr)."&v=".urlencode($vm_name)."&f=".urlencode($detail["isflash"])."'><i class='fa fa-desktop'></i></a>&nbsp;";
			}
				$bus_id.= "<a href=\"#\" title='"._("Device Log Information")."' onclick=\"openBox('/webGui/scripts/disk_log&amp;arg1={$disk}','Device Log Information',600,900,false);return false\"><i class='fa fa-file icon'></i></a>";
				$bus_id .="<span title='"._("Click to view/hide partitions and mount points")."' class='exec toggle-hdd' hdd='{$disk}'></span>";
				
				# if ($detail["ishub"] == "interface" || $detail["ishub"] == "emptyport") {
				if ($detail['level'] != 0) {
					$indent = "" ;
					for ($x=0; $x <= ($detail['level'] *2) ; $x++) { $indent .= "&nbsp&nbsp"; }
					$indent .= "|__ ";
				} else $indent = "" ;
				$detail["BUSID"] = $disk ;
				$mbutton = make_mount_button($detail);		
				/* Device serial number */
			    echo "<td>{$bus_id}</td><td>{$indent}{$disk}</td>";

				/* Device Driver */
				echo "<td>".ucfirst($detail["ishub"])."</td>";
				/* Device Vendor & Model */
				if (isset($detail["ID_VENDOR_FROM_DATABASE"])) {
					$vendor=$detail["ID_VENDOR_FROM_DATABASE"] ;
				} else {
					$vendor=$detail["ID_VENDOR"] ;
				}
				if ($optionempty == "false" && $detail["ishub"] == "emptyport")  echo "<td></td>" ; else  echo "<td>".$vendor.":".$detail["ID_MODEL"]."</td>" ; 
			   
			
				if ($srlnbr_short != "") echo "<td>  ".$srlnbr_short."</td>"  ; else echo "<td>  ".$srlnbr."</td>"  ;
				
				$connected="" ;
				if ($vm_name != "" ) {
			#	$res = $lv->get_domain_by_name($vm_name);
			#	$dom = $lv->domain_get_info($res);
			#	$state = $lv->domain_state_translate($dom['state']);

			#put check for  VM subsystem running.
				if ($libvirtd_running && $vm_name != "") $state=get_vm_state($vm_name) ; else $state = "Disabled." ;
				#if ($libvirtd_running && $port_map_vm != "" ) $port_vmstate=get_vm_state($port_map_vm) ; else $port_vmstate = "Disabled." ;

				
				} else { $state="No VM Defined" ;} 

				if ($port_map_vm != "" ) {
					if ($libvirtd_running && $port_map_vm != "" ) $port_vmstate=get_vm_state($port_map_vm) ; else $port_vmstate = "Disabled." ;
				} else { $port_vmstate="No VM Defined" ;} 

				if (isset($usb_state[$srlnbr]["connected"])) {
					$connected = $usb_state[$srlnbr]["connected"];
					$connected_map = $usb_state[$srlnbr]["connectmap"] ;
					if ($connected_map =="") $connected_map="Device" ;
					if ($connected == true) {$connected ="Connected(".$connected_map.")" ;} else {$connected="Disconnected";}
  
				  } else $connected = "Disconnected" ;
  
				  if ($usb_state[$srlnbr]["virsherror"] == true)   {
					  $error=$usb_state[$srlnbr]["virsh"] ;
					  $connected = "<a class='info'><i class='fa fa-warning fa-fw orange-text'></i><span>"._(ltrim($error, "\n"))."</span></a>Virsh Error";
					}

				if ($detail["isflash"]) {
					$vm_name ="Not Allowed" ;
					$port_map_vm ="Not Allowed" ;
					$state="Not Allowed" ;
					$connected="Not Allowed" ;
					$type="N/A" ;
				}	

				#if ($connected_method == "Device")
				#    $port_vmstate = "Disabled." ;
			#		else $state = "Disabled." ;
#if ( !$detail["isflash"]) {
				if ($vm_name != "" ) {
					$type="Device Mapping:" ;
					echo "<td>".$type."</td>" ;
					echo "<td>" ;
					#echo "<a href=\"#\" title='"._("Show QEMU Connected Devices")."' onclick=\"openBox('virsh qemu-monitor-command {$vm_name} --hmp /'info usb/'','Vm Connected USB Devices',600,900,false);return false\"><i class='fa fa-link icon'></i></a>";
					echo $vm_name."</td>";
				#echo "<td>".$port_map_vm."</td>";
				#echo "</select></td> " ;
				$vmbutton = make_vm_button($vm_name, $detail["BUSNUM"],$detail["DEVNUM"],$srlnbr,$state, $detail["isflash"] ,$detail["usbip_status"],"Device");
				echo "<td>".$state."</td>" ;
				echo "<td class='mount'>{$vmbutton}</td>";

				} else {
					if ($port_map_vm != "" ) {
						$type="Port Mapping:" ;
						echo "<td>".$type."</td>" ;
						echo "<td>".$port_map_vm."</td>";
						echo "<td>".$port_vmstate."</td>" ;
						$vmbutton = make_vm_button($port_map_vm, $detail["BUSNUM"],$detail["DEVNUM"],$srlnbr,$port_vmstate, $detail["isflash"] ,$detail["usbip_status"],"Port");
						echo "<td class='mount'>{$vmbutton}</td>";
						}
	
				}

				if ($vm_name == ""  && $port_map_vm == "" ) {
					$type="No Mappings" ;
					$vmbutton = make_vm_button($port_map_vm, $detail["BUSNUM"],$detail["DEVNUM"],$srlnbr,$port_vmstate, $detail["isflash"] ,$detail["usbip_status"],"Port");
					echo "<td>".$type."</td><td></td><td></td><td class='mount'>{$vmbutton}" ;
				}
				
#			}


				echo "<td>".$connected."</td>" ;
				/* USBIP Bind button */
				if ($usbip_enabled == "enabled") echo "<td class='mount'>{$mbutton}</td>";
			    $usbip_status=$detail["usbip_status"] ;
				if ($usbip_status == 1 ) $usbip_status_desc="Bound to driver" ;
				
				if ($usbip_status == 2 ) {
					$usbip_status_desc="Connected to Remote Host:" ;
					if ($usb_connects[$disk]["hostname"] == "" ) $usb_rmt_iphost=$usb_connects[$disk]["IP"] ; 	else $usb_rmt_iphost=$usb_connects[$disk]["hostname"] ;
				}
				else $usb_rmt_iphost = "" ;
                $ip= $usb_connects[$disk]['IP'];
				if ($usbip_status == false ) $usbip_status_desc="" ;
				echo "<td>".$usbip_status_desc."</td>" ;	
				if ($usbip_status == 2) echo "<td><span  title='$ip' </span>".$usb_rmt_iphost."</td>" ;
				echo "</tr>" ;

				if ($port_map_vm !="" && $vm_name != "" && 	!$detail["isflash"]) {
				$type="Port Mapping:" ;
				echo "<tr><td></td><td></td><td></td><td></td><td></td><td>".$type."</td>" ;
				echo "<td>".$port_map_vm."</td>";
				echo "<td>".$port_vmstate."</td>" ;
				$vmbutton = make_vm_button($port_map_vm, $detail["BUSNUM"],$detail["DEVNUM"],$srlnbr,$port_vmstate, $detail["isflash"] ,$detail["usbip_status"],"Port");
				echo "<td class='mount'>{$vmbutton}</td></tr>";
			    }
		
			}
		} else {
			echo "<tr><td colspan='12' style='text-align:center;'>"._('No Bindable Devices available').".</td></tr>";
	

		}
		echo "</tbody></table>" ;
		#echo "<button onclick='save_vm_mapping()'>"._('Save VM Mappings')."</button>";
		echo "</div>";

		
		if ($usbip_enabled == "enabled") {
		/* Remote USBIP Servers */
		echo "<div id='rmtip_tab' class='show-rmtip'>";
		
		echo "<div class='show-rmtip' id='rmtip_tab'><div id='title'><span class='left'><img src='/plugins/$plugin/icons/nfs.png' class='icon'>"._('Remote USBIP Hosts')." &nbsp;</span></div>";
		#echo "<table class='disk_status wide remote_ip'><thead><tr><td>"._('Remote host')."</td><td>"._('Busid')."</td><td>"._('Action')."</td><td>"._('Vendor:Product(Additional Details)')."</td><td></td><td>"._('Remove')."</td><td>"._('Settings')."</td><td></td><td></td><td>"._('Size')."</td><td>"._('Used')."</td><td>"._('Free')."</td><td>"._('Log')."</td></tr></thead>";
		echo "<table class='remote_hosts wide remote_ip'><thead><tr><td>"._('Remote host')."</td><td>"._('Busid')."</td><td>"._('Action')."</td><td>"._('Vendor:Product(Additional Details)')."</td><td></td><td>"._('Remove')."</td><td>"._('')."</td><td></td><td></td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td></tr></thead>";
		echo "<tbody>";
		$ds1 = time();
		$remote_usbip = get_remote_usbip();
		ksort($remote_usbip) ;
		$ii=1 ;

		usb_manager_log("get_remote_usbip: ".($ds1 - microtime(true))."s!","DEBUG");
		if (count($remote_usbip)) {
			foreach ($remote_usbip as $key => $remote)
			{


				$cmd_return=parse_usbip_remote($key) ;
		
				$busids = $cmd_return[$key] ;
				if (isset($busids)) {
				foreach ($busids as $busidkey => $busiddetail)
				{
				echo "<tbody>" ;
	
				
				$hostport = $key."".ltrim($busidkey) ;
				$hostport = "HP".$ii ;
				echo "<tr class='toggle-rmtips'><td><i class='fa fa-minus-circle orb grey-orb'></i>"; 

				echo $key."</td>";
				

				$abutton = make_attach_button($key, $busidkey);		
				
				echo "<td>".$busidkey."</td><td>" ;
			
				echo "<class='attach'>{$abutton}   ";
				echo "<td><span title='"._("Click to view/hide additional Remote details")."' class='exec toggle-rmtip' hostport='{$hostport}'><i class='fa fa-plus-square fa-append'></i></span>".$busiddetail["vendor"].$busiddetail["product"]."</td><td>" ;

				$detail_lines=$busiddetail["detail"] ;
				echo "</td><td title='"._("Remove Remote Host configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;' onclick='remove_remote_host_config(\"{$key}\")'><i class='fa fa-remove hdd'></a></td></tr>" ;

		
					foreach($detail_lines as $line)
						{
						$style = "style='display:none;' " ;

						echo "<tr class='toggle-parts toggle-rmtip-".$hostport."' name='toggle-rmtip-".$hostport."'".$style.">";
						echo "<td></td><td></td><td></td><td>&nbsp&nbsp&nbsp&nbsp&nbsp".htmlspecialchars($line)."</td></tr>" ;				
					}
		
				$ii++ ;
				echo "</tr>";
				}
			}
		}
	}


		if (! count($remote_usbip)) {
			echo "<tr><td colspan='13' style='text-align:center;'>"._('No Remote Systems configured').".</td></tr>";
		}
		echo "</tbody></table>";
		
		echo "<button onclick='add_remote_host()'>"._('Add Remote System')."</button>";
		echo "</div>";
		echo "</div>";


		echo "<div id='port_tab' class='show-ports'>";
		$ct = "";
		$port=parse_usbip_port() ;
	
		echo "<div class='show-ports' id='ports_tab'><div id='title'><span class='left'><img src='/plugins/{$plugin}/icons/historical.png' class='icon'>"._('Attached Ports')."</span></div>";
		echo "<table class='usb_attach wide usb_attached'><thead><tr><td>"._('Device')."</td><td>"._('HUB Port=>Remote host')."</td><td>"._('Action')."</td><td></td><td></td><td></td><td></td><td></td><td>"._('')."</td><td>"._('')."</td></tr></thead>" ;

		foreach ($port as $portkey => $portline) {
			$dbutton = make_detach_button($portkey);
			$ct = "";
			$ct .= "<tr class='toggle-ports'><td><i class='fa fa-minus-circle orb grey-orb'></i><span title='"._("Click to view/hide additional details")."' class='exec toggle-port' port='{$portkey}'><i class='fa fa-plus-square fa-append'></i></span> Port:".$portkey."</td><td>".$portline[2]."</td><td>".$dbutton."</td>";
			$ct .= "";
				$ct .= "<td></td><td></td><td></td><td></td><td></td>";
				$ct .= "<td><a title='"._("Edit Historical Device Settings and Script")."' hidden href='/Main/EditSettings?s=&l=".urlencode(basename($portkey))."&p=".urlencode("1")."&t=TRUE'><i class='fa fa-gears'></i></a></td>";
				$ct .= "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;' hidden onclick='remove_disk_config(\"{$serial}\")'><i class='fa fa-remove hdd'></a></td></tr>";
		

     
 		
		echo "<tbody>{$ct}";
		
		$index = 0;
			foreach($portline as $desc)
			{
				if ($index != 2) {
				
				$style = "style='display:none;'" ;
				#<tr class='toggle-parts toggle-".basename($disk['device'])."' name='toggle-".basename($disk['device'])."' $style>"
				echo "<tr class='toggle-parts toggle-port-".basename($portkey)."' name='toggle-port-".basename($portkey)."' $style>";
				echo "<td></td><td>".htmlspecialchars($desc)."</td></tr>";
				
				}
				$index++ ;
			}
		
	
		}

		echo "</tr>";


		if ( ! count($port)) {
			echo "<tr><td colspan='13' style='text-align:center;'>"._('No ports in use').".</td></tr>";
		}


		
		echo "</tbody></table></div>";
	}

		
		 usb_manager_log("Total render time: ".($time + microtime(true))."s", "DEBUG");
		 
		
		 echo "</div><div id='hist_tab' class='show-history'>";
		
		 $config_file = $GLOBALS["paths"]["vm_mappings"];
		 $config = is_file($config_file) ? @parse_ini_file($config_file, true) : array();
		 $disks_serials = array();
		 #foreach ($disks as $disk) $disks_serials[] = $disk['partitions'][0]['serial'];
		 $ct = "";
		 ksort($config) ;
		 foreach ($config as $serial => $value) {
			
			if($serial == "Config") continue;
			 if (! preg_grep("#{$serial}#", $disks_serials)){
				if (substr($serial,0,5) == "Port:") $icon="fa-usb" ; else $icon="fa-desktop" ;
				if (!isset($value["autoconnect"]))  $value["autoconnect"] ="no" ;
				if (!isset($value["autoconnectstart"])) $value["autoconnectstart"] ="no" ;
 				 #$mountpoint	= basename(get_config($serial, "mountpoint.1"));
				 $ct .= "<tr><td><i class='fa fa-usb'></i>"._("")."</td><td>$serial"." </td>";
				 $ct .= "<td>".$value["VM"]."</td><td>".ucfirst($value["autoconnect"])."</td><td>".ucfirst($value["autoconnectstart"])."</td><td></td><td></td><td></td>";
				 $ct .= "<td><a title='"._("Edit Historical USB Device Settings")."' href='/USB/USBEditSettings?s=".urlencode($serial)."&v=".urlencode($value["VM"])."&t=TRUE'><i class='fa ".$icon."'></i></a></td>";
				 $ct .= "<td title='"._("Remove USB Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;' onclick='remove_vmmapping_config(\"{$serial}\")'><i class='fa fa-remove hdd'></a></td></tr>";
			 }
		 }
		 if (strlen($ct)) {
			 echo "<div class='show-disks'><div class='show-historical' id='hist_tab'><div id='title'><span class='left'><img src='/plugins/{$plugin}/icons/historical.png' class='icon'>"._('Port and Historical Device Mappings')."</span></div>";
			 echo "<table class='disk_status wide usb_absent'><thead><tr><td>"._('Device')."</td><td>"._('Serial Number')."</td><td>"._('VM')."</td><td>Auto Connect</td><td>Auto Connect Start</td><td></td><td></td><td></td><td>"._('Settings')."</td><td>"._('Remove')."</td></tr></thead><tbody>{$ct}</tbody></table></div>";
		 } else {
			echo "<div class='show-disks'><div class='show-historical' id='hist_tab'><div id='title'><span class='left'><img src='/plugins/{$plugin}/icons/historical.png' class='icon'>"._('Port and Historical Device Mappings')."</span></div>";
			echo "<table class='disk_status wide usb_absent'><thead><tr><td>"._('Device')."</td><td>"._('Serial Number')."</td><td>"._('VM')."</td><td>Auto Connect</td><td>Auto Connect Start</td><td></td><td></td><td></td><td>"._('Settings')."</td><td>"._('Remove')."</td></tr></thead>" ;
			echo "<tr><td colspan='13' style='text-align:center;'>"._('No Historic Mappings configured').".</td></tr>";
		 }
		 unassigned_log("Total get_content render time: ".($time + microtime(true))."s", "DEBUG");

		 
		break;

	case 'refresh_page':
		if (! is_file($GLOBALS['paths']['reload'])) {
		#	@touch($GLOBALS['paths']['reload']);
		}
		publish("reload", json_encode(array("rescan" => "yes"),JSON_UNESCAPED_SLASHES)) ;
		break;

	case 'bind':
		$device = urldecode($_POST['device']);
		$cmd_usbip_bind= "usbip bind -b ".$device ;
		exec($cmd_usbip_bind, $out, $return);
		echo json_encode(["status" => $return ? false : true ]);
		break;

	case 'unbind':
		$device = urldecode($_POST['device']);
		$cmd_usbip_unbind= "usbip unbind -b ".$device ;
		exec($cmd_usbip_unbind, $out, $return);
		echo json_encode(["status" => $return ? false : true ]);
		break;

	case 'detach':
		$port = urldecode($_POST['port']);
		$cmd_usbip_detach= "usbip detach -p ".$port ;
		exec($cmd_usbip_detach, $out, $return);
		echo json_encode(["status" => $return ? false : true ]);
		break;
		
	case 'attach':
		$hostport = urldecode($_POST['hostport']);
		$explode= explode(";",$hostport) ;
		$host = $explode[0] ;
		$port = $explode[1] ;
		$cmd_usbip_attach= "usbip attach -r ".$host." -b ".$port ;
		exec($cmd_usbip_attach, $out, $return);
		echo json_encode(["status" => $return ? false : true ]);
		break;	


	case 'rescan_disks':
		exec("plugins/{$plugin}/scripts/copy_config.sh");
		$tc = $paths['hotplug_status'];
		$hotplug = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : "no";
		if ($hotplug == "no") {
			file_put_contents($tc, json_encode('yes'));
			@touch($GLOBALS['paths']['reload']);
		}
		break;

	case 'list_nfs_hosts':
		$network = $_POST['network'];
		foreach ($network as $iface)
		{
			$ip = $iface['ip'];
			$netmask = $iface['netmask'];
			echo shell_exec("/usr/bin/timeout -s 13 5 plugins/{$plugin}/scripts/port_ping.sh {$ip} {$netmask} 3240 2>/dev/null | sort -n -t . -k 1,1 -k 2,2 -k 3,3 -k 4,4");
		}
		break;

	case 'autoconnectstart':
		$serial = urldecode(($_POST['serial']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => toggle_autoconnectstart($serial, $status) ));
		break;

	case 'autoconnect':
		$serial = urldecode(($_POST['serial']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => toggle_autoconnect($serial, $status) ));
		break;

	case 'updatevm':
		$serial = urldecode(($_POST['serial']));
		$vmname = urldecode(($_POST['vmname']));
		echo json_encode(array( 'result' => updatevm($serial, $vmname) ));
		break;
	
	case 'add_remote_host':
		$rc = TRUE;

		$ip = urldecode($_POST['IP']);
		$ip = implode("",explode("\\", $ip));
		$ip = stripslashes(trim($ip));

		if ($ip) {
			$device = $ip ;
			$device = str_replace("$", "", $device);
		
			set_remote_host_config("{$device}", "ip", (is_ip($ip) ? $ip : strtoupper($ip)));


			/* Refresh the ping status */
			is_usbip_server_online($ip, FALSE);
		}
		echo json_encode($rc);
		break;

	case 'remove_remote_host_config':
		$ip = urldecode(($_POST['ip']));
		echo json_encode(remove_config_remote_host($ip));
		break;

	case 'remove_vmmapping':
		$serial = urldecode(($_POST['serial']));
		echo json_encode(remove_vm_mapping($serial));
		break;

	case 'test':
		$vm = urldecode($_POST['vm']);
		#$op = urldecode($_POST['op']);
		$explode= explode(";",$vm );
		$vmname = $explode[0] ;
		$bus = $explode[1] ;
		$dev = $explode[2] ;
		$srlnbr= $explode[3] ;
		$usbstr = '';


		$return=virsh_device_by_bus("attach",$vmname, $bus, $dev) ;
		save_usbstate($srlnbr, "connected" , true) ;
		echo json_encode(["status" => $return ]);
		break ;	

		case 'vm_connect':
			$vm = urldecode($_POST['vm']);
			$action = "attach" ;
			$return = vm_map_action($vm, $action) ;
			echo $return;
			break ;	

		case 'vm_disconnect':
			$vm = urldecode($_POST['vm']);
			$action = "detach" ;
			$return = vm_map_action($vm, $action) ;
			echo $return;
			break ;	
	
		case 'usbdash':
			$allocated = "" ;
			$dash_array=array() ;
			$usb_devices =	get_all_usb_info() ;
			ksort($usb_devices,SORT_NATURAL  ) ;
			$usb_connects = load_usb_connects() ;
			foreach ($usb_devices as $key => $device) {

				$allocated = "" ;
				$srlnbr = $device["ID_SERIAL"] ;

					if (isset($device["usbip_status"] )) {
						
						if ($device["usbip_status"] == 1) {
							$state="Bound(USBIP)" ;
							$orb_colour ='yellow' ;
						}
						if ($device["usbip_status"] == 2) {
							$state="Connected(USBIP)" ;
							$orb_colour ='green' ;
							if ($usb_connects[$key]["hostname"] == "" ) $allocated=$usb_connects[$key]["IP"] ; 	else $allocated=$usb_connects[$key]["hostname"] ;
						}
						else $usb_rmt_iphost = "" ;
					} else {
				        if ($usb_state[$srlnbr]["connected"] == '1')	{
					    $state="Connected(VM)" ;
					    $allocated = $usb_state[$srlnbr]["VM"] ;
					    $orb_colour ='green' ;
				        } else {
					    $state="Available" ;
					
				    	$allocated = "" ;
					    $orb_colour ='blue' ;
				        }

		}

		if ($device["isflash"]) {
			$state = "UNRAID FLASH" ;
			$allocated = "BOOT DEVICE" ;
			$orb_colour ='grey' ;
		}
		if ($device["ID_MODEL"] == "") $device["ID_MODEL"] = ucfirst($device["ishub"]) ;
					$dash_array[$key] = array(
					"ID_MODEL" => $device["ID_MODEL"],
					"allocated" => $allocated,
					"status" => $state,
					"orb_colour" => $orb_colour ,	
				) ;
			}
			$return  = ['usb_devices' => $dash_array];
        	echo json_encode($return);
	}
?>
