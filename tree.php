<?php
  /*
  * Copyright (c) 2015 Sascha.Ludwig@dienes.de
  * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
  */

  error_reporting(E_ERROR | E_WARNING | E_PARSE);

  // helper fucntions
  $unhex   = function($value) { return substr($value, 2); };
  $hex2int = function($value) { return hexdec($value); };

  $multicell = join(" ", file("http://admin:admin@172.23.2.1/MultiCell.html"));

  // get chain IPs
  preg_match("/.*SetSyncIpChain\(\"(.*)\"\);.*/", $multicell, $data);
  $chain_ip = array_map('trim', split(",", $data[1]));

  // get chain MACs
  preg_match("/.*SetSyncMacChain\(\"(.*)\"\);.*/", $multicell, $data);
  $chain_mac_tmp = array_map($unhex, split(",", $data[1]));
  for( $i=0 ; $i<sizeof($chain_mac_tmp) ; $i+=6 ) {
    $chain_mac[] = $chain_mac_tmp[$i].":".$chain_mac_tmp[$i+1].":".$chain_mac_tmp[$i+2].":".$chain_mac_tmp[$i+3].":".$chain_mac_tmp[$i+4].":".$chain_mac_tmp[$i+5];
  }

  // get chain sync tree
  preg_match("/.*SetSyncTree\(\"(.*)\"\);.*/", $multicell, $data);
  $chain_sync = array_map($hex2int, split(",", $data[1]));

 // get chain rssi values
  preg_match("/.*SetSyncDectRssiChain\(\"(.*)\"\);.*/", $multicell, $data);
  $rssi_tmp = split(":", $data[1]);
  for ( $idx=0; $idx<sizeof($rssi_tmp) ; $idx++ ) {
    if ( $rssi_tmp[$idx] != "0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0" ) {
      $tmp = split(",", $rssi_tmp[$idx]);
      for ( $rpnidx=0; $rpnidx<sizeof($tmp); $rpnidx++ ) {
        list( $rpn, $rssi ) = split("\.", $tmp[$rpnidx]);
        if ( $rssi != 0 ) {
          $chain_rssi[$idx][$rpn] = round(2.127*$rssi-147.39);
        }
      }
    }
  }

  // get basestation names
  preg_match("/.*SetSyncBaseNameChain\(\"(.*)\"\);.*/", $multicell, $data);
  $bs_names = array_map('trim', split(",", $data[1]));

  $graph = sprintf('digraph {
  rankdir=LR;
  size = "16,9";
  center=true;
  ratio=compress;
  overlap=scale;
  splines=true;
  node [shape = ellipse];
  ');

  for( $i=0 ; $i<sizeof($chain_ip); $i++ ) {
      if( $chain_ip[$i] != '0.0.0.0' ) {
          if( $i != $chain_sync[$i] ) {
              $rssi = $chain_rssi[$i][$chain_sync[$i]*4];
              if( $rssi == 0 ) { $rssidb = "unknown"; } else { $rssidb = $rssi."dBm"; }
              $graph .= sprintf("  %s->%s [label=<<font point-size=\"10\">%s</font>>];\n", $bs_names[$chain_sync[$i]], $bs_names[$i], $rssidb );
          }
          if( $i == $chain_sync[$i] ) {
              $master_label = " (MASTER)";
          } else { $master_label = ""; }
          $graph .= sprintf("  %s [label=<%s<br/><font point-size=\"8\">%s</font><br/><font point-size=\"8\">%s</font><br/><font point-size=\"8\">RPN%02X%s</font>>];\n", $bs_names[$i], $bs_names[$i], $chain_mac[$i], $chain_ip[$i], $i*4, $master_label);
      }
  }

  $graph .= sprintf('label="DECT Sync Tree DIENES - %s"
    fontsize=12;
  }', date("D M j G:i:s T Y"));

  if($_REQUEST['test'] == '1') {
    header("Content-type: text");
    echo $graph."\n";
    var_dump($rssi_tmp);
    var_dump($chain_rssi);

  } else {
    header("Content-type: image/png");
    //system("echo ".escapeshellarg($graph)." | fdp -Tpng");
    system("echo ".escapeshellarg($graph)." | dot -Tpng");
    //system("echo ".escapeshellarg($graph)." | twopi -Tpng");
    //system("echo ".escapeshellarg($graph)." | neato -Tpng");
  }
?>
