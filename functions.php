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



function aid_type($bit) {
	$type=bindec($bit);
	switch ($type) {
	case 0:
		$rtval="Not Specified";
		break;
	case 1:	
		$rtval="reference Point";
		break;
	case 2:
		$rtval="Racon (radar trasponder marking an hazard)";
		break;
	case 3:
		$rtval="Fixed Structure Off Shore";
		break;
	case 4:
		$rtval="Spare";
		break;
	case 5:
		$rtval="Light, without sectors";
		break;
	case 6:
		$rtval="Light, with sectors";
		break;
	case 7:
		$rtval="Leading Light Front";
		break;
	case 8:
		$rtval="Leading light Rear";
		break;
	case 9:
		$rtval="Beacon, cardinal N";
		break;
	case 10:
		$rtval="Beacon, cardinal E";
		break;
	case 11:
		$rtval="Beacon, cardinal S";
		break;
	case 12:
		$rtval="Beacon, cardinal W";
		break;
	case 13:
		$rtval="Beacon, port hand";
		break;
	case 14:
		$rtval="Beacon, starboard hand";
		break;
	case 15:
		$rtval="Beacon, preferred Channel port hand";
		break;
	case 16:
		$rtval="Beacon, preferred Channel starboard hand";
		break;
	case 17:
		$rtval="Beacon, Isolated danger";
		break;
	case 18:
		$rtval="Beacon, Safe Water";
		break;
	case 19:
		$rtval="Beacon, Special Mark";
		break;
	case 20:	
		$rtval="Cardinal, Mark N";
		break;
	case 21:	
		$rtval="Cardinal, Mark E";
		break;
	case 22:	
		$rtval="Cardinal, Mark S";
		break;
	case 23:	
		$rtval="Cardinal, Mark W";
		break;
	case 24:
		$rtval="Port Hand Mark";
		break;
	case 25:
		$rtval="Starboard Hand Mark";
		break;
	case 26:
		$rtval="Preferred Channel Port Hand";
		break;
	case 27:
		$rtval="Preferred Channel Port Hand";
		break;
	case 28:
		$rtval="Isolated Danger";
		break;
	case 29:
		$rtval="Safe Water";
		break;
	case 30:
		$rtval="Special Mark";
		break;
	case 31:
		$rtval="Light Vessel - Lanby - Rigs";
		break;
	}
	return $rtval;
}

function field_decode_01($bit) {
        //Funzione di decodifica del primo campo (message type)
        $temp1th= bindec($bit);
        return $temp1th;
}

function field_decode_02($bit) {
        //Funzione di decodifica del secondo campo (repeat indicator)
        $temp2nd=bindec($bit);
        if ($temp2nd == '3') {
                $value="Do Not repeat";
        }
            else {
                $value=$temp2nd;
        }
        return $value;
}

function field_decode_03($bit) {
        //Funzione di decodifica del terzo campo (MMSI)
        $temp3th=bindec($bit);
        return $temp3th;
}

function posac_decode($bit) {
        //Decodifica del settimo campo -- Matrice 6 -- Position Accuracy
        switch ($bit) {
                case 0:
                        $retval="Accuracy > 10m - GNSS Fix";
                        break;
                case 1:
                        $retval="Accuracy < 10m - DGPS Fix";
						break;
        }
        return $retval;
}

function speed_over_Ground($bit){
	$temp6th=bindec($bit);
        //echo "SOG $temp6th\n";
        switch ($temp6th) {
                case ($temp6th < 1023):
                        $rtval=$temp6th/10;
                        break;
                case ($temp6th== 1023):
                        $rtval="No speed information available";
                        break;
                default :
                        $rtval="NaN";
                        break;
	}
	return $rtval;
}

function field_decode_epfd($bit) {
        //Decodifica del tredicesimo campo - Matrice 12 - Position to Fix
        $temp13th=bindec($bit);
        switch ($temp13th) {
                case 0:
                        $rtval="Undefined";
                        break;
                case 1:
                        $rtval="GPS";
                        break;
                case 2:
                        $rtval="GLONASS";
                        break;
                case 3:
                        $rtval="Combined GPS/Glonass";
                        break;
                case 4:
                        $rtval="Loran-C";
                        break;
                case 5:
                        $rtval="Chayka";
                        break;
                case 6:
                        $rtval="Integrated Navigation System";
                        break;
                case 7:
                        $rtval="Surveyed";
                        break;
                case 8:
                        $rtval="Galileo";
                        break;
                case 9:
                case 10:
                case 11:
                case 12:
                case 13:
                case 14:
                        $rtval="Not Used";
                        break;
                case 15:
                        $rtval="Internal GNSS";
                        break;
                }
        return $rtval;
}

function field_decode_longitude ($bit) {
        //Decodifica dell'ottavo campo -- Matrice 7 -- Longitude

        //echo "DEBUG: $fields[7]\n";
        $temp8th= bin28dec($bit);
        //echo "DEBUG: temp8th $temp8th\n";
        $debug8=$temp8th/600000.0;
        //echo "DEBUG debug8 $debug8\n";
        //$d_fields[7]=$temp8th/600000.0;
        $rtval=round(($temp8th/600000.0),4,PHP_ROUND_HALF_UP);
        //echo "DEBUG: valore rtval $rtval\n";
        if (($rtval == 181) || ($rtval >182)) {
                $rtval="NA";
        }
        return $rtval;

}

function field_decode_latitude ($bit) {
        //Decodifica del nono campo -- Matrice 8 -- Latitude
        $temp9th= bin27dec($bit);
        //echo "temp9th $temp9th \n";
        $rtval=round(($temp9th/600000.0),4,PHP_ROUND_HALF_UP);
        if (($rtval == 91) || ($rtval > 92)) {
                $rtval="NA";
        }
        return $rtval;
}

function field_decode_raim ($bit) {
        //Decodifica del decimoquindo campo -- Matrice 14 -- RAIM Flag
        switch ($bit) {
                case 0:
                        $rtval="Not In Use";
                        break;
                case 1:
                        $rtval="In Use";
                        break;
                }
        return $rtval;
}

function field_decode_radio ($bit,$flag) {
        //Decodifica del decimosesto campo -- Matrice 15 -- Radio Status

        switch($flag) {
                case 1:
                        //echo "DEBUG: msgtyp 1 -> SOTDMA\n";
                        $rtval="SOTDMA";
		//	echo "DEBUG field_decode_radio: $rtval\n";
			return $rtval;
                        break;
                case 2:
                        //echo "DEBUG: msgtyp 2 -> SOTDMA\n";
                        $rtval="SOTDMA";
			echo "DEBUG field_decode_radio: $rtval\n";
                        return $rtval;
                        break;
                case 3:
                        //echo "DEBUG: msgtyp 3 -> ITDMA\n";
                        $rtval="ITDMA";
		//	echo "DEBUG field_decode_radio: $rtval\n";
                        return $rtval;
                        break;
                }
}

function cog($bit) {
	//Decodifica del decimo campo -- Matrice 9 -- Course over Ground
        $temp10th=bindec($bit);
        if ($temp10th == 3600 ) {
                $rtval="COG: no data";
        }
 	else {
                $rtval=$temp10th/10;
        };
	return $rtval;

}

function shiptype($bit) {
	//ShipType
        $tmp5th=bindec($bit);
        //echo "DEBUG quinto campo: $tmp5th $fields[4]\n";
        switch ($tmp5th) { //par3
        	case 0:
                	$retval="Not Available";
                	break;
                case 1:
                case 2:
                case 3:
                case 4:
                case 5:
                case 6:
                case 7:
                case 8:
                case 9:
                case 10:
                case 11:
                case 12:
                case 13:
                case 14:
                case 15:
                case 16:
                case 17:
                case 18:
                case 19:
                	$retval="Reserved for future use";
                        break;
                case 20:
                        $retval="Wing in ground";
                        break;
                case 21:
                        $retval="Wing in ground - Hazardous cat. A";
                        break;
                case 22:
                        $retval="Wing in ground - Hazardous cat. B";
                        break;
                case 23:
                        $retval="Wing in ground - Hazardous cat. C";
                        break;
                case 24:
                        $retval="Wing in ground - Hazardous cat. D";
                	break;
                case 25:
                case 26:
                case 27:
                case 28:
                case 29:
                        $retval="Wing in ground - Reserved";
                        break;
                case 30:
                        $retval="Fishing";
                        break;
                case 31:
                        $retval="Towing";
                        break;
                case 32:
                        $retval="Towing: lenght exceeds 200m or breadth exceeds 25m";
                        break;
                case 33:
                        $retval="Dredging or underwater ops";
                        break;
                case 34:
                        $retval="Diving Ops";
                        break;
                case 35:
                        $retval="Military Ops";
                        break;
                case 36:
                        $retval="Sailing";
                        break;
                case 37:
                        $retval="Pleasure Craft";
                        break;
                case 38:
                        $retval="Reserved";
                        break;
                case 39:
                        $retval="Reserved";
                        break;
                case 40:
                        $retval="High Speed Craft, all ships";
                        break;
                case 41:
                        $retval="High Speed Craft - Hazardous cat. A";
                        break;
                case 42:
                        $retval="High Speed Craft - Hazardous cat. B";
                        break;
                case 43:
                        $retval="High Speed Craft - Hazardous cat. C";
                        break;
                case 44:
                        $retval="High Speed Craft - Hazardous cat. D";
                        break;
                case 45:
                case 46:
                case 47:
                case 48:
                case 49:
                        $retval="High Speed Craft - Reserved";
                        break;
                case 50:
                        $retval="Pilot Vessel";
                        break;
                case 51:
                        $retval="Search and rescue Vessel";
                        break;
                case 52:
                        $retval="Tug";
                        break;
                case 53:
                        $retval="Port tender";
                        break;
                case 54:
                        $retval="Anti-Pollution Equipment";
                        break;
                case 55:
                        $retval="Law Enforcement";
                        break;
                case 56:
                        $retval="Spare - Local Vessel";
                        break;
                case 57:
                        $retval="Spare - Local Vessel";
                        break;
                case 58:
                        $retval="Medical Transport";
                        break;
                case 59:
                        $retval="Noncombatant ship according to RR N.18";
                        break;
                case 60:
                        $retval="Passenger, all ships of this type";
                        break;
                case 61:
                        $retval="Passenger, Hazardous cat.A";
                        break;
                case 62:
                        $retval="Passenger, Hazardous cat.B";
                        break;
                case 63:
                        $retval="Passenger, Hazardous cat.C";
                        break;
                case 64:
                        $retval="Passenger, Hazardous cat.D";
                        break;
                case 65:
                case 66:
                case 67:
                case 68:
                case 69:
                        $retval="Passenger, Reserved";
                        break;
                case 70:
                        $retval="Cargo, all ships of this type";
                        break;
                case 71:
                        $retval="Cargo, Hazardous cat. A";
                        break;
                case 72:
                        $retval="Cargo, Hazardous cat. B";
                        break;
                case 73:
			$retval="Cargo, Hazardous cat. C";
                        break;
                case 74:
                        $retval="Cargo, Hazardous cat. D";
                        break;
                case 75:
                case 76:
                case 77:
                case 78:
                case 79:
                        $retval="Cargo, Reserved";
                        break;
                case 80:
                        $retval="Tanker, all ship of this type";
                        break;
                case 81:
                        $retval="Tanker, Hazardous cat. A";
                        break;
                case 82:
                        $retval="Tanker, Hazardous cat. B";
                        break;
                case 83:
                        $retval="Tanker, Hazardous cat. C";
                        break;
                case 84:
                        $retval="Tanker, Hazardous cat. D";
                        break;
                case 85:
                case 86:
                case 87:
                case 88:
                case 89:
                        $retval="Tanker, Reserved";
                        break;
                case 90:
                        $retval="Other type, all ships of this type";
                        break;
                case 91:
                        $retval="Other type, Hazardous cat. A";
                        break;
                case 92:
                        $retval="Other type, Hazardous cat. B";
                        break;
                case 93:
                        $retval="Other type, Hazardous cat. C";
                        break;
                case 94:
                        $retval="Other type, Hazardous cat. D";
                        break;
                case 95:
                case 96:
                case 97:
                case 98:
                case 99:
                $retval="Other Type, Reserved";
                break;
		}
	return $retval;
}

function decnavstatus($bit) {
$navstatus=bindec($bit);
        switch ($navstatus) {
                case 0:
                        $retval="Under Way Using Engine";
                        break;
                case 1:
                        $retval="At Anchor";
                        break;
                case 2:
                        $retval="Not Under Command";
                        break;
                case 3:
                        $retval="Restricted Manoeuverability";
                        break;
                case 4:
                        $retval="Constrained by her draught";
                        break;
                case 5:
                        $retval="Moored";
                        break;
                case 6:
                        $retval="Aground";
                        break;
                case 7:
                        $retval="Engaged in fishing";
                        break;
                case 8:
                        $retval="Under Way Sailing";
                        break;
                case 9:
                        $retval="Reserved for future for HSC";
                        break;
                case 10:
                        $retval="Reserved for future for HSC";
                        break;
                case 11:
                        $retval="Reserved for future use";
                        break;
                case 12:
                        $retval="Reserved for future use";
                        break;
                case 13:
                        $retval="Reserved for future use";
                        break;
                case 14:
                         $retval="Reserved for future use";
                        break;
                case 15:
                         $retval="Reserved for future use";
                        break;
        }
	return $retval;


}
?>
