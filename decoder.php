<?php
// Code written by Adelmo De Santis <adelmo@univpm.it>. Just a simple AIS Decoding Engine
//Copyright (C) 2011 Adelmo De Santis

//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.

//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.

//    You should have received a copy of the GNU General Public License
//    along with this program.  If not, see <http://www.gnu.org/licenses/>.

//Adjust time zone
date_default_timezone_set('Europe/Rome');
$timestamp=time();
$anno=date("Y",$timestamp);
$tablename="ais_stat_data_".$anno;

//Version 2

// Changelog:
//	1.2
//	MessageType 24 decoding added
//	1.3
//	MessageType 18,19 decoding added
//	Put most used functions in an external file
//	1.4
//	MessageType 21,27 decoding Added
//	Stats file won't overwrite each time the program is restarted
//	1.5
//	English translation for Google Code
//	1.6
//	Bug correction in bearing calculation
//	1.7
//	Minor bug correction
//	Checksum error management. Wrong checksummed packets are recorded as type=native_type+60
//	1.8
//	Bug correction on mysql insert function. Presence of "strange" char could lead program to crash
//	1.9
//	InsertName BUG correction. Database was cleaned.
//	Change of development tool: from "vim" to "aptana". 
//  1.91
//	A new table was added to database in order to make site quicker.
//	New record function inserted.
//  Minor changes.
// 	2
//	Two More tables added in order to record decoded messages of type 4, 11 and 21.
//  Minor bug corrections
//  2.1
//	This version was born after the main computer big crash.
//	Support for PHP7.0 - Minor Bug Corrections

include 'functions.php';

//If $DEBUG is set to 1, data are read from test arrays
$DEBUG=0;

//File Name vars

$rawfile="ais_data_18.raw";
$outfile="ais_process";
$statsfile="ais_stats_18.txt";

//MySQL connection vars 
$hostName="p:127.0.0.1";
$userName="ais";
$password="aisais";
$databaseName="ais";


//Global Vars, used as buffers
$pkt_buf="";
$buffer=array();
$buffer_C='';
$buffer_B='';
$buffer_A='';

//Global Vars to store data
$disbea=array("dis" => "0","bea" => "0","avedis" => "0", "avebea" => "0", "pre_avg" => "0", "pre_index" => "0");
$sessionstats=array("pkts" => "0", "msg_01" => "0","msg_01F" => "0", "msg_02" => "0","msg_02F" => "0","msg_03" => "0","msg_03F" => "0","msg_04" => "0","msg_04F" => "0","msg_05"=>"0","msg_05F" => "0","msg_06"=>"0","msg_06F" => "0","msg_07"=>"0","msg_07F" => "0","msg_08"=>"0","msg_08F" => "0","msg_09"=>"0","msg_09F" => "0","msg_10"=>"0","msg_10F" => "0","msg_11"=>"0","msg_11F" => "0","msg_12"=>"0","msg_12F" => "0","msg_13"=>"0","msg_13F" => "0","msg_14"=>"0","msg_14F" => "0","msg_15"=>"0","msg_15F" => "0","msg_16"=>"0","msg_16F" => "0","msg_17"=>"0","msg_17F" => "0","msg_18"=>"0","msg_18F" => "0","msg_19"=>"0","msg_19F" => "0","msg_20"=>"0","msg_20F" => "0","msg_21"=>"0","msg_21F" => "0","msg_22"=>"0","msg_22F" => "0","msg_23"=>"0","msg_23F" => "0","msg_24"=>"0","msg_24F" => "0","msg_25"=>"0","msg_25F" => "0","msg_26"=>"0","msg_26F" => "0","msg_27"=>"0","msg_27F" => "0","other" => "0","radioA" => "0", "radioB" => "0", "chksum_ok" => "0", "chksum_nok" => "0","max_distance" => "0","bear_to_max" => "0","max_mmsi"=>"0");

//
// Some DEbug Stuff to check Code 
//
$test_data_1=array("!AIVDM,1,1,,A,B5NH9bP0j@?SU36?13?7GwnUkP06,0*79","!AIVDM,1,1,,A,H5NH9bTU4I138D0G45ooni1@4220,0*00");


//Channel definition to let data "in".
$socket = stream_socket_server("udp://127.0.0.1:22099", $errno, $errstr, STREAM_SERVER_BIND);




function checkfile() {
global $statsfile;
	if (file_exists($statsfile)){
		echo "File $statsfile exists -> copying \n";
		$newfile=date('d-M-Y',time())."-".$statsfile;
		copy($statsfile,$newfile);
	}

}

function checksum_calc($str) {
	//This function is used to compute received string chechsum
	//Checksum is a bit-by-bit xor of the whole string, excluding leading ! and trailing *

	//Search for a "*" character in the string.
	$mychar='*';
	$pos=strpos($str,$mychar);
	if ($pos !== false) {
	        $substring=explode("*",$str);
        	$substring[0]=substr($substring[0],1);
        	$strtocheck=$substring[0];
        	$i=0;
        	$cksumdec=0;
        	while (isset($strtocheck{$i})) {

                	$cksumdec = $cksumdec ^ ord($strtocheck{$i});
                	++$i;
        	}
        	$cksumbin=base_convert($cksumdec,10,2);
		$origcheck=base_convert($substring[1],16,2);

		if ($cksumbin == $origcheck) {
                	return true;
        	} else {
                	return false;
        	}
	} else {
	//No '*" character found. So let's assume checksum is wrong!
	return false;
	}
}

function pre_pkt($pacchetto) {
	global $pkt_buf;
	//This function tries to solve the problem of multisentence messages. When a pkt is generated that is longer than
	//160 bit, it is split in two parts. The first is a regular 160 bit, the latter is shorter and bound to the first
	//using sequence numbers. Sometimes, due to the bad dispatcher we use, both packets are received as a single one, 
	//in which the leading "AIVDM" is present twice.
        $pkt_arr=array();
	
        $occurr=substr_count($pacchetto, '!AIVDM');

        switch ($occurr) {

        case 1:
                $frags=separa_campi($pacchetto);
                return $frags;
                break;

        case 2:
                $substring=explode("!AIVDM",$pacchetto);
                $pkt_arr[]="!AIVDM".$substring[1];
                $pkt_arr[]="!AIVDM".$substring[2];
                foreach ($pkt_arr as $key => $value) {
                        $frags=separa_campi($value);
                }
                return $frags;
                break;
        }
}


function DECtoDMS($dec)	{
	// ( Degrees / minutes / seconds ) 

	// This is the piece of code which may appear to 
	// be inefficient, but to avoid issues with floating
	// point math we extract the integer part and the float
	// part by using a string function.

    	$vars = explode(".",$dec);
    	$deg = $vars[0];
    	$tempma = "0.".$vars[1];

    	$tempma = $tempma * 3600;
    	$min = floor($tempma / 60);
    	$sec = $tempma - ($min*60);

    	return array("deg"=>$deg,"min"=>$min,"sec"=>$sec);
}


function statistics ($dis, $bea, $mmsi, $lat, $lon,$type){
	global $disbea,$sessionstats,$tablename,$conn;
	$timestamp=time();
	//echo "DEBUG: statistics\n";
	//echo "DEBUG: $type\n";
	//This function is used to quickly analyze data retrived from decoding functions.
	//Some information about distance, bearing and averages are stored in the array.
	//
	//What we want to compute:
	//maximum distance of received packet
	//average distance
	//bearing to max (the direction toward which we could point a virtual antenna to maximize signal)
	//
	
	//Maximum distance and corresponding bearing in the buffer array
	//"max_distance" => "0","bear_to_max" => "0"
	if ($dis > $disbea["dis"]) {
		$disbea["dis"]=$dis;
		$disbea["bea"]=$bea;
		$sessionstats["max_distance"]=$dis;
		$sessionstats["bear_to_max"]=$bea;
		$sessionstats["max_mmsi"]=$mmsi;
	}
	
	//Data retrived are to be inserted in a database for future reference and off-line anlysis;
	//
	//In order to avoid the DB-table to saturate a simple time-based decimation filter is implemented.
	//
	//Data are MMSI-bounded. Once they have been computed, a select is performed on the table to check timestamp
	//of MMSI for which we have new data. If delta t is shorter than 300 s (5 minutes) no insert is performed on table.
	//In this way we can dramatically reduce amount of data stored on the table, without loosing so much information.
	
	//Let's divide correct and incorrect checksums into different tables.
	if ($type >= 60){
		$result=mysqli_query($conn,"select timestamp from ais_stat_data where mmsi=$mmsi order by timestamp desc limit 2");
		$oldtimestamp=mysqli_fetch_row($result);
		$deltatime=($timestamp-$oldtimestamp[0]);
		//This should take care of the "first element ever" case
		if (($deltatime == $timestamp) || ($deltatime > 300)) {
			if (($dis != "NNN") || ($bea != "NNN")) {
	 			echo "Checksum ok -New Data Inserted\n";
				mysqli_query($conn,"INSERT INTO ais_stat_no_checksum (id,timestamp,MMSI,Latitude,Longitude,Distance,Bearing,Type)VALUES ('',$timestamp,'$mmsi','$lat','$lon','$dis','$bea','$type')");
			}
		}
	} else {
		$result=mysqli_query($conn, "select timestamp from ais_stat_data where mmsi=$mmsi order by timestamp desc limit 2");

		$oldtimestamp=mysqli_fetch_row($result);
	
		//If the previous query returned an element let's compute "deltatime" 
		$deltatime=($timestamp-$oldtimestamp[0]);

		//This should take care of the "first element ever" case
		if (($deltatime == $timestamp) || ($deltatime > 300)) {
			if (($dis != "NNN") || ($bea != "NNN")) {
	 			echo "Checksum ok -New Data Inserted\n";
				mysqli_query($conn,"INSERT INTO ais_stat_data (id,timestamp,MMSI,Latitude,Longitude,Distance,Bearing,Type)VALUES ('',$timestamp,'$mmsi','$lat','$lon','$dis','$bea','$type')");
				mysqli_query($conn,"INSERT INTO $tablename    (id,timestamp,MMSI,Latitude,Longitude,Distance,Bearing,Type)VALUES ('',$timestamp,'$mmsi','$lat','$lon','$dis','$bea','$type')");
				mysqli_query($conn,"INSERT INTO ais_stat_data_temp (id,timestamp,MMSI,Latitude,Longitude,Distance,Bearing,Type)VALUES ('',$timestamp,'$mmsi','$lat','$lon','$dis','$bea','$type')");
			}
		}
	}
	return;

}
    
function insertname($mmsi,$callsign,$vesselname) {
	//Names and Callsigns are handled in a separate table "ais_stat_data_name". First of all MMSI is checked. If an MMSI is present which has
	//an empty callsign or vessel name field, then the corresponding emty field is filled with missing information.
	global $conn;
	$timestamp=time();
	$result_name_mmsi=mysqli_query($conn,"select mmsi,callsign,vesselname from ais_stat_data_name where mmsi = $mmsi");
	$righe_name=mysqli_num_rows($result_name_mmsi);
	$risultato=mysqli_fetch_row($result_name_mmsi);
	$callsign_sql=$risultato[1];
	$vesselname_sql=$risultato[2];
	//echo "DEBUG insertname: callsign_sql $callsign_sql -- vessel $vesselname_sql \n";
	//echo "DEBUG insertname: mmsi $mmsi - callsign $callsign - vessel $vesselname \n";
	if ($righe_name != 0) {
		//echo "DEBUG Found a matching MMSI\n";
		//MMSI is already present in the table. Let's check for empty fields.
		if (($callsign_sql == "") and ($callsign!="")) {
			//echo "DEBUG: insertname: callsign inserted\n";
			mysqli_query($conn,"update ais_stat_data_name set callsign='$callsign' where ais_stat_data_name.mmsi = $mmsi");
			//echo "DEBUG: insertname: mmsi ok  update callsign \n";
		}
		if (($vesselname_sql == "") and ($vesselname != "")) {
			//echo "DEBUG: insertname: vesselname field update\n";
	                mysqli_query($conn,"update ais_stat_data_name set vesselname='$vesselname' where ais_stat_data_name.mmsi = $mmsi");
			//echo "DEBUG: insertname: update vesselname \n";
                }
		return ;
	} else {
		//echo "DEBUG: insertname: new field insertion\n";
		//Let's add some checks for string length in order to avoid crashes and bad behaviours.
		//Lenght of fields: callsign: 64 - vesselname: 64
		//More over let's purge strange chars
		$callsign_length=strlen($callsign);
		$vesselname_length=strlen($vesselname);
		if ($callsign_length > 64){
			$callsign=substr($callsign,0,64);
		}
		if ($vesselname_length > 64){
			$vesselname=substr($vesselname,0,64);
		}
		//$callsign=str_replace("'","",$callsign);
		//$vesselname=str_replace("'","",$vesselname);
		$real_escape_vessel=mysqli_real_escape_string($conn,$vesselname);
		$real_escape_callsign=mysqli_real_escape_string($conn,$callsign);
		$query_result=mysqli_query($conn,"INSERT INTO ais_stat_data_name (id,mmsi,callsign,vesselname,timestamp)VALUES ('','$mmsi','$real_escape_callsign','$real_escape_vessel','$timestamp')");
		if (!$query_result) {
                	die('Invalid query: ' . mysqli_error($conn));
                      	}
		//echo "DEBUG: insertname insert \n";
	}

}
function distance ($remote_plat,$remote_plon) {
//This function computes distance from a fixed point (receiver point) using a complex aplgorithm.
//Distance calculation is a though job as you have to take into consideration the elliptical shape of earth.
//This code was ported in PHP from the work of Chris Veness  http://www.movable-type.co.uk/scripts/latlong-vincenty.html
//
//Vincenty Inverse Solution of Geodesics on the Ellipsoid (c) Chris Veness 2002-2012
//

if ($remote_plat == "NA" & $remote_plon == "NA") {
	return array("dis"=>"NNN","brg"=>"NNN");
	}

//Radian conversion
$remote_plat_rad=deg2rad($remote_plat);
$remote_plon_rad=deg2rad($remote_plon);


//Ellipsoid parameter definition
$a=6378137;
$b=6356752.314245;
$f=1/298.257223563;
$limit=1E-12;

//Receiver Position Definition (GMS format)
$homelat_g=43;
$homelat_m=35;
$homelat_s=13;
$homelon_g=13;
$homelon_m=31;
$homelon_s=1;

//Receiver Position Definition (Decimal Form)
$homelat=43.586944;
$homelat_rad=deg2rad($homelat);
$homelon=13.516944;
$homelon_rad=deg2rad($homelon);


//Coordinates conversion (unused)
$conv_remote_plat=DECtoDMS($remote_plat);
$conv_remote_plon=DECtoDMS($remote_plon);

$remotelat_g=$conv_remote_plat["deg"];
$remotelat_p=$conv_remote_plat["min"];
$remotelat_s=$conv_remote_plat["sec"];


$remotelon_g=$conv_remote_plon["deg"];
$remotelon_p=$conv_remote_plon["min"];
$remotelon_s=$conv_remote_plon["sec"];

// Radians conversion. The whole algorithm is working in radians.
// functions involved deg2rad degrees      -> radians
//                    rad2deg radians      -> degrees

//Computing L (delta-longitude) and radians convertion
$L_dec=$homelon-$remote_plon;
$L_rad=deg2rad($L_dec);

//U1 ed U2 reduced latitude
$u1=atan((1-$f)*tan($remote_plat_rad));
$u2=atan((1-$f)*tan($homelat_rad));


$sinu1=sin($u1);
$sinu2=sin($u2);
$cosu1=cos($u1);
$cosu2=cos($u2);


$iterlimit=100;
$lambda=$L_rad;

do {
$sinLambda=sin($lambda);
$cosLambda=cos($lambda);
//echo "DEBUG sinL: $sinLambda -- cosL: $cosLambda\n";
$sinSigma=sqrt(($cosu2*$sinLambda)*($cosu2*$sinLambda)+($cosu1*$sinu2-$sinu1*$cosu2*$cosLambda)*($cosu1*$sinu2-$sinu1*$cosu2*$cosLambda));
//echo "DEBUG sinSigma: $sinSigma\n";
if ($sinSigma == 0) {
        return 0;
        }
$cosSigma=$sinu1*$sinu2+$cosu1*$cosu2*$cosLambda;
$sigma=atan2($sinSigma, $cosSigma);
$sinAlpha=$cosu1*$cosu2*$sinLambda/$sinSigma;
$cosSqAlpha=1-$sinAlpha*$sinAlpha;
$cos2SigmaM=$cosSigma-2*$sinu1*$sinu2/$cosSqAlpha;
if (is_nan($cos2SigmaM)) {
        $cos2SigmaM=0;
        }
$c=($f/16)*$cosSqAlpha*(4+$f*(4-3*$cosSqAlpha));
$lambdap=$lambda;
$lambda=$L_rad+(1-$c)*$f*$sinAlpha*($sigma+$c*$sinSigma*($cos2SigmaM+$c*$cosSigma*(-1+2*$cos2SigmaM*$cos2SigmaM)));
} while (abs($lambda-$lambdap) >$limit && --$iterlimit >0);

if ($iterlimit == 0) {
        return NaN;
        }

$uSq=$cosSqAlpha*($a*$a-$b*$b)/($b*$b);

$A=1+($uSq/16384)*(4096+$uSq*(-768+$uSq*(320-175*$uSq)));
$B=$uSq/1024*(256+$uSq*(-128+$uSq*(74-47*$uSq)));

$DeltaSigma=$B*$sinSigma*($cos2SigmaM+$B/4*($cosSigma*(1-2*$cos2SigmaM*$cos2SigmaM)-($B/6*$cos2SigmaM*(-3+4*$sinSigma*$sinSigma)*(-3+4*$cos2SigmaM*$cos2SigmaM))));

$s=$b*$A*($sigma-$DeltaSigma);
$s=round($s,0);

$fwdAz=atan2($cosu2*$sinLambda,$cosu1*$sinu2-$sinu1*$cosu2*$cosLambda);
$fwdAz_dec=rad2deg($fwdAz);
$fwdAz_nor=($fwdAz_dec+360)%360;

if ($fwdAz_nor >= 180) {
        //echo "DEBUG - Sopra 180\n";
        $gamma=360-$fwdAz_nor;
        $beta=180-$gamma;
        $exit=$beta;
        } else {
        //echo "DEBUG - Sotto 180\n";
        $beta=180+$fwdAz_nor;
        $exit=$beta;
        }


return array("dis"=>$s,"brg"=>$exit);

}

//----------------------------- END FUNCTION ---------------------------------------------------------


function field_explosion($stream) {
//This function put the comma-separated fields present in the original packet, in a buffer array, for further processing (print version -unused-)
	global $sessionstats;
	
	echo "Messaggio originale: $stream\n";
	$campi = explode(",", $stream);
        echo "Header         $campi[0]\n"; //header
        echo "Num. Framm.    $campi[1]\n"; //fragment number
        echo "Id. Framm.     $campi[2]\n"; //this message is fragment number ... (if 1,1 single message)
        echo "Num. Seq.      $campi[3]\n"; //message sequential number 
        echo "Canale Radio   $campi[4]\n"; //radio channel 
        echo "Mr. Payload    $campi[5]\n"; //payload -> this is the section we want to decode
        echo "Fill Bit e Chk $campi[6]\n"; //filling bits e checksum
        echo "\n\n";
	
	switch ($campi[4]) {
		case "A":
				++$sessionstats["radioA"];
				break;
		case "B":
				++$sessionstats["radioB"];
				break;
	}
        return $campi;

}

function field_explosion_np($stream) {
//This function put the comma-separated fields present in the original packet, in a buffer array, for further processing (no print version)
	global $sessionstats;
	
	$campi = explode(",", $stream);
	switch ($campi[4]) {
		case "A":
				++$sessionstats["radioA"];
				break;
		case "B":
				++$sessionstats["radioB"];
				break;
	}
        return $campi;

}


function separa_campi($dati_stream) {
	//This function act as the core of the whole program. It takes the received packet as input, it separates the fields using "field_explode"
	//and is able to track multisentence messages using sequence numbers. It returns an array in which all fields are stored to be further processed.
	//The return variable is $campi[5] which holds the payload to be further decoded.
	global $buffer, $buffer_C, $buffer_B, $buffer_A, $sessionstats, $chkflag;

	//Sometimes, due to some limitation in "php dispatecher" program, multisentece messages are decoded as a single packet in which the 
	//sentence AIVDM is written twice. This function takes care of this problem too.

	//Checksum calculation.
	if (checksum_calc($dati_stream)) {
		++$sessionstats["chksum_ok"];
		$chkflag=0;
		//echo "Checksum ok\n\n";
	} else {
		++$sessionstats["chksum_nok"];
		$chkflag=1;
		//echo "Checksum not ok\n\n";
	}
	$campi=field_explosion_np($dati_stream);
	$field_A=$campi[1];
	$field_B=$campi[2];
	$field_C=$campi[3];

	$payload='';

	switch ($field_A){
		case 1:
			//Condition !AIVDM,1,1,,allotherrubbish
			//in this case we have little to do. Message has to be decoded.
			if ($field_B == 1) {
				$buffer_A='';
				$buffer_B='';
				$buffer_C='';
				return $campi;
			}
			break;
		default :
			//Condition !AIVDM,2,x,y,allotherubbish
			if ($field_B == 1) {
			//Condition !AIVDM,2,1,y,allotherrubbish
				$buffer_A=$field_A;
				$buffer_B=$field_B;
				$buffer_C=$field_C;
				$buffer[]=$dati_stream;
			//In this case there is no need to set output variable as message is not complete. Any effort to decode this
			//message will fail so we put a "next" keyword in $campi[5] and let the river flow.
			$campi[5]="next";
			return $campi;
			} else {
			//Condition !AIVDM,2,x,y,allotherrubbish
				if (( $field_C == $buffer_C) & ( $field_B != $buffer_A)) {
					//This is the n+1 fragment. Is the one following the first we recorded.
					$buffer[]=$dati_stream;
					}
				if (( $field_C == $buffer_C) & ( $field_B == $buffer_A)) {
					//The last one of the multisentence group. Now it is time to close and start processing data
					$buffer[]=$dati_stream;
					//It is now time to process data which are held in the buffer. We have to bind all payloads
					//but we are not aware of the number of elements which are stored.	
					foreach ($buffer as $key => $value ) {
						$campi=field_explosion_np($value);
						$payload.=$campi[5];
					}
					$buffer_A='';
					$buffer_B='';
					$buffer_C='';
					$buffer=array();
 			}	
		}
			break;
	}
	$campi[5]=$payload;
	$output=$campi[5];
	return $campi;
}

function fwrite_stream($fp, $string) {
//This function is used to write the stream on a file in a continous way.
    for ($written = 0; $written < strlen($string); $written += $fwrite) {
        $fwrite = fwrite($fp, substr($string, $written));
        if ($fwrite === false) {
            return $written;
        }
    }
    return $written;
}

function fmt_binary($x, $numbits =6 ) {
//This function takes a decimal number as input and converts it in binary form. More over
//it groups bit 6 by 6.
	$bit=decbin($x);
	$bitcluster='';
        for ($x=0; $x< strlen($bit)/6; $x++) {
                $bitcluster .= ' '. substr($bit, $x*6, 6);
        }
        //echo "Bitcluster $bitcluster\n";
        return ltrim($bitcluster);

}


function fmt_binary1($x, $numbits =6 ) {
//This function takes a decimal number as input and converts it in binary form. More over
//it groups bit 6 by 6.
        $bin = decbin($x);
        $bin = substr(str_repeat(0,$numbits),0,$numbits - strlen($bin)) . $bin;
    // Get rid of first space.
    return ltrim($bin);
}


function bit_splitter($bitpattern, $numbits) {
//this function takes a bitpattern as input and produces a bitpattern as output
//but it splits the bit "numbits" by "numbits"
		
		$bitcluster='';

	        for ($x=0; $x< strlen($bit)/$numbits; $x++) {
        	        $bitcluster .= ' '. substr($bit, $x*$numbits, $numbits);
        	}
        return ltrim($bitcluster);

		
        }

function payload_to_bit($payload) {
//one of the most importan function in the whole program.
//It takes payload as input (ascii-chars) and convert it in a bit string.
//Ascii chars are converted in decimal form, and then 48 is subtracted. If result is >40, then 8 is subtracted. 
//Decimal result obtained are converted in binary form and tied toghether to form a unique bit string.
        $i=0;
        $numbits=8;
        $bitpattern=' ';
        while (isset($payload{$i})) {
                $test=ord($payload{$i});
                $bit=decbin($test);
                $test=$test-48;
                        if ($test > '40')
                                $test=$test-8;
                $bit_out=fmt_binary1($test);
                $bitpattern.= $bit_out;
                ++$i;
        }
        return($bitpattern);
}

function bit_to_char($bit) {
//From ais crazy way to represent information, to a more common, huma readable and machine oriented way
	$bitcluster='';
	$strout='';

        for ($x=0; $x< strlen($bit)/6; $x++) {
                $bitcluster .= ' '. substr($bit, $x*6, 6);
                $chardec=bindec($bitcluster);
                if ($chardec < 32) {
                        $chardec=$chardec+64;
                }
                $bitcluster='';
                $ascii=chr($chardec);
		$strout.=$ascii;

        }
        return $strout;

}


function _bin8dec($bin) {
    // Function to convert 8bit binary numbers to integers using two's complement
    $num = bindec($bin);
    if($num > 0xFF) { return false; }
    if($num >= 0x80) {
        return -(($num ^ 0xFF)+1);
    } else {
        return $num;
    }
}

function bin28dec($bin) {
        // Function to convert 8bit binary numbers to integers using two's complement
        $num = bindec($bin);
        if($num > 0xFFFFFFF) {return false; }
        if($num >= 0x8000000) {
        return -(($num ^ 0xFFFFFFF)+1);
        } else {
        return $num;
        }
}

function bin27dec($bin) {
        // Function to convert 8bit binary numbers to integers using two's complement
        $num = bindec($bin);
        if($num > 0x7FFFFFF) {return false; }
        if($num >= 0x4000000) {
        return -(($num ^ 0x7FFFFFF)+1);
        } else {
        return $num;
        }
}

function messagetype ($bit_payload) {
	$msgtype=bindec(substr($bit_payload,1,6));
	return $msgtype;
	}


function maxima ($type,$distance,$azimut,$id,$timest){
	global $conn;
	$timestamp=time();
	//this function is used to populate ais table ais_max-table.
	//it receives type of source and distance and compares distance value
	//with the stored one. If the calculated value is greater than the stored
	//let's UPDATE data.
	//Structure of data follows:
	//<1 letter> <number> <2 letter>
	//1 letter is one of following: S (ships) 
	//								F (fixed) 
	//								B (type B)
	//number is 1 to 4: 1 Distance
	//					2 Bearing or azimut
	//					3 MMSI
	//					4 Timestamp
	//2 letter is one of the following 	H (hour)
	//									D (day)
	//									W (week)
	//									A Absolute

	//echo "DEBUG \n Maxima Calling tipo $type,dis $distance, azi $azimut,mmsi $id, stamp $timest \n";
	if (($distance != "NNN") && ($azimut != "NNN")) {
	switch($type){
		case 1:	//ship information
				//SxH
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"S1H\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"S4H\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				//echo "DEBUG SxH: $distance - $max_sql_dist[0] -- $timestamp - $record_time[0]\n";
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])) {
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"S1H\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"S2H\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"S3H\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"S4H\"");
					echo "DEBUG Maxima type S1H updated\n";					
					}
				//SxD
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"S1D\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"S4D\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				//echo "DEBUG SxD: $distance - $max_sql_dist[0] -- $timestamp - $record_time[0]\n";
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])) {
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"S1D\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"S2D\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"S3D\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"S4D\"");
					echo "DEBUG Maxima type S1D updated\n";
					}
				//SxW
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"S1W\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"S4W\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				//echo "DEBUG SxW: $distance - $max_sql_dist[0] -- $timestamp - $record_time[0]\n";
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])) {
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"S1W\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"S2W\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"S3W\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"S4W\"");
					echo "DEBUG Maxima type S1W update\n";
					}
				//SxA
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"S1A\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"S4A\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				//echo "DEBUG SxA: $distance - $max_sql_dist[0] -- $timestamp - $record_time[0]\n";
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time)) {
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"S1A\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"S2A\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"S3A\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"S4A\"");
					echo "DEBUG Maxima type S1A update\n";
					}
				break;
				
		case 4:	//fix information
				//FxH
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"F1H\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"F4H\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])){
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"F1H\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"F2H\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"F3H\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"F4H\"");
					echo "DEBUG Maxima type F1H update\n";
					}
				//FxD
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"F1D\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"F4D\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])){
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"F1D\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"F2D\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"F3D\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"F4D\"");
					echo "DEBUG Maxima type F1D update\n";
					}
				//FxW
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"F1W\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"F4W\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])){
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"F1W\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"F2W\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"F3W\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"F4W\"");
					echo "DEBUG Maxima type F1W update\n";
					}
				//FxA
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"F1A\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"F4A\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])){
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"F1A\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"F2A\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"F3A\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"F4A\"");
					echo "DEBUG Maxima type F1A update\n";
					}
				break;
						
		case 8: //type B information
				//BxH
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"B1H\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"B4H\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])) {
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"B1H\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"B2H\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"B3H\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"B4H\"");
					echo "DEBUG Maxima type B1H new data \n";
					}
				//BxD
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"B1D\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"B4D\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])) {
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"B1D\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"B2D\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"B3D\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"B4D\"");
					echo "DEBUG Maxima type B1D new data \n";
					}
				//BxW
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"B1W\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"B4W\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])) {
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"B1W\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"B2W\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"B3W\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"B4W\"");
					echo "DEBUG Maxima type B1W new data \n";
					}
				//BxA
				$result_ship_dist=mysqli_query($conn,"select value from ais_max_record where type=\"B1A\"");
				$result_ship_time=mysqli_query($conn,"select value from ais_max_record where type=\"B4A\"");
				$max_sql_dist=mysqli_fetch_row($result_ship_dist);
				$record_time=mysqli_fetch_row($result_ship_time);
				if (($distance > $max_sql_dist[0]) && ($timestamp > $record_time[0])) {
					mysqli_query($conn,"update ais_max_record set value=$distance where type=\"B1A\"");
				 	mysqli_query($conn,"update ais_max_record set value=$azimut where type=\"B2A\"");
					mysqli_query($conn,"update ais_max_record set value=$id where type=\"B3HA\"");
					mysqli_query($conn,"update ais_max_record set value=$timest where type=\"B4HA\"");
					echo "DEBUG Maxima type B1A new data \n";
				}
				break;
	} //switch	
	} //if
}

//------------------------------------------------------------------Decoding Messagetype  1 2 3 -----------------------------------------------------
function cnb_field_13($bit_payload) {
	global $chkflag;
	global $conn;
		$timestamp=time();
        $i=0;
        $wks_payload=$bit_payload;
	//Number of bits
        while (isset ($wks_payload{$i})) {
                ++$i;
        }
        --$i;
        if ( $i != '168' ) {
                echo "Somthing is wrong cnb_field_test_13 $i\n";
        }
        $fields[0]=substr($bit_payload,1,6);    //Message Type
        $fields[1]=substr($bit_payload,7,2);    //Message repeat count
        $fields[2]=substr($bit_payload,9,30);   //MMSI
        $fields[3]=substr($bit_payload,39,4);   //Navigation Status
        $fields[4]=substr($bit_payload,43,8);   //Rate Of Turn (ROT)
        $fields[5]=substr($bit_payload,51,10);  //Speed Over Ground (SOG)
        $fields[6]=substr($bit_payload,61,1);   //Position Accuracy
        $fields[7]=substr($bit_payload,62,28);  //Longitude
        $fields[8]=substr($bit_payload,90,27);  //Latitude
        $fields[9]=substr($bit_payload,117,12); //Course Over Ground (COG)
        $fields[10]=substr($bit_payload,129,9); //True Heading (HDG)
        $fields[11]=substr($bit_payload,138,6); //Time Stamp
        $fields[12]=substr($bit_payload,144,2); //Maneuver Indicator
        $fields[13]=substr($bit_payload,146,3); //Spare
        $fields[14]=substr($bit_payload,147,1); //RAIM Flag
        $fields[15]=substr($bit_payload,150,19);//Radio Status
        
        //Decodifica del primo campo -- Matrice 0 -- Message Type
	if ($chkflag){
		$d_fields[0]=field_decode_01($fields[0])+60;}
	else {
		$d_fields[0]=field_decode_01($fields[0]);
	}

        //Decodifica del secondo campo -- Matrice 1 -- Repeat Indicator
	$d_fields[1]=field_decode_02($fields[1]);

        //Decodifica del terzo campo -- Matrice 2 --  MMSI
        $d_fields[2]=field_decode_03($fields[2]);

        //Decodifica del quarto campo -- Matrice 3 -- Navigation Status
	$d_fields[3]=decnavstatus($fields[3]);

	//Decodifica del quinto campo -- Matrice 4 -- Rate of Turn
        //Uso della funzione _bin8dec($bin) trovata su php.net
        $temp5th=_bin8dec($fields[4]);
	$d_fields[4]="NaN";
        switch ($temp5th) {
		case   0 :
			//echo "case 0 \n";
			$d_fields[4]="Not Turning";
			break;
                case  127:
                        //echo "case 127 \n";
                        $d_fields[4]="Turnig right - No TI";
                        break;
                case -127:
                        //echo "case -127 \n";
                        $d_fields[4]="Turnig left - No TI";
                        break;
                case  128:
                        //echo "case 128\n";
                        $d_fields[4]="No Turning Information";
                        break;
                default :
                        //echo "default\n";
                        if ((1< $temp5th) && ($temp5th < 126)) {
                                 $temp5th1=pow($temp5th/4.733,2);
                                 $d_fields[4]= "Turning right at $temp5th1 degrees per minute";
                        }
                        if ((-126 < $temp5th) && ($temp5th < -1)) {
                                 $temp5th1=pow($temp5th/4.733,2);
                                 $d_fields[4]= "Turning left at $temp5th1 degrees per minute";
                        }
			break;
                }


        //Decodifica del sesto campo -- Matrice 5 -- Speed Over Ground
	$d_fields[5]=speed_over_ground($fields[5]);

        //Decodifica del settimo campo -- Matrice 6 -- Position Accuracy
	$d_fields[6]=posac_decode($fields[6]);	

        //Decodifica dell'ottavo campo -- Matrice 7 -- Longitude
        $d_fields[7]=field_decode_longitude($fields[7]);

        //Decodifica del nono campo -- Matrice 8 -- Latitude
        $d_fields[8]=field_decode_latitude($fields[8]);

        //Decodifica del decimo campo -- Matrice 9 -- Course over Ground
	$d_fields[9]=cog($fields[9]);

        //Decodifica del decimoprimo campo -- Matrice 10 -- True Heading
        $temp11th=bindec($fields[10]);
         if ($temp11th == 511 ) {
                $d_fields[10]="TH: no data";
        }
        else {
                $d_fields[10]=$temp11th;
        };

        //Decodifica del decimosecondo campo -- Matrice 11 -- Time Stamp
        $temp12th=bindec($fields[11]);
        switch ($temp12th) {
                case 60:
                        $d_fields[11]="Timestamp not available";
                        break;
                case 61:
                        $d_fields[11]="Positioning system manual input mode";
                        break;
                case 62:
                        $d_fields[11]="Positioning System in estimated mode";
                        break;
                case 63:
                        $d_fields[11]="Positioning system not operative";
                        break;
                default:
                         $d_fields[11]=$temp12th;
                }

        //Decodifica del decimoterzo campo -- Matrice 12 -- Maneuver Indicator
        switch ($fields[12]) {
                case 0:
                        $d_fields[12]="Not available";
                        break;
                case 1:
                        $d_fields[12]="No special Maneuver";
                        break;
                case 2:
                        $d_fields[12]="Special Maneuver";
                        break;
                }

        //Decodifica del decimoquarto campo -- Matrice 13 -- Spare
		$d_fields[13]="";
   	//Decodifica del decimoquindo campo -- Matrice 14 -- RAIM Flag
	$d_fields[14]=field_decode_raim ($fields[14]);

        //Decodifica del decimosesto campo -- Matrice 15 -- Radio Status
	$d_fields[15]=field_decode_radio($fields[15],$d_fields[0]);
        
	//Let's compute distance between target point and receiver position.
	//Heading is computed too.
	//Fields 	$d_fields[7] longitude
	//		$d_fileds[8] latitude.
	//Value are passed to distance calculating function and result is stored into two fields of result array.
	//distance e heading $d_fileds[16] e $d_fields[17]
	//distance ($remote_plat,$remote_plon)  return array("dis"=>$s,"brg"=>$fwdAz_nor);
	$distanza=distance($d_fields[8],$d_fields[7]);
	//$d_fields[16]=number_format($distanza["dis"], 0, ',', '.');
	$d_fields[16]=$distanza["dis"];
	$d_fields[17]=$distanza["brg"];
		
	//Calling function to record some statistics for this messagetype
	//[16] distanza [17] azimut [2] mmsi [8] latitude [7] longitude 
	if ($chkflag){
		statistics($d_fields[16],$d_fields[17],$d_fields[2],$d_fields[8],$d_fields[7],61);
		}
        else {
		statistics($d_fields[16],$d_fields[17],$d_fields[2],$d_fields[8],$d_fields[7],1);
		maxima(1,$d_fields[16],$d_fields[17],$d_fields[2],$timestamp);
        }



	return ($d_fields);

}
//---------------------------------------------------------------------END OF MessageType 1 2 3 ----------------------------------------------------



//--------------------------------------------------- Decoding Messagetype 4 and 11 --------------------------------------------------------------------
function base_station_report_4($bit_payload) {
	global $chkflag;
	global $conn;
		$timestamp=time();
        //Messagetype 4 decoding function
         $i=0;
        $wks_payload=$bit_payload;
        //Bit Number Check
        while (isset ($wks_payload{$i})) {
                ++$i;
        }
        --$i;
        if ( $i != '168' ) {
                echo "eport $i\n";
        }
	
		$fields[0]=substr($bit_payload,1,6);    	//Message Type
        $fields[1]=substr($bit_payload,7,2);    	//Repeat Indicator
        $fields[2]=substr($bit_payload,9,30);   	//MMSI 
        $fields[3]=substr($bit_payload,39,14);   	//Year
        $fields[4]=substr($bit_payload,53,4);   	//Month
        $fields[5]=substr($bit_payload,57,5);  		//Day
        $fields[6]=substr($bit_payload,62,5);   	//Hour
        $fields[7]=substr($bit_payload,67,6);  		//Minute
        $fields[8]=substr($bit_payload,73,6);  		//Second
        $fields[9]=substr($bit_payload,79,1); 		//Fix Quality
        $fields[10]=substr($bit_payload,80,28); 	//Longitude
        $fields[11]=substr($bit_payload,108,27); 	//Latitude
        $fields[12]=substr($bit_payload,135,4); 	//Type of EPFD
        $fields[13]=substr($bit_payload,139,1); 	//tc Control
        $fields[14]=substr($bit_payload,140,9); 	//spare
        $fields[15]=substr($bit_payload,149,1); 	//RAIM
        $fields[16]=substr($bit_payload,150,19); 	//SOTDMA

	//Decodifica del primo campo -- Matrice 0 - Message Type
	//Type 4:  report from base station
	//Type 11: report from mobile station
	
	if ($chkflag){
                $d_fields[0]=field_decode_01($fields[0])+60;}
        else {
                $d_fields[0]=field_decode_01($fields[0]);
        }

	//Decodifica del secondo campo -- Matrice 1 -- Repeat Indicator
	$d_fields[1]=field_decode_02($fields[1]);

	//Decodifica del terzo campo -- Matrice 2 -- MMSI
	$d_fields[2]=field_decode_03($fields[2]);

	//Decodifica del quarto campo -- Matrice 3 -- YEAR
	$temp4th=bindec($fields[3]);
	if ($temp4th == 0) {
		$d_fields[3]="N/A";
	} else {
		$d_fields[3]=$temp4th;
	}	

	//Decodifica del quinto campo -- Matrice 4 -- Month
	$temp5th=bindec($fields[4]);
	if ($temp5th == 0) {
		$d_fields[4]="N/A";
	} else {
		$d_fields[4]=$temp5th;
	}	
	

	//Decodifica del sesto campo -- Matrice 5 -- Day
	$temp6th=bindec($fields[5]);
	if ($temp6th == 0) {
		$d_fields[5]="N/A";
	} else {
		$d_fields[5]=$temp6th;
	}	


	//Decodifica del settimo campo -- matrice 6 -- Hour
	$temp7th=bindec($fields[6]);
	if ($temp7th == 24) {
		$d_fields[6]="N/A";
	} else {
		$d_fields[6]=$temp7th;
	}	


	//Decodifica dell'ottavo campo -- Matrice 7 -- Minute
	$temp8th=bindec($fields[7]);
	if ($temp8th == 60) {
		$d_fields[7]="N/A";
	} else {
		$d_fields[7]=$temp8th;
	}	


	//Decodifica del nono campo -- Matrice 8 -- Second 
	$temp9th=bindec($fields[8]);
	if ($temp9th == 60) {
		$d_fields[8]="N/A";
	} else {
		$d_fields[8]=$temp9th;
	}	


	//Decodifica del decimo campo -- Matrice 9 -- Fix Quality 
	$d_fields[9]=posac_decode($fields[9]);

	//Decodifica del decimoprimo campo -- Matrice 10 -- Longitude
	$d_fields[10]=field_decode_longitude($fields[10]);

	//Decodifica del decimosecondo campo -- Matrice 11 -- Latitude
	$d_fields[11]=field_decode_latitude($fields[11]);


	//Decodifica del decimoterzo campo -- Matrice 12 -- Type of EPFD
	$d_fields[12]=field_decode_epfd($fields[12]);

	//Decodifica del decimoquarto campo -- Matrice 13 -- Transmission Control Long Range
	$d_fields[13]=$fields[13];
	
	//Decodifica del decimoquinto campo -- Matrice 14 -- Spare
	$d_fields[14]=$fields[14];

	//Decodifica del decimoquinto campo -- Matrice 15 -- RAIM Flag
	$d_fields[15]=field_decode_raim($fields[15]);

	//Decodifica del decimosesto campo -- Matrice 16 -- SOTDMA Radio
	$d_fields[16]=field_decode_radio($fields[16],$d_fields[0]);

 	//Let's compute distance between target point and receiver position.
        //Heading is computed too.
        //Fields        $d_fields[7] longitude
        //              $d_fileds[8] latitude.
        //Value are passed to distance calculating function and result is stored into two fields of result array.
        //distance e heading $d_fileds[17] e $d_fields[18]
        //distance ($remote_plat,$remote_plon)  return array("dis"=>$s,"brg"=>$fwdAz_nor);
        $distanza=distance($d_fields[11],$d_fields[10]);
        //$d_fields[17]=number_format($distanza["dis"], 0, ',', '.');
        $d_fields[17]=$distanza["dis"];
        $d_fields[18]=$distanza["brg"];

	//Calling function to record some statistics for this messagetype
	//$d_fields[16]= distanza, $d_fields[17]=azimut, $d_fields[2]=mmsi, $d_fields[11]=latitude, $d_fields[10]=longitude, $d_fields[0]=type
	statistics($d_fields[17],$d_fields[18],$d_fields[2],$d_fields[11],$d_fields[10],$d_fields[0]);
	maxima(4,$d_fields[17],$d_fields[18],$d_fields[2],$timestamp);
    
	//Now let's insert data in a special "type 4 and 11" table in order to make some analysis easier.
	$result=mysqli_query($conn,"select timestamp from ais_type_4_11_data where mmsi=$d_fields[2] order by timestamp desc limit 2");
	$oldtimestamp=mysqli_fetch_row($result);
	$deltatime=($timestamp-$oldtimestamp[0]);

	//This should take care of the "first element ever" case
	if (($deltatime == $timestamp) || ($deltatime > 300)) {
	 	echo "New Data Inserted\n";
		echo "DEBUG: '$timestamp','$d_fields[0]','$d_fields[1]','$d_fields[2]','$d_fields[3]','$d_fields[4]','$d_fields[5]','$d_fields[6]','$d_fields[7]','$d_fields[8]','$d_fields[9]','$d_fields[10]','$d_fields[11]','$d_fields[12]','$d_fields[13]','$d_fields[15]','$d_fields[16]','$d_fields[17]','$d_fields[18]' \n";
		mysqli_query($conn,"INSERT INTO ais_type_4_11_data (`id`,`timestamp`,`msg_id`,`repeat`,`MMSI`,`utc_y`,`utc_m`,`utc_d`,`utc_h`,`utc_p`,`utc_s`,`pos_acc`,`Longitude`,`Latitude`,`type_position`,`tc_control`,`raim`,`com_state`,`distance`,`Bearing`)VALUES ('','$timestamp','$d_fields[0]','$d_fields[1]','$d_fields[2]','$d_fields[3]','$d_fields[4]','$d_fields[5]','$d_fields[6]','$d_fields[7]','$d_fields[8]','$d_fields[9]','$d_fields[10]','$d_fields[11]','$d_fields[12]','$d_fields[13]','$d_fields[15]','$d_fields[16]','$d_fields[17]','$d_fields[18]')");
	}
	    
    return ($d_fields);



}

//---------------------------------------------------END OF MessageType 4--------------------------------------------------------------------------------

//---------------------------------------------------------------------DECODIFICA MESSAGGI TIPO 5 --------------------------------------------
function static_voyage_data($bit_payload) {
	global $chkflag;
	global $conn;
        //Messagetype 5 decoding function. Not all fields are decoded as we are interested only in vessel name and callsign
         $i=0;
        $wks_payload=$bit_payload;
        while (isset ($wks_payload{$i})) {
                ++$i;
        }
        --$i;
        if ( $i != '426' ) {
                echo "Something is wrong static_voyage_data $i\n";
        }
        $fields[0]=substr($bit_payload,1,6);    	//Message Type
        $fields[1]=substr($bit_payload,7,2);    	//Repeat Indicator
        $fields[2]=substr($bit_payload,9,30);   	//MMSI
        $fields[3]=substr($bit_payload,39,2);   	//AIS Version
        $fields[4]=substr($bit_payload,41,30);   	//IMO Number
        $fields[5]=substr($bit_payload,71,42);  	//Call Sign
        $fields[6]=substr($bit_payload,113,120);   	//Vessel Name
        $fields[7]=substr($bit_payload,233,8);  	//Ship Type
        $fields[8]=substr($bit_payload,241,9);  	//Dimension to bow
        $fields[9]=substr($bit_payload,250,9); 		//Dimension to stern
        $fields[10]=substr($bit_payload,259,6); 	//Dimension to port
        $fields[11]=substr($bit_payload,265,6); 	//Dimension to Starboard
        $fields[12]=substr($bit_payload,271,4); 	//Position Fix Type
        $fields[13]=substr($bit_payload,275,4); 	//ETA Month
        $fields[14]=substr($bit_payload,279,5); 	//ETA Day
        $fields[15]=substr($bit_payload,284,5); 	//ETA Hour
        $fields[16]=substr($bit_payload,289,6); 	//ETA minute
        $fields[17]=substr($bit_payload,295,8); 	//Draught
        $fields[18]=substr($bit_payload,303,120); 	//Destination
        $fields[19]=substr($bit_payload,423,1); 	//DTE
        $fields[20]=substr($bit_payload,424,1); 	//Spare

        //Decodifica del primo campo -- Matrice 0 -- Message Type
	if ($chkflag){
                $d_fields[0]=field_decode_01($fields[0])+60;}
        else {
                $d_fields[0]=field_decode_01($fields[0]);
        }
        //Decodifica del secondo campo -- Matrice 1 -- Repeat Indicator
        $d_fields[1]=field_decode_01($fields[1]);

        //Decodifica del terzo campo -- Matrice 2 --  MMSI
        $d_fields[2]=field_decode_01($fields[2]);

        //Decodifica del quarto campo - Matrice 3 -- AIS Version
        $d_fields[3]=bindec($fields[3]);

        //Decodifica del quinto campo - Matrice 4 -- IMO Number
        $d_fields[4]=bindec($fields[4]);

        //Decodifica del sesto campo  - Matrice 5 -- Call Sign
        //echo "DEBUG: campo 5 call sign: $fields[5]\n";
        $d_fields[5]=str_replace("@","",bit_to_char($fields[5]));

        //Decodifica del settimo campo - Matrice 6 -- Vessel Name
        //echo "DEBUG: campo 6 vessel name: $fields[6]\n";
        $d_fields[6]=str_replace("@","",bit_to_char($fields[6]));

	insertname($d_fields[2],$d_fields[5],$d_fields[6]);

        return ($d_fields);


}
//---------------------------------------------------END OF MessageType 5--------------------------------------------------------------------------------
//---------------------------------------------------iMessageType  18--------------------------------------------------------------------------------
function standard_classB_18($bit_payload){
	global $chkflag;
	global $conn;
	global $timestamp;
	// MessageType 18 decoding. "Standard Class B Position Report". All functions used here where developed for previous field
	//decoding.
	
	$i=0;
        $wks_payload=$bit_payload;
        //Payload's Length check 168
        while (isset ($wks_payload{$i})) {
                ++$i;
        }
        --$i;
        if ( $i != '168' ) {
                echo "something is wrong in MessageType 18 $i\n";
        }
        //echo "Indice della conta $i\n";
        $fields[0]=substr($bit_payload,1,6);    	//Message Type
        $fields[1]=substr($bit_payload,7,2);    	//Message repeat count
        $fields[2]=substr($bit_payload,9,30);   	//MMSI
        $fields[3]=substr($bit_payload,39,8);   	//Regional Reserved
        $fields[4]=substr($bit_payload,47,10);   	//Speed Over Ground
        $fields[5]=substr($bit_payload,57,1);  		//Position Accuracy
        $fields[6]=substr($bit_payload,58,28);   	//Longitude
        $fields[7]=substr($bit_payload,86,27);		//Latitude
        $fields[8]=substr($bit_payload,113,12);  	//Course Over Ground
        $fields[9]=substr($bit_payload,125,9); 		//True Heading
        $fields[10]=substr($bit_payload,134,6); 	//Time Stamp
        $fields[11]=substr($bit_payload,140,2); 	//Regional Reserved
        $fields[12]=substr($bit_payload,142,1); 	//CS Unit
        $fields[13]=substr($bit_payload,143,1); 	//Display Flag
        $fields[14]=substr($bit_payload,144,1); 	//Dsc Flag
        $fields[15]=substr($bit_payload,145,1);		//Band Flag
        $fields[16]=substr($bit_payload,146,1);		//Message 22 Flag
        $fields[17]=substr($bit_payload,147,1);		//Assigned
        $fields[18]=substr($bit_payload,148,1);		//RAIM Flag
        $fields[19]=substr($bit_payload,149,20);	//Radio
        //Debug: stampa tutta la matrice
        //for ($j=0; $j<=15; $j++) {
        //        echo "Indice $j valore $fields[$j]\n";
        //}


	 //Decodifica del primo campo -- Matrice 0 -- Message Type
	if ($chkflag){
                $d_fields[0]=field_decode_01($fields[0])+60;}
        else {
                $d_fields[0]=field_decode_01($fields[0]);
        }
        //Decodifica del secondo campo -- Matrice 1 -- Repeat Indicator
        $d_fields[1]=field_decode_02($fields[1]);

        //Decodifica del terzo campo -- Matrice 2 --  MMSI
        $d_fields[2]=field_decode_03($fields[2]);

	//Decodifica del quarto campo -- Matrice 3 -- Regional Reserved
	$d_fields[3]="NA";

	//Decodifica del quinto campo -- Matrice 4 -- Speed Over Ground
	$d_fields[4]=speed_over_ground($fields[4]);

	//Decodifica del sesto campo  -- Matrice 5 -- Position Accuracy
	$d_fields[5]=posac_decode($fields[5]);

	//Decodifica del settimo campo -- Matrice 6 -- Longitude
	$d_fields[6]=field_decode_longitude($fields[6]);

	//Decodifica del ottavo campo -- Matrice 7 -- Latitude
	$d_fields[7]=field_decode_latitude($fields[7]);

	//Decodifica del non campo -- Matrice 8 -- Course Over Ground
	$d_fields[8]=cog($fields[8]);

	//Decodifica del decimo campo -- Matrice 9 -- True Heading
	$temp9th=bindec($fields[9]);
         if ($temp9th == 511 ) {
                $d_fields[9]="TH: no data";
        }
        else {
                $d_fields[9]=$temp9th;
        };

	//Decodifica del decimoprimo campo -- Matrice 10 -- Time Stamp
	$d_fields[10]=bindec($fields[10]);

	//Decodifica del decimosecondo campo -- Matrice 11 -- Regional Reserved
	$d_fields[11]=" ";

	//Decodifica del decimoterzo campo -- Matrice 12 -- CS Unit
	switch ($fields[12]) {
		case 0:
			$d_fields[12]="Class B SOTDMA";
			break;
		case 1:
			$d_fields[12]="Class B CS";
			break;
	}

	//Decodifica del decimoquarto campo -- Matrice 13 -- Display Flag
	switch ($fields[13]) {
                case 0:
                        $d_fields[13]="No visual Display";
                        break;
                case 1:
                        $d_fields[13]="Visual Display";
                        break;
        }

	//Decodifica del decimoquinto campo -- Matrice 14 -- DSC Flag
	switch ($fields[14]) {
                case 1:
                        $d_fields[14]="VHF voice with DSC";
                        break;
                default:
                        $d_fields[14]="No DSC capability";
                        break;
        }

	//Decodifica del decimosesto campo -- Matrice 15 -- Band Flag
	switch ($fields[15]) {
                case 1:
                        $d_fields[15]="Unit can use any part of maritime band";
                        break;
                default:
                        $d_fields[15]="Unit can not user any part of maritime band";
                        break;
        }


	//Decodifica del decimosettimo campo -- Matrice 16 -- Message 22 Flag
	switch ($fields[16]) {
                case 1:
                        $d_fields[16]="Channel Assignment via message 22";
                        break;
                default:
                        $d_fields[16]="NO channel assignment via message 22";
                        break;
        }

	//decodifica del decimoottavo campo -- Matrice 17 -- Assigned
	switch ($fields[17]) {
                case 1:
                        $d_fields[17]="Autonomous Mode";
                        break;
                default:
                        $d_fields[17]="Assigned Mode";
                        break;
        }

	//Decodifica del decimonono campo -- Matrice 18 -- RAIM Flag
	$d_fields[18]=field_decode_raim($fields[18]);

	//Decodifica del ventesimo campo -- Matrice 19 -- Radio
	$d_fields[19]=field_decode_radio($fields[19],$d_fields[0]);

	//Distance calculation. Same as before!
        $distanza=distance($d_fields[7],$d_fields[6]);
        $d_fields[20]=$distanza["dis"];
        $d_fields[21]=$distanza["brg"];
		//$d_fields[20]=distance, $d_fields[21]=azimut, 
        statistics($d_fields[20],$d_fields[21],$d_fields[2],$d_fields[7],$d_fields[6],$d_fields[0]);
		maxima(8,$d_fields[20],$d_fields[21],$d_fields[2],$timestamp);


	return ($d_fields);

}
//--------------------------------------------------- END OF Messagetype 18--------------------------------------------------------------------------------

//--------------------------------------------------- Messagetype  19--------------------------------------------------------------------------------
function extended_classB_19($bit_payload){
	global $chkflag;
	global $conn;
        //Something very close to messagetype 18, but with extended information provided!

        $i=0;
        $wks_payload=$bit_payload;
        //Check for payload lenght 168
        while (isset ($wks_payload{$i})) {
                ++$i;
        }
        --$i;
        if ( $i != '312' ) {
                echo "Something is wrong in extended_classB_19 $i\n";
        }
        //echo "Indice della conta $i\n";
        $fields[0]=substr($bit_payload,1,6);            //Message Type
        $fields[1]=substr($bit_payload,7,2);            //Message repeat count
        $fields[2]=substr($bit_payload,9,30);           //MMSI
        $fields[3]=substr($bit_payload,39,8);           //Regional Reserved
        $fields[4]=substr($bit_payload,47,10);          //Speed Over Ground
        $fields[5]=substr($bit_payload,57,1);           //Position Accuracy
        $fields[6]=substr($bit_payload,58,28);          //Longitude
        $fields[7]=substr($bit_payload,86,27);          //Latitude
        $fields[8]=substr($bit_payload,113,12);         //Course Over Ground
        $fields[9]=substr($bit_payload,125,9);          //True Heading
        $fields[10]=substr($bit_payload,134,6);         //Time Stamp
        $fields[11]=substr($bit_payload,140,4);         //Regional Reserved
        $fields[12]=substr($bit_payload,144,120);       //Name
        $fields[13]=substr($bit_payload,264,8);         //Type Of Ship
        $fields[14]=substr($bit_payload,272,9);         //Dim to bow
        $fields[15]=substr($bit_payload,281,9);         //Dim to Stern
        $fields[16]=substr($bit_payload,290,6);         //Dim to Port
        $fields[17]=substr($bit_payload,296,6);         //Dim to StarBoard
        $fields[18]=substr($bit_payload,302,4);         //Position Fix Type
        $fields[19]=substr($bit_payload,306,1);        	//RAIM
        $fields[20]=substr($bit_payload,307,1);        	//DTE
        $fields[21]=substr($bit_payload,308,1);        	//Assigned Mode
        $fields[22]=substr($bit_payload,309,4);        	//Assigned Mode
        //Debug: stampa tutta la matrice
        //for ($j=0; $j<=15; $j++) {
        //        echo "Indice $j valore $fields[$j]\n";
        //}

	 //Decodifica del primo campo -- Matrice 0 -- Message Type
	if ($chkflag){
                $d_fields[0]=field_decode_01($fields[0])+60;}
        else {
                $d_fields[0]=field_decode_01($fields[0]);
        }

        //Decodifica del secondo campo -- Matrice 1 -- Repeat Indicator
        $d_fields[1]=field_decode_02($fields[1]);

        //Decodifica del terzo campo -- Matrice 2 --  MMSI
        $d_fields[2]=field_decode_03($fields[2]);

        //Decodifica del quarto campo -- Matrice 3 -- Regional Reserved
        $d_fields[3]="NA";

	//Decodifica del quinto campo -- Matrice 4 -- Speed Over Ground
	$d_fields[4]=speed_over_ground($fields[4]);

	//Decodifica del sesto campo -- Matrice 5 -- Position Accuracy
	$d_fields[5]=posac_decode($fields[5]);

	//Decodifica del settimo campo -- Matrice 6 --  Longitude 
	$d_fields[6]=field_decode_longitude($fields[6]);

	//Decodifica del ottavo campo -- Matrice 7 -- Latitude
	$d_fields[7]=field_decode_latitude($fields[7]);

	//Decodifica del nono campo -- Matrice 8 -- Course Over Ground
	$d_fields[8]=cog($fields[8]);

	//Decodifica del decimo campo -- Matrice 9 -- True Heading
	$temp9th=bindec($fields[9]);
         if ($temp9th == 511 ) {
                $d_fields[9]="TH: no data";
        }
        else {
                $d_fields[9]=$temp9th;
        };

	//Decodifica del decimoprimo campo -- Matrice 10 -- Time Stamp
	$d_fields[10]=bindec($fields[10]);

	//Decodifica del decimosecondo campo -- Matrice 11 -- Regional Reserved
	$_fields[11]=" ";

	//Decodifica del decimoterzo campo -- Matrice 12 -- Name
	$d_fields[12]=str_replace("@","",bit_to_char($fields[12]));
        insertname($d_fields[2],"",$d_fields[12]);

	//Decodifica del decimoquarto campo -- Matrice 13 -- Type Of Ship
	$d_fields[13]=shiptype($fields[13]);
	
	//Decodifica del decimoquinto campo -- Matrice 14 -- Dim to Bow
	$d_fields[14]=bindec($fields[14]);

	//Decodifica del decimosesto campo -- Matrice 15 -- Dim to Stern
	$d_fields[15]=bindec($fields[15]);

	//Decodifica del decimosettimo campo -- Matrice 16 -- Dim to Port
	$d_fields[16]=bindec($fields[16]);

	//Decodifica del decimoottavo campo -- Matrice 17 -- Dim to StarBoard
	$d_fields[17]=bindec($fields[17]);

	//Decodifica del decimonono campo -- Matrice 18 -- Position Fix Type
	$d_fields[18]=field_decode_epfd($fields[18]);

	//Decodifica del ventesimo campo -- Matrice 19 -- RAIM Flag
	$d_fields[19]=field_decode_raim($fields[19]);

	//Decodifica del ventesimoprimo campo -- Matrice 20 -- DTE
	switch ($fields[20]) {
                case 0:
                        $d_fields[20]="Data Terminal Ready";
                        break;
                case 1:
                        $d_fields[20]="Not Ready";
                        break;
        }
	
	//Decodifica del ventesimosecondo campo -- Matrice 21 -- Assigned Mode
	switch ($fields[21]) {
                case 1:
                        $d_fields[21]="Autonomous Mode";
                        break;
                default:
                        $d_fields[21]="Assigned Mode";
                        break;
        }
	
	//Decodifica del ventesimoterzo campo -- Matrice 22 - Assigned Mode
	$d_fields[22]=" ";
	
        $distanza=distance($d_fields[7],$d_fields[6]);
        $d_fields[23]=$distanza["dis"];
        $d_fields[24]=$distanza["brg"];

        statistics($d_fields[23],$d_fields[41],$d_fields[2],$d_fields[7],$d_fields[6],$d_fields[0]);
		maxima(8,$d_fields[23],$d_fields[24],$d_fields[2],$timestamp);
		
	return ($d_fields);

}
//--------------------------------------------------- END OF MessageType 19--------------------------------------------------------------------------------

//--------------------------------------------------- Messagetype  21 --------------------------------------------------------------------------------
function aid_to_navigation21($bit_payload){
	//Messagetype 21 "ais-to-navigation report". They are transmitted by lighthouses and buoies
	global $chkflag;
	global $conn;
        $i=0;
        $wks_payload=$bit_payload;
        while (isset ($wks_payload{$i})) {
                ++$i;
        }
        --$i;
        if ( $i > '312' ) {
		echo "Something is wrong in ais_to_navigation_21 $i\n";
        }
	$lunghezza=$i;
        //echo "Indice della conta $i\n";
        $fields[0]=substr($bit_payload,1,6);            //Message Type
        $fields[1]=substr($bit_payload,7,2);            //Message repeat count
        $fields[2]=substr($bit_payload,9,30);           //MMSI
        $fields[3]=substr($bit_payload,39,5);           //Aid Type
        $fields[4]=substr($bit_payload,44,120);         //Name
        $fields[5]=substr($bit_payload,164,1);          //Position Accuracy
        $fields[6]=substr($bit_payload,165,28);         //Longitude
        $fields[7]=substr($bit_payload,193,27);         //Latitude
        $fields[8]=substr($bit_payload,220,9);          //Dimension to bow
        $fields[9]=substr($bit_payload,229,9);          //Dimension to stern
        $fields[10]=substr($bit_payload,238,6);         //Dimension to port
        $fields[11]=substr($bit_payload,244,6);         //Dimension to starboard
        $fields[12]=substr($bit_payload,250,4);         //Epfd type
        $fields[13]=substr($bit_payload,254,6);         //Utc Seconds
        $fields[14]=substr($bit_payload,260,1);         //Off Position Indicator
        $fields[15]=substr($bit_payload,261,8);         //Regional reserved
        $fields[16]=substr($bit_payload,269,1);         //Raim
        $fields[17]=substr($bit_payload,270,1);         //Virtual AID
        $fields[18]=substr($bit_payload,271,1);         //Assigned Mode
        $fields[19]=substr($bit_payload,272,1);         //Spare
        $fields[20]=substr($bit_payload,273,88);        //name extension
        
	//Debug: stampa tutta la matrice
        //for ($j=0; $j<=15; $j++) {
        //        echo "Indice $j valore $fields[$j]\n";
        //}
	
	 //Decodifica del primo campo -- Matrice 0 -- Message Type
	if ($chkflag){
                $d_fields[0]=field_decode_01($fields[0])+60;}
        else {
                $d_fields[0]=field_decode_01($fields[0]);
        }

        //Decodifica del secondo campo -- Matrice 1 -- Repeat Indicator
        $d_fields[1]=field_decode_02($fields[1]);

        //Decodifica del terzo campo -- Matrice 2 --  MMSI
        $d_fields[2]=field_decode_03($fields[2]);

	//Decodifica del quarto campo -- Matrice 3 -- Aid Type
	$d_fields[3]=aid_type($fields[3]);
	
	//Decodifica del quinto campo -- Matrice 4 -- Name
	$d_fields[4]=str_replace("@","",bit_to_char($fields[4]));
	insertname($d_fields[2],"",$d_fields[4]);	

	//Decodifica del sesto campo -- Matrice 5 -- Position Accuracy
	$d_fields[5]=posac_decode($fields[5]);

	//Decodifica del settimo campo -- Matrice 6 -- Longitude
	$d_fields[6]=field_decode_longitude($fields[6]);

	//Decodifica dell'ottavo campo -- Matrice 7 -- Latitude
	$d_fields[7]=field_decode_latitude($fields[7]);

	//Decodifica del nono campo -- Matrice 8 -- Dimension to bow
	$d_fields[8]=bindec($fields[8]);

	//Decodifica del decimo campo -- Matrice 9 -- Dimension to stern
	$d_fields[9]=bindec($fields[9]);

	//Decodifica dell' undicesimo campo -- Matrice 10 -- Dimensio to port
	$d_fields[10]=bindec($fields[10]);

	//Decodifica del dodicesimo campo -- Matrice 11 -- Dimension to starboard
	$d_fields[11]=bindec($fields[11]);

	//Decodifica del tredicesimo campo -- Matrice 12 -- Epfd type
	$d_fields[12]=field_decode_epfd($fields[12]);

	//Decodifica del quattordicesimo campo -- Matrice 13 -- Utc Seconds
	$d_fields[13]=bindec($fields[13]);

	//Decodifica del quindicesimo campo -- Matrice 14 -- Off Position Indicator
	if ($d_fields[13] <= 59 ){
		switch (bindec($fields[14])) {
			case 0:
				$d_fields[14]="On Position";
				break;
			case 1:
				$d_fields[14]="Off Position";
                break;
			default:
				$d_fields[14]="NoData";
                break;
		}
	}else { $d_fields[14]="Unset";}

	//Decodifica del sedicesimo campo -- Matrice 15 -- Regonal Reserved
	$d_fields[15]=bindec($fields[15]);

	//Decodifica del diciassettesimo campo -- Matrice 16 -- RAIM Flag
	$d_fields[16]=field_decode_raim($fields[16]);

	//Decodifica del diciottesimo campo -- Matrice 17 -- Virtual aid
	switch (bindec($fields[17])) {
                        case 0:
                                $d_fields[17]="Real Aid";
                                break;
                        case 1:
                                $d_fields[17]="Virtual Aid";
                                break;
                        default:
                                $d_fields[17]="Error Decoding";
                                break;
                }

	//Decodifica del diciannovesimo campo -- Matrice 18 -- Assigned Mode Flag
	$d_fields[18]=$fields[18];

	//Decodifica del ventesimo campo -- Matrice 19 -- Spare
	$d_fields[19]=" ";

	//Decodifica del ventunesimo campo -- Matrice 20 -- Name Extension
	$bitlenght=$lunghezza-272;
	$d_fields[20]=str_replace("@","",bit_to_char($fields[20]));

        $distanza=distance($d_fields[7],$d_fields[6]);
        $d_fields[21]=$distanza["dis"];
        $d_fields[22]=$distanza["brg"];

        statistics($d_fields[21],$d_fields[22],$d_fields[2],$d_fields[7],$d_fields[6],$d_fields[0]);
	
	
	//Now let's insert data in a special "type 4 and 11" table in order to make some analysis easier.
	$result=mysqli_query($conn,"select timestamp from ais_type_21_data where mmsi=$d_fields[2] order by timestamp desc limit 2");
	$oldtimestamp=mysqli_fetch_row($result);
	$timestamp=time();
	$deltatime=($timestamp-$oldtimestamp[0]);

	//This should take care of the "first element ever" case
	if (($deltatime == $timestamp) || ($deltatime > 300)) {
	 	echo "Type 21 New Data Inserted\n";
		echo "DEBUG: $timestamp,$d_fields[0],$d_fields[1],$d_fields[2],$fields[3],$d_fields[3],$d_fields[4],$d_fields[5],$d_fields[6],$d_fields[7],$d_fields[8],$d_fields[9],$d_fields[10],$d_fields[11],$d_fields[12],$d_fields[13],$d_fields[14],$d_fields[15],$d_fields[16],$d_fields[17],$d_fields[18],$d_fields[21],$d_fields[22] "			;									
		echo "\n";
		//mysqli_query($conn,"INSERT INTO ais_type_21_data (`id`,`timestamp`,`msg_id`,`repeat`,`mmsi`,`type_aton`,`explicit_type`,`name`,`pos_acc`,`Longitude`,`Latitude`,`dimension_bow`,`dimension_stern`,`dimension_port`,`dimension_starboard`,`epfd_type`,`time`,`off_position`,`Aton_status`,`raim`,`virtual`,`assigned`,`distanza`,`bearing`)VALUES (' ','$timestamp','$d_fields[0]','$d_fields[1]','$d_fields[2]','$fields[3]','$d_fields[3]','$d_fields[4]','$d_fields[5]','$d_fields[6]",'$d_fields[7]','$d_fields[8]','$d_fields[9]','$d_fields[10]','$d_fields[11]','$d_fields[12]','$d_fields[13]','$d_fields[14]','$d_fields[15]','$d_fields[16]','$d_fields[17]','$d_fields[18]','$d_fields[21]','$d_fields[22]')");
		mysqli_query($conn,"INSERT INTO ais_type_21_data (`id`,`timestamp`,`msg_id`,`repeat`,`mmsi`,`type_aton`,`explicit_type`,`name`,`pos_acc`,`Longitude`,`Latitude`,`dimension_bow`,`dimension_stern`,`dimension_port`,`dimension_starboard`,`epfd_type`,`time`,`off_position`,`Aton_status`,`raim`,`virtual`,`assigned`,`distanza`,`bearing`)VALUES (' ','$timestamp','$d_fields[0]','$fields[1]','$d_fields[2]','$fields[3]','$d_fields[3]','$d_fields[4]','$d_fields[5]','$d_fields[6]','$d_fields[7]','$d_fields[8]','$d_fields[9]','$d_fields[10]','$d_fields[11]','$d_fields[12]','$d_fields[13]','$d_fields[14]','$d_fields[15]','$d_fields[16]','$d_fields[17]','$d_fields[18]','$d_fields[21]','$d_fields[22]')");
		
	}

        return ($d_fields);

}



//--------------------------------------------------- END OF MessageType 21--------------------------------------------------------------------------------

//--------------------------------------------------- MessageType 24--------------------------------------------------------------------------------
function static_data_report_24($bit_payload) { //par1
	global $chkflag;
	global $conn;
	//This messagetype is little tricky to be decoded. Lots of code functions are taken from messagetype 5, but 
	//we have do decode immediately the first 4 sections of the payload in order to understand what kind of message we have to
	//deal with. 

        //echo "Payload incoming $bit_payload\n";
        //echo "stripping\n";
        //echo "\n\n";
	$msgtype="";
        
	//echo "Indice della conta $i\n";
        $fields[0]=substr($bit_payload,1,6);    //Message Type
        $fields[1]=substr($bit_payload,7,2);    //Message repeat count
        $fields[2]=substr($bit_payload,9,30);   //MMSI
        $fields[3]=substr($bit_payload,39,2);   //PART: case of this field to be 0, then we have a type A message. Otherwise ((1) is a type B
	//This messagetype relies on field 4 decoding to define how fields have to be divided and decoded.

	//Decodifica del primo campo -- Matrice 0 -- Message Type
	if ($chkflag){
                $d_fields[0]=field_decode_01($fields[0])+60;}
        else {
                $d_fields[0]=field_decode_01($fields[0]);
        }

        //Decodifica del secondo campo -- Matrice 1 -- Repeat Indicator
        $d_fields[1]=field_decode_02($fields[1]);

        //Decodifica del terzo campo -- Matrice 2 --  MMSI
        $d_fields[2]=field_decode_03($fields[2]);

	 //Decodifica del quarto campo -- Matrice 3 -- Part type
        $d_fields[3]=field_decode_02($fields[3]);
	
	switch ($d_fields[3]) { 
		case 0:	//messaggio 24 type A
			$msgtype="A";

	 		//Decodifica del quinto campo -- Matrice 4 -- Parte A -- Vessel Name
			$fields[4]=substr($bit_payload,41,120);	// Vessel Name 20 x 6 bit char
		        $d_fields[4]=str_replace("@","",bit_to_char($fields[4]));
	 		
			//Decodifica del sesto campo -- Matrice 5 -- Parte A -- Spare Bit
        		$fields[5]=substr($bit_payload,161,7);  // Not Used
			$d_fields[5]="";

			break;
		
		case 1: //messaggio 24 type B common part
			$msgtype="B";
			
	 		//Decodifica del quinto campo -- Matrice 4 -- Parte B -- ShipType
			$fields[4]=substr($bit_payload,41,8);	//ship Type
			$d_fields[4]=shiptype($fields[4]);
        	

	 		//Decodifica del sesto campo -- Matrice 5 -- Parte B -- Vendor ID
                        $fields[5]=substr($bit_payload,49,42);  // Vendor ID
			$tmp6th_a=substr($fields[5],1,20);
			$tmp6th_b=substr($fields[5],21,4);
			$tmp6th_c=substr($fields[5],25,18);
			$d_fields[5]="Man_Id ".bindec($tmp6th_a)." Unit Model ".bindec($tmp6th_b)." SN ".bindec($tmp6th_c);

			//Decodifica del settimo campo -- Matrice 6 -- Parte B -- Call Sign
                        $fields[6]=substr($bit_payload,91,42);  // Callsign as in type 5
			//echo "DEBUG: campo 5 call sign: $fields[6]\n";
        		$d_fields[6]=str_replace("@","",bit_to_char($fields[6]));
			
			//now we have to have a little rest to see what kind of information is provided from bits going 
			//from index 132 to 162. They can convey "ship dimension" or MMSI. In case we have to handle a
			// son-of-ship boat, MMSI will start with "98".
			
			if (substr($d_fields[2],1,2) == 98) {//par4
				
				//Decodifica del ottavo campo -- Matrice 7 -- Parte B -- MMSI Mother Ship
				$fields[7]=substr($bit_payload,133,30);	// MMSI of mother Ship
				$d_fields[7]=field_decode_01($fields[7]);

				//Decodifica del nono campo -- Matrice 8 -- Parte B -- Spare Bit
				$fields[8]=substr($bit_payload,163,6);	// Spare not used
			} else {
				
				//Decodifica del ottavo campo -- Matrice 7 -- Parte B -- Dimension to bow
				$fields[7]=substr($bit_payload,133,9); 	// Dimension to bow
                        	$d_fields[7]=bindec($fields[7]);
	
				//Decodifica del nono campo -- Matrice 8 -- Parte B -- Dimension to stern
				$fields[8]=substr($bit_payload,142,9); 	// Dimension to stern
                        	$d_fields[8]=bindec($fields[8]);

				//Decodifica del ottavo campo -- Matrice 8 -- Parte B -- Dimension to port
                        	$fields[9]=substr($bit_payload,151,6); // Dimension to port
                        	$d_fields[9]=bindec($fields[9]);

				//Decodifica del ottavo campo -- Matrice 8 -- Parte B -- Dimension to starboard
                        	$fields[10]=substr($bit_payload,157,6); // Dimension to starboard
                        	$d_fields[10]=bindec($fields[10]);

				//Decodifica del ottavo campo -- Matrice 8 -- Parte B -- Spare Bit
				$fields[11]=substr($bit_payload,163,6); // Spare not used
                        	$d_fields[11]="";
			}//fine dell'else
		
                        //}
		default:
	}//fine del case tipo A o tipo B

	switch ($msgtype){
		case "A":
			insertname($d_fields[2],"",$d_fields[4]);
			break;
		case "B":
			insertname($d_fields[2],$d_fields[6],"");
			break;
	}
	return ($d_fields);

}//fine della funzione	




//--------------------------------------------------- END MessageType 24--------------------------------------------------------------------------------
//--------------------------------------------------- MessageType 27--------------------------------------------------------------------------------
function longrange($bit_payload) {
//Messagetype 27 stands for "Long Range AIS". It's a subset of Class A ship messages.
	global $conn;
	global $chkflag;
        $i=0;
        $wks_payload=$bit_payload;
        //Controllo che siano effetivamente 168
        while (isset ($wks_payload{$i})) {
                ++$i;
        }
        --$i;
        if ( $i > '96' ) {
                echo "qualche cosa non va long range 27 $i\n";
                $lunghezza=$i;
        }
        //echo "Indice della conta $i\n";
        $fields[0]=substr($bit_payload,1,6);            //Message Type
        $fields[1]=substr($bit_payload,7,2);            //Message repeat count
        $fields[2]=substr($bit_payload,9,30);           //MMSI
        $fields[3]=substr($bit_payload,39,1);           //Position Accuracy
        $fields[4]=substr($bit_payload,40,1);         	//RAIM Flag
        $fields[5]=substr($bit_payload,41,1);           //Navigation Status
        $fields[6]=substr($bit_payload,45,18);          //Longitude
        $fields[7]=substr($bit_payload,63,17);          //Latitude
        $fields[8]=substr($bit_payload,80,6);         	//SOG
        $fields[9]=substr($bit_payload,86,9);          	//COG
        $fields[10]=substr($bit_payload,95,1);         	//GNSS Position
        $fields[11]=substr($bit_payload,96,1);         	//Spare

	 //Decodifica del primo campo -- Matrice 0 -- Message Type
	if ($chkflag){
                $d_fields[0]=field_decode_01($fields[0])+60;}
        else {
                $d_fields[0]=field_decode_01($fields[0]);
        }

        //Decodifica del secondo campo -- Matrice 1 -- Repeat Indicator
        $d_fields[1]=field_decode_02($fields[1]);

        //Decodifica del terzo campo -- Matrice 2 --  MMSI
        $d_fields[2]=field_decode_03($fields[2]);

	//Decodifica del quarto campo  -- Matrice 3 -- Position Accuracy
	$d_fields[3]=posac_decode($fields[3]);

	//Decodifica del quinto campo -- Matrice 4 -- RAIM Flag
	$d_fields[4]=field_decode_raim($fields[4]);

	//Decodifica del sesto campo -- Matrice 5 -- Navigation status
	$d_fields[5]=decnavstatus($fields[5]);
	
	//Decodifica del settimo campo -- Matrice 6 -- Longitude
	$d_fields[6]=field_decode_longitude($fields[6]);

	//Decodifica dell ottavo campo -- Matrice 7 -- Latitude
	$d_fields[7]=field_decode_latitude($fields[7]);

	//Dedodifica del nono campo -- Matrice 8 -- SOG
	$d_fields[8]=speed_over_ground($fields[8]);

	//Decodifica del decimo campo -- Matrice 9 -- COG
	$d_fields[9]=cog($fields[9]);

	//Decodifica del decimoprimo campo -- Matrice 10 -- GNSS
	$temp10=bindec($fields[10]);
	switch ($temp10){
		case 0:
			$d_fields[10]="Current GNSS Position";
			break;
		case 1:
			$d_fields[10]="Not GNSS Position";
			break;
	}
	
	//Decodifica del decimosecondo campo -- Matrice 11 -- Spare
	$d_fields[11]=" ";

        $distanza=distance($d_fields[7],$d_fields[6]);
        $d_fields[12]=$distanza["dis"];
        $d_fields[13]=$distanza["brg"];

        statistics($d_fields[12],$d_fields[13],$d_fields[2],$d_fields[7],$d_fields[6],$d_fields[0]);
	


	return $d_fields;
}



//--------------------------------------------------- END OF MessageType 27--------------------------------------------------------------------------------
function write_matrix_to_file($matrice) {
	$fp1=fopen("$outfile","w+");
	foreach($matrice as $key => $value){
	fwrite($fp1,$value."\t");
        }
	fclose($fp1);
    }

function print_matrix_to_screen($matrice,$mask) {
	//echo "DEBUG: print matrix $mask \n";
	switch ($mask) {

	case 1:
	case 2:
	case 3:
		$etichette=array("Message Type     ","Repeat Indicator  ","MMSI              ","Nav. Status       ","ROT               ","SOG               ","Pos.Accuracy      ","Longitude         ","Latitude          ","COG               ","HDG               ","TimeStamp         ","Maneuver Indicator","Spare             ","RAIM              ","Radio             ","Distanza          ","Heading           ");
		break;
	case 4:
		$etichette=array("Message Type   ","Repeat Indicator","MMSI            ","Year           ","Month           ","Day             ","Hour            ","Minute          ","Second          ","Fix Quality     ","Longitude       ","Latitude        ","Type of EPFD    ","Transm. Control ","Spare           ","RAIM Flag       ","SOTDMA          ","Distanza        ","Heading         ");
		break;
	case 5:
                $etichette=array("Message Type    ","Repeat Indicator","MMSI            ","AIS Versione    ","IMO Ship ID     ","CallSign        ","ShipName        ");
                break;
	case 18:
                $etichette=array("Message type    ","Repeat Indicator ","MMSI             ","Regional res.    ","SOG              ","Position Accuracy","Longitude        ","Latitude         ","COG             ","True Heading     ","TimeStamp         ","Regional res.    ","CS Unit          ","Display Flag     ","DSC Flag         ","Band Flag        ","Msg 22 Flag     ","Assigned         ","RAIM             ","Radio            ","Distanza         ","Heading          ");
                break;
	case 19:
                $etichette=array("Message Type     ","Repeat Indicator ","MMSI             ","Regional res.    ","SOG              ","Position Accuracy","Longitude        ","Latitude         ","COG             ","True Heading     ","Timestamp        ","Regional res.    ","Name             ","Type Of Ship     ","Dim. to Bow","Dim. to Stern    ","Dim. to Port     ","Dim. to Starboard","Position Fix Type","RAIM             ","DTE              ","Assigned Mode    ","Spare            ","Distanza         ","Heading          ");
                break;
	case 21:
                $etichette=array("Message Type     ","Repeat Indicator","MMSI             ","Aid Type         ","Name             ","Accuracy         ","Longitude        ","Latitude         ","Dim.to Bow       ","Dim.to Stern     ","Dim.to Port      ","Dim.to Starboard","EPFD             ","UTC Seconds      ","Off-Position     ","Reg.Reserv      ","RAIM             ","Virtual Aid      ","Assigned Mode    ","Spare            ","Extension        ","Distanza         ","Heading         ");
                break;
	case 241:$etichette=array("Message Type         ","Repeat Indicator","MMSI            ","Part Number     ","Vessel Name     ","Spare           ","");  //messaggio 24 tipo A
		//echo "debug print matrix 241 \n";
		break;
	case 242:$etichette=array("Message Type    ","Repeat Indicator","MMSI            ","Part Number     ","Ship Type       ","Vendor ID       ","CallSign        ","MotherShip MMSI","Spare           "); //messaggio 24 tipo B variante 1 con mothership MMSI
		//echo "debug print matrix 242 \n";
		break;
	case 243:$etichette=array("Message Type      ","Repeat Indicator  ","MMSI              ","Part Number       ","Ship Type         ","Vendor ID         ","CallSign          ","Dimension To Bow","Dimension to Stern","Dimension to Port","Dimension to starboard","Spare             "); //messaggio 24 tipo B variante 2 con dimensioni
		//echo "debug print matrix 243 \n";
		break;
	case 27:
                $etichette=array("Message Type     ","Repeat Indicator ","MMSI             ","Position Accuracy","RAIM             ","Navigation Status","Longitude        ","Latitude         ","SOG            ","COG              ","GNSS Position    ","Spare            ","Distanza         ","Heading          ");
	}
	$k=0;
	foreach($matrice as $key => $value){
	//echo "indice1 $key indice2 $k $etichette[$k] \t\t\t \t valore: $value \n";
	echo "$etichette[$k] \t\t\t \t valore: $value \n";
	++$k;
	}
	}

function parser_message_type($variabile) {
	//It identifies messagatype and call the corresponding decoding function, passing the whole payload.
	global $sessionstats,$chkflag;
	if ($variabile[5]=="next") {
		//echo "Multifragment Message - Waiting for next\n";
		return;
	}
	
	$convpayload=payload_to_bit($variabile[5]);

	$tipomsg=messagetype($convpayload);
        //echo "DEBUG: Tipo messaggio: $tipomsg\n";
	
	$tipomessaggio=messagetype(payload_to_bit($variabile[5]));
	switch (messagetype(payload_to_bit($variabile[5]))) {
        case 1:
		echo "Messaggio tipo 1 \n\n";
                print_matrix_to_screen(cnb_field_13($convpayload),$tipomsg);
		if ($chkflag){++$sessionstats["msg_01F"];}
		else {++$sessionstats["msg_01"];};
                break;
        case 2:
		echo "Messaggio tipo 2 \n\n";
                print_matrix_to_screen(cnb_field_13($convpayload),$tipomsg);
		 if ($chkflag) {++$sessionstats["msg_02F"];}
                else {++$sessionstats["msg_02"];};
                break;
	case 3:
		echo "Messaggio tipo 3 \n\n";
                print_matrix_to_screen(cnb_field_13($convpayload),$tipomsg);
		if ($chkflag) {++$sessionstats["msg_03F"];}
                else {++$sessionstats["msg_03"];};
                break;
	case 4:
		echo "Messaggio tipo 4 \n\n";
                print_matrix_to_screen(base_station_report_4($convpayload),$tipomsg);
		if ($chkflag) {++$sessionstats["msg_04F"];}
                else {++$sessionstats["msg_04"];};
                break;
	case 5:
		echo "Messaggio tipo 5 \n\n";
                print_matrix_to_screen(static_voyage_data($convpayload),$tipomsg);
		if ($chkflag) {++$sessionstats["msg_05F"];}
                else {++$sessionstats["msg_05"];};
                break;
	case 6:
		echo "Messaggio tipo 6 \n\n";
		if ($chkflag) {++$sessionstats["msg_06F"];}
                else {++$sessionstats["msg_06"];};
		break;
	case 7:
		echo "Messaggio tipo 7 \n\n";
		if ($chkflag) {++$sessionstats["msg_07F"];}
                else {++$sessionstats["msg_07"];};
                break;
	case 8:
		echo "Messaggio tipo 8 \n\n";
		if ($chkflag) {++$sessionstats["msg_08F"];}
                else {++$sessionstats["msg_08"];};
                break;
	case 9:
		echo "Messaggio tipo 9 \n\n";
		if ($chkflag) {++$sessionstats["msg_09F"];}
                else {++$sessionstats["msg_09"];};
                break;
	case 10:
		echo "Messaggio tipo 10 \n\n";
		if ($chkflag) {++$sessionstats["msg_10F"];}
                else {++$sessionstats["msg_10"];};
                break;
	case 11:
		echo "Messaggio tipo 11 \n\n";
                print_matrix_to_screen(base_station_report_4($convpayload),$tipomsg);
		if ($chkflag) {++$sessionstats["msg_11F"];}
                else {++$sessionstats["msg_11"];};
                break;
	case 12:
		echo "Messaggio tipo 12 \n\n";
		if ($chkflag) {++$sessionstats["msg_12F"];}
                else {++$sessionstats["msg_12"];};
                break;
	case 13:
		echo "Messaggio tipo 13 \n\n";
		if ($chkflag) {++$sessionstats["msg_13F"];}
                else {++$sessionstats["msg_13"];};
                break;
	case 14:
		echo "Messaggio tipo 14 \n\n";
		if ($chkflag) {++$sessionstats["msg_14F"];}
                else {++$sessionstats["msg_14"];};
                break;
	case 15:
		echo "Messaggio tipo 15 \n\n";
		if ($chkflag) {++$sessionstats["msg_15F"];}
                else {++$sessionstats["msg_15"];};
                break;
	case 16:
		echo "Messaggio tipo 16 \n\n";
		if ($chkflag) {++$sessionstats["msg_16F"];}
                else {++$sessionstats["msg_16"];};
                break;
	case 17:
		echo "Messaggio tipo 17 \n\n";
		if ($chkflag) {++$sessionstats["msg_17F"];}
                else {++$sessionstats["msg_17"];};
                break;
	case 18:
		echo "Messaggio tipo 18 \n\n";
		print_matrix_to_screen(standard_classB_18($convpayload),$tipomsg);
		if ($chkflag) {++$sessionstats["msg_18F"];}
                else {++$sessionstats["msg_18"];};
                break;
	case 19:
		echo "Messaggio tipo 19 \n\n";
		print_matrix_to_screen(extended_classB_19($convpayload),$tipomsg);
		if ($chkflag) {++$sessionstats["msg_19F"];}
                else {++$sessionstats["msg_19"];};
                break;
	case 20:
		echo "Messaggio tipo 20 \n\n";
		if ($chkflag) {++$sessionstats["msg_20F"];}
                else {++$sessionstats["msg_20"];};
                break;
	case 21:
		echo "Messaggio tipo 21 \n\n";
		print_matrix_to_screen(aid_to_navigation21($convpayload),$tipomsg);
		if ($chkflag) {++$sessionstats["msg_21F"];}
                else {++$sessionstats["msg_21"];};
                break;
	case 22:
		echo "Messaggio tipo 22 \n\n";
		if ($chkflag) {++$sessionstats["msg_22F"];}
                else {++$sessionstats["msg_22"];};
                break;
	case 23:
		echo "Messaggio tipo 23 \n\n";
		if ($chkflag) {++$sessionstats["msg_23F"];}
                else {++$sessionstats["msg_23"];};
                break;
	case 24:
		echo "Messaggio tipo 24 \n\n";
		$parser24_type=substr($convpayload,39,2);
		//echo "DEBUG parse $parser24_type \n";
		if ($parser24_type == "00") {
			$tipomsg = 241;
			} else {
				$parser24_mmsi=substr(field_decode_03(substr($convpayload,9,30)),1,2);
					if ($parser24_mmsi == 98) {
						$tipomsg=242;
						} else {
						$tipomsg=243;
						}
		}
		//echo "DEBUG: parse message tipomessaggio: $tipomsg\n";
		print_matrix_to_screen(static_data_report_24($convpayload),$tipomsg);
		if ($chkflag) {++$sessionstats["msg_24F"];}
                else {++$sessionstats["msg_24"];};
		break;
	case 25:
		echo "Messaggio tipo25\n";
		if ($chkflag) {++$sessionstats["msg_25F"];}
                else {++$sessionstats["msg_25"];};
                break;
	case 26:
		echo "Messaggio tipo26\n";
		if ($chkflag) {++$sessionstats["msg_26F"];}
                else {++$sessionstats["msg_26"];};
                break;
	case 27:
		echo "Messaggio tipo27\n";
		print_matrix_to_screen(longrange($convpayload),$tipomsg);
		if ($chkflag) {++$sessionstats["msg_27F"];}
                else {++$sessionstats["msg_27"];};
                break;
	default:
		echo "Messaggio altro tipo tipoaltro\n";
		++$sessionstats["other"];
		break;

	}
}
function save_stats(){
	global $sessionstats, $statsfile;
	$filestat=fopen("$statsfile","w");
	fwrite ($filestat, var_export($sessionstats,1));
	fclose($filestat);

}

//--------------------------------MAIN--------------------------------------------------------
echo "Connessione a $hostName";
$conn=mysqli_connect($hostName, $userName, $password, $databaseName);
if (!$conn) {
	    echo "Error: Unable to connect to MySQL." . PHP_EOL;
	    echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
	    echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
	    exit;
}

echo "Connessione avvenuta con successo" . PHP_EOL;
echo "Host information: " . mysqli_get_host_info($conn) . PHP_EOL;

checkfile();




switch ($DEBUG) {
	
	case 0: //Socket Beahviour
		echo "DEBUG: normal mode\n";

		//File opening to define handle
		$handle=fopen($rawfile,'a') or
        	die ("can't open file");
		
		if (!$socket) {
        		die("$errstr ($errno)");
		}

		do {
		 
   		$pkt = stream_socket_recvfrom($socket,160);
		if ($pkt != $pkt_buf) {
			$timestamp=time();
			//Copia dei dati su un file per successivi riferimenti e tests
			$pkt_w=$timestamp."  ".$pkt;
   			fwrite_stream ($handle,$pkt_w);
		
			echo "\n";
			echo "Version 2j   ";
			echo date('d-M-Y H:i:s', $timestamp)."\n";
			//echo "DEBUG: valore di pkt $pkt\n";
		
			$pezzi=pre_pkt($pkt);

			parser_message_type($pezzi);
			++$sessionstats["pkts"];
			save_stats();

			$pkt_buf=$pkt;
		}
		} while ($pkt !== false); 
		break;	

	case 1: //Debug working
		echo "DEBUG: debug mode\n";
		
		foreach ($test_data_1 as $key => $value) {

		$pkt=$value;
		echo"\n\n";
		echo "DEBUG: ----------------------------------------------------------------------------------------- \n";
		echo "DEBUG: Inizio routine \n";
		echo "DEBUG: valore di pkt $pkt\n";
		$pezzi=separa_campi($pkt);
		//echo "DEBUG: dopo il separa campi\n";
                parser_message_type($pezzi);
		//echo "DEBUG: dopo message type\n";
                }
                break;



}



?>
