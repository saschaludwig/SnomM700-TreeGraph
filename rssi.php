<pre>
<?php
  /*
  * Copyright (c) 2015 Sascha Ludwig / dienes.de
  * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
  */

  $multicell = join(" ", file("http://admin:admin@172.23.2.1/MultiCell.html"));

  $unhex   = function($value) { return substr($value, 2); };
  $hex2int = function($value) { return hexdec($value); };
  $rssi2db = function($value) { return $value-100; };

  preg_match("/.*SetSyncDectRssiChain\(\"(.*)\"\);.*/", $multicell, $data);
  $rssi = split(":", $data[1]);


  preg_match("/.*SetSyncMacChain\(\"(.*)\"\);.*/", $multicell, $data);
  $mac = split(",", $data[1]);



  var_dump($rssi);
  var_dump($mac);


?>
