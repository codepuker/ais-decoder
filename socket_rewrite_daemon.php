<?php

ignore_user_abort(true);
set_time_limit(0);

$txleng=160;
$rxleng=160;


$socket_RX = stream_socket_server("udp://192.168.188.2:5555", $errno, $errstr, STREAM_SERVER_BIND);
do {
$pkt = stream_socket_recvfrom($socket_RX,$rxleng);


if ($pkt != "\n") {
	$buff.=$pkt;
} else {
	$out=$buff;
	$out2=$buff."\n";
	$buff="";
}



if ($out2 == $oldp) {
} else {
	




$sock1 = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
$sock2 = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
$sock3 = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
$sock4 = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

//81.15 linux
socket_sendto($sock2, $out, $txleng ,0,  '127.0.0.1', 22099);
//marinetraffic
socket_sendto($sock4, $out2, $txleng ,0,  '5.9.207.224', 5402);


$oldp=$out2;

}
} while ($pkt !== false);
?>
