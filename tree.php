<?php
  /*
  * Copyright (c) 2015 Sascha Ludwig / dienes.de
  * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
  */

  error_reporting(E_ERROR | E_WARNING | E_PARSE);
  $unhex   = function($value) { return substr($value, 2); };
  $hex2int = function($value) { return hexdec($value); };

  $basestation_settings = file("http://admin:admin@172.23.2.2/Settings.cfg");

  // get all settings values
  foreach ( $basestation_settings as $line ) {
    list($key, $value) = split(":", $line);
    $settings[$key] = $value;
  }

  // get chain IPs
  $chain_ip = array_map('trim', split(",", $settings["%NETWORK_SYNC_STATIC_IP_CHAIN%"]));
  // get chain MACs
  $chain_mac_tmp = array_map($unhex, split(",", $settings["%NETWORK_SYNC_MAC_CHAIN%"]));
  for( $i=0 ; $i<sizeof($chain_mac_tmp) ; $i+=6 ) {
    $chain_mac[] = $chain_mac_tmp[$i].":".$chain_mac_tmp[$i+1].":".$chain_mac_tmp[$i+2].":".$chain_mac_tmp[$i+3].":".$chain_mac_tmp[$i+4].":".$chain_mac_tmp[$i+5];
  }

  // get chain sync tree
  $chain_sync = array_map($hex2int, split(",", $settings["%NETWORK_DECT_SYNC_TREE%"]));

  // get sync master
  for( $i=0 ; $i<sizeof($chain_ip); $i++ ) {
    if( $chain_ip[$i] != '0.0.0.0' ) {
      if( $i == $chain_sync[$i] ) {
        $sync_master = $i;
      }
    }
  }

  $graph = sprintf('digraph {
  rankdir=TB;
  size="16,9";
  node [shape = ellipse]; DECT%02d;
  node [shape = ellipse];
  ', $sync_master+1);

  for( $i=0 ; $i<sizeof($chain_ip); $i++ ) {
      if( $chain_ip[$i] != '0.0.0.0' ) {
          if( $i != $chain_sync[$i] ) {
              $graph .= sprintf("  DECT%02d->DECT%02d;\n", $chain_sync[$i]+1, $i+1);
          }
          if( $i == $chain_sync[$i] ) {
              $master_label = "<br/><font point-size=\"8\">MASTER</font>";
          } else { $master_label = ""; }
          $graph .= sprintf("  DECT%02d [label=<DECT%02d<br/><font point-size=\"8\">%s</font><br/><font point-size=\"8\">%s</font>%s>];\n", $i+1, $i+1, $chain_mac[$i], $chain_ip[$i], $master_label);
      }
  }

  $graph .= sprintf('  overlap=false;
    label="DECT Sync Tree DIENES - %s"
    fontsize=12;
  }', date("D M j G:i:s T Y"));

  if($_REQUEST['test'] == '1') {
    echo "<pre>".$graph."</pre>";
  } else {
    header("Content-type: image/png");
    system("echo ".escapeshellarg($graph)." | neato -Tpng");
  }
?>
