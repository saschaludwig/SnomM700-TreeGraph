<?php
  error_reporting(E_ERROR | E_WARNING | E_PARSE);
  require_once("config.php");

  // inline helper fucntions
  $unhex   = function($value) { return substr($value, 2); };
  $hex2int = function($value) { return hexdec($value); };
  $str2int = function($value) { return intval($value); };

  $ext = join(" ", file("http://$user:$pass@$base/Ext.html"));
  $multicell = join(" ", file("http://$user:$pass@$base/MultiCell.html"));

  // parse extension data
  // get number
  preg_match("/.*SetExtensions\(\"(.*)\"\);.*/", $ext, $data);
  $ext_number = array_map('trim', split(",", $data[1]));

  // get name
  preg_match("/.*SetDisplayNames\(\"(.*)\"\);.*/", $ext, $data);
  $ext_name = array_map('trim', split(",", $data[1]));

  // get ipei
  preg_match("/.*SetIpeis\(\"(.*)\"\);.*/", $ext, $data);
  $ext_ipei = array_map('trim', split(",", $data[1]));

  // get fp idx
  preg_match("/.*SetExtensionFpIdx\(\"(.*)\"\);.*/", $ext, $data);
  $ext_fpidx = array_map('trim', split(",", $data[1]));

  // get status
  preg_match("/.*SetExtensionStatus\(\"(.*)\"\);.*/", $ext, $data);
  $ext_status = array_map('trim', split(",", $data[1]));


  // parse multicell data
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
  $chain_sync = array_map($str2int, split(",", $data[1]));

 // get chain rssi values
  preg_match("/.*SetSyncDectRssiChain\(\"(.*)\"\);.*/", $multicell, $data);
  $rssi_tmp = split(":", $data[1]);
  for ( $idx=0; $idx<sizeof($rssi_tmp) ; $idx++ ) {
    if ( $rssi_tmp[$idx] != "0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0" ) {
      $tmp = split(",", $rssi_tmp[$idx]);
      for ( $rpnidx=0; $rpnidx<sizeof($tmp); $rpnidx++ ) {
        list( $rpn, $rssi ) = split("\.", $tmp[$rpnidx]);
        if ( $rssi != 0 ) {
          $chain_rssi_db[$idx][$rpn] = round(2.127*$rssi-147.39);
          $chain_rssi[$idx][$rpn] = $rssi;
        }
      }
    }
  }

  // get basestation names
  preg_match("/.*SetSyncBaseNameChain\(\"(.*)\"\);.*/", $multicell, $data);
  $bs_names = array_map('trim', split(",", $data[1]));

  $graph = sprintf('digraph {
  rankdir=LR;
  size = "32,18";
  center=true;
  ratio=compress;
  overlap=scale;
  splines=true;
  node [shape = ellipse];
  graph [fontname = "Sans-Serif"];
  node [fontname = "Sans-Serif"];
  edge [fontname = "Sans-Serif"];
  ');

  $rssi_sum = 0;
  $rssi_sum_unknown = 0;

  // add FP to graph
  for( $i=0 ; $i<sizeof($chain_ip); $i++ ) {
      if( $chain_ip[$i] != '0.0.0.0' ) {
          $hue = $i/10;
          $color = sprintf("%f,1,1", $hue);
          $fillcolor = sprintf("%f,0.1,1", $hue);
          $linecolor = "black";
          if( $i != $chain_sync[$i] ) {
              $rssi = $chain_rssi_db[$i][$chain_sync[$i]*4];
              $rssi_plain = $chain_rssi[$i][$chain_sync[$i]*4];

              if( $rssi == 0 ) {
                  $rssidb = "unknown";
                  $rssi_sum_unknown = 1;
              } else {
                  $rssidb = $rssi."dBm";
                  $rssi_sum = $rssi_sum + $rssi_plain;
              }
              $graph .= sprintf("  %s -> %s [color=\"%s\" label=<<font point-size=\"10\">%s</font>>];\n", $bs_names[$chain_sync[$i]], $bs_names[$i], $linecolor, $rssidb );
          }
          if( $i == $chain_sync[$i] ) {
              $master_label = " (MASTER)";
          } else { $master_label = ""; }

          $graph .= sprintf("  %s [color=\"%s\" style=filled fillcolor=\"%s\" label=<%s<br/><font point-size=\"8\">%s<br/>%s<br/>%s<br/>RPN%02X%s</font>>];\n", $bs_names[$i], $color, $fillcolor, $bs_names[$i], $chain_mac[$i], htmlentities(utf8_decode($location[$bs_names[$i]])), $chain_ip[$i], $i*4, $master_label);
      }
  }

  if($_REQUEST['pp'] == '1') {
      // add PP to graph
      for( $i=0 ; $i<sizeof($ext_status); $i++ ) {
          if( $ext_status[$i] == '5' ) {
              $number = $ext_number[$i];
              $name   = $ext_name[$i];
              $ipei   = $ext_ipei[$i];
              $fpidx  = $ext_fpidx[$i];

              $hue = $fpidx/10;
              $color = sprintf("%f,1,1", $hue);
              $fillcolor = sprintf("%f,0.1,1", $hue);
              $linecolor = "black";

              $graph .= sprintf("  %s -> %s [color=\"%s\"] ;\n", $bs_names[$fpidx], $number, $color);
              $graph .= sprintf("  %s [color=\"%s\" style=filled fillcolor=\"%s\" label=<%s<br/><font point-size=\"8\">%s<br/>%s</font>>];\n", $number, $color, $fillcolor, $number, htmlentities(utf8_decode($name)), $ipei );
          }
      }
  }

  if ( $rssi_sum_unknown ) {
      $rssi_sum_text = "RSSI sum currently not calulateable (>1 RSSI is unknown)";
  } else {
      $rssi_sum_text = sprintf('calculated RSSI sum=%d', $rssi_sum);
  }

  $graph .= sprintf('label="%s - %s - %s"
    fontsize=12;
}', $footer, date("D M j G:i:s T Y"), $rssi_sum_text);

  if($_REQUEST['test'] == '1') {
    header("Content-type: text");
    echo $graph."\n";
    var_dump($bs_names);
    var_dump($chain_sync);
    echo "\n";
    var_dump($rssi_tmp);
    var_dump($chain_rssi_db);
    var_dump($ext_number);
    var_dump($ext_name);
    var_dump($ext_ipei);
    var_dump($ext_fpidx);
    var_dump($ext_status);

  } else {
    header("Content-type: image/png");
    //system("echo ".escapeshellarg($graph)." | fdp -Tpng");
    system("echo ".escapeshellarg($graph)." | dot -Tpng");
  }
?>
