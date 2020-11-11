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

//Why php?
//Actually this is php code "echoing" html.
//In this way I can take advantage from php strength, and control authentication rights.
//
//No CSS.
//Some items were removed, due to security reasons. Empty spaces are marked with <insert_value_here>,
//basically if you perform a text search, you'll be able to find all of them.

//Page features:
//Display a map with the real position of the ships. A sidebar displays all the MMSI that were received 
//and allows user to click on it to have further information.
//Diplay maximum distance for each type o ship in the last hour, day, week or year.
//Access some additional pages like calendar, search page etc.


//Used to calculate server performances in managing the query on db.

//Get current time
    $mtime = microtime();
//Split seconds and microseconds
    $mtime = explode(" ",$mtime);
//Create one value for start time
    $mtime = $mtime[1] + $mtime[0];
//Write start time into a variable
    $tstart = $mtime;

//Authentication
session_start();
if (!isset($_SESSION["loginid"])) {
                $_SESSION["message"]= "You are not authorized to access the URL {$_SERVER["REQUEST_URI"]}";
                header ("Location: ../logout.php");
                exit;
        }
echo "<!DOCTYPE html PUBLIC\"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n ";
echo "<meta name=\"viewport\" content=\"initial-scale=1.0, user-scalable=no\" />\n ";
echo "<meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\"/> \n";
echo "<meta http-equiv=\"refresh\" content=\"90\">\n";

echo "<style> \n";
echo "#sideContainer { \n";
echo "	list-style-type: none; \n";
echo "	padding: 0; \n";
echo " 	margin: 0 10px 0 0; \n";
echo " 	float: left; \n";
echo " 	border: 1px solid #676767; \n";
echo " 	background-color: #eee; \n";
echo "  overflow: scroll; \n";
echo "	height: 600px; \n";
echo "	width: 12%; \n";
echo "}\n";
echo "#sideContainer li { \n";
echo " 	font-size: 0.9em; \n";
echo " 	border-bottom: 1px solid #aaa; \n";
echo " 	padding: 5px; \n";
echo " 	} \n";
echo "#map { \n";
echo " 	float: right; \n";
echo " 	width: 85%; \n";
echo "  height: 600px; \n";
echo " 	} \n";
echo "</style> \n";

echo "<html>\n";
echo "<head>\n";
echo "<meta http-equiv=\"Content-Type\" content=\"test/html; charset=iso-8859-1\">\n";
echo "<title>AIS Receiver data display </title>\n";

//Google API keys
echo "<script async defer src=\"https://maps.googleapis.com/maps/api/js?key=<insert_value_here>&callback=initMap\" type=\"text/javascript\"></script>";
echo "<style type=\"text/css\">\n";
echo "     html { height: 100% }\n";
echo "     body { height: 100%; margin: 0; padding: 0 }\n";
echo "   </style>\n";
echo "\n\n";

echo "<script>\n";
echo "var n = 1;\n";
echo "function generateListElement( marker,name ){\n";
echo "   var ul = document.getElementById('sideContainer');\n";
echo "   var li = document.createElement('li');\n";
echo "   var aSel = document.createElement('a');\n";
echo "   aSel.href = 'javascript:void(0);';\n";
echo "   aSel.innerHTML = 'Open  ' + name;\n";
echo "   aSel.onclick = function(){ google.maps.event.trigger(marker, 'click')};\n";
echo "   li.appendChild(aSel);\n";
echo "   ul.appendChild(li);\n";
echo " } \n";
echo "</script>\n";

//Define markers shape, color, size
echo "<script type=\"text/javascript\">\n";
echo "var icon = new google.maps.MarkerImage(\"http://maps.google.com/mapfiles/ms/micons/blue.png\",\n";
echo "new google.maps.Size(32, 32), new google.maps.Point(0, 0),\n";
echo "new google.maps.Point(16, 32));\n";
echo "var iconfix = new google.maps.MarkerImage(\"http://maps.google.com/mapfiles/ms/micons/green.png\",\n";
echo "new google.maps.Size(32, 32), new google.maps.Point(0, 0),\n";
echo "new google.maps.Point(16, 32));\n";
echo "var icon21 = new google.maps.MarkerImage(\"http://maps.google.com/mapfiles/ms/micons/yellow.png\",\n";
echo "new google.maps.Size(32, 32), new google.maps.Point(0, 0),\n";
echo "new google.maps.Point(16, 32));\n";
echo "var iconCsB = new google.maps.MarkerImage(\"http://maps.google.com/mapfiles/ms/micons/red.png\",\n";
echo "new google.maps.Size(32, 32), new google.maps.Point(0, 0),\n";
echo "new google.maps.Point(16, 32));\n";
echo "var center = null;\n";
echo "var map = null;\n";
echo "var currentPopup;\n";

//Create a marker for fixed stations
echo "function addMarkerFix(lat, lng, info, name) {\n";
echo "        var pt = new google.maps.LatLng(lat, lng);\n";
echo "                var marker = new google.maps.Marker({\n";
echo "                position: pt,\n";
echo "                icon: iconfix,\n";
echo "                map: map,\n";
echo "                title: info\n";
echo "                });\n";
echo "        var popup = new google.maps.InfoWindow({\n";
echo "                content: info,\n";
echo "                maxWidth: 300\n";
echo "        });\n";
echo "        google.maps.event.addListener(marker, \"click\", function() {\n";
echo "                if (currentPopup != null) {\n";
echo "                currentPopup.close();\n";
echo "                currentPopup = null;\n";
echo "        }\n";
echo "        popup.open(map, marker);\n";
echo "                currentPopup = popup;\n";
echo "        });\n";
echo "        google.maps.event.addListener(popup, \"closeclick\", function() {\n";
echo "                map.panTo(center);\n";
echo "                currentPopup = null;\n";
echo "                });\n";
echo "        generateListElement (marker,name);\n";
echo "        }\n";


//Create a marker for type B ships
echo "function addMarkerCsB(lat, lng, info, name) {\n";
echo "        var pt = new google.maps.LatLng(lat, lng);\n";
echo "                var marker = new google.maps.Marker({\n";
echo "                position: pt,\n";
echo "                icon: iconCsB,\n";
echo "                map: map,\n";
echo "                title: info\n";
echo "                });\n";
echo "        var popup = new google.maps.InfoWindow({\n";
echo "                content: info,\n";
echo "                maxWidth: 300\n";
echo "        });\n";
echo "        google.maps.event.addListener(marker, \"click\", function() {\n";
echo "                if (currentPopup != null) {\n";
echo "                currentPopup.close();\n";
echo "                currentPopup = null;\n";
echo "        }\n";
echo "        popup.open(map, marker);\n";
echo "                currentPopup = popup;\n";
echo "        });\n";
echo "        google.maps.event.addListener(popup, \"closeclick\", function() {\n";
echo "                map.panTo(center);\n";
echo "                currentPopup = null;\n";
echo "                });\n";
echo "        generateListElement (marker,name);\n";
echo "        }\n";

//Create marker for type A ships
echo "function addMarker21(lat, lng, info, name) {\n";
echo "        var pt = new google.maps.LatLng(lat, lng);\n";
echo "                var marker = new google.maps.Marker({\n";
echo "                position: pt,\n";
echo "                icon: icon21,\n";
echo "                map: map,\n";
echo "                title: info\n";
echo "                });\n";
echo "        var popup = new google.maps.InfoWindow({\n";
echo "                content: info,\n";
echo "                maxWidth: 300\n";
echo "        });\n";
echo "        google.maps.event.addListener(marker, \"click\", function() {\n";
echo "                if (currentPopup != null) {\n";
echo "                currentPopup.close();\n";
echo "                currentPopup = null;\n";
echo "        }\n";
echo "        popup.open(map, marker);\n";
echo "                currentPopup = popup;\n";
echo "        });\n";
echo "        google.maps.event.addListener(popup, \"closeclick\", function() {\n";
echo "                map.panTo(center);\n";
echo "                currentPopup = null;\n";
echo "                });\n";
echo "        generateListElement (marker,name);\n";
echo "        }\n";

//Once markers have been created, we need to add them to the map, by assigning them 
//longitude and latitude. Markers are "cliccable" to show further information and
//to open a link toward vessel finder.

echo "function addMarker(lat, lng, info, name) {\n";
echo "        var pt = new google.maps.LatLng(lat, lng);\n";
echo "                var marker = new google.maps.Marker({\n";
echo "                position: pt,\n";
echo "                icon: icon,\n";
echo "                map: map,\n";
echo "                title: info\n";
echo "                });\n";
echo "        var popup = new google.maps.InfoWindow({\n";
echo "                content: info,\n";
echo "                maxWidth: 300\n";
echo "        });\n";
echo "        google.maps.event.addListener(marker, \"click\", function() {\n";
echo "                if (currentPopup != null) {\n";
echo "                currentPopup.close();\n";
echo "                currentPopup = null;\n";
echo "        }\n";
echo "        popup.open(map, marker);\n";
echo "                currentPopup = popup;\n";
echo "        });\n";
echo "        google.maps.event.addListener(popup, \"closeclick\", function() {\n";
echo "                map.panTo(center);\n";
echo "                currentPopup = null;\n";
echo "                });\n";
echo "	generateListElement (marker,name);\n";
echo "        }\n";

//The most important part: create the map! On the map a circle is drawn to show the "Radio Horizon" coverage.
//All data in the circle are received thanks to "normal" tropospheric propagation. If signals are received
//that come from outside the circle, there is tropospheric anomaly.

echo "function initMap() {\n";
echo "        var myOptions = {\n";
echo "        center: new google.maps.LatLng(<insert_value_here>,<insert_value_here>),\n";
echo "        zoom: 8,\n";
echo "	scrollwheel: false,\n";
echo "        mapTypeId: google.maps.MapTypeId.ROADMAP,\n";
echo "	mapTypeControl: true,\n";
echo "	mapTypeControlOptions: {\n";
echo "		style: google.maps.MapTypeControlStyle.DROPDOWN_MENU\n";
echo "	},\n";
echo "	zoomControl: true,\n";
echo "	zoomControlOptions: {\n";
echo "		style: google.maps.ZoomControlStyle.SMALL\n";
echo "	}\n";
echo "        };\n";
echo "        map = new google.maps.Map(document.getElementById(\"map\"),myOptions);\n";
echo "        var radiohorizonoptions = {\n";
echo "        strokeColor: \"#FF0000\",\n";
echo "        strokeOpacity: 0.8,\n";
echo "        strokeWeight: 2,\n";
echo "        fillColor: \"#FF0000\",\n";
echo "        fillOpacity: 0.35,\n";
echo "        map: map,\n";
echo "        center: new google.maps.LatLng(<insert_value_here>,<insert_value_here>),\n";
echo "        radius: <insert_value_here>\n";
echo "        }\n";
echo "        RadioHorizon = new google.maps.Circle(radiohorizonoptions);\n";
//echo " } \n";

$timestamp=time();
$timestamp=$timestamp-60;
$timestamp1h=$timestamp-3600;

//Add a reference file to get information on database, basically username, password and hostname
require "<insert_value_here>";

$conn=mysqli_connect($hostName, $userName, $passdata);
if (!$conn)
        die("Connection Failure");
if (!(mysqli_select_db($conn,$databaseName)))
        die("Cannot Change Database");

//Mobile stations query tool

$query = mysqli_query($conn,"select ais_stat_data_temp.mmsi, ais_stat_data_temp.Distance,ais_stat_data_temp.Longitude,ais_stat_data_temp.Latitude, ais_stat_data_name.callsign, ais_stat_data_name.vesselname from ais_stat_data_temp left join ais_stat_data_name on ais_stat_data_temp.mmsi = ais_stat_data_name.mmsi where type =1 and ais_stat_data_temp.timestamp > $timestamp1h   and ais_stat_data_temp.timestamp < $timestamp   group by mmsi;");
//Generate link for vessel finder database
while ($row = mysqli_fetch_array($query)){
$name=$row['mmsi'];
$lat=number_format($row['Latitude'],3);
$lon=number_format($row['Longitude'],3);
$dist=$row['Distance'];
$cs=$row['callsign'];
$cs=str_replace("'", "", $cs);
$vn=addslashes($row['vesselname']);
$vn=str_replace("'", "", $vn);
$test_link="https://www.marinetraffic.com/en/ais/index/search/all?keyword=";
echo ("addMarker($lat, $lon,'MMSI: <b>$name</b><br>Distanza (m): $dist <br> Callsign: $cs <br> Name: $vn <br> <a href=\"$test_link.$name\"> Search </a>',$name);\n");
}
echo ";\n\n";

//Aid to navigation query tool 

$query = mysqli_query($conn,"select ais_stat_data_temp.mmsi, ais_stat_data_temp.Distance,ais_stat_data_temp.Longitude,ais_stat_data_temp.Latitude, ais_stat_data_name.callsign, ais_stat_data_name.vesselname from ais_stat_data_temp left join ais_stat_data_name on ais_stat_data_temp.mmsi = ais_stat_data_name.mmsi where type =21 and ais_stat_data_temp.timestamp > $timestamp1h   and ais_stat_data_temp.timestamp < $timestamp   group by mmsi;");

while ($row = mysqli_fetch_array($query)){
$name=$row['mmsi'];
$lat=number_format($row['Latitude'],3);
$lon=number_format($row['Longitude'],3);
$dist=$row['Distance'];
$cs=$row['callsign'];
$cs=str_replace("'", "", $cs);
$vn=addslashes($row['vesselname']);
$vn=str_replace("'", "", $vn);
$test_link="https://www.marinetraffic.com/en/ais/index/search/all?keyword=";
echo ("addMarker21($lat, $lon,'MMSI: <b>$name</b><br>Distanza (m): $dist <br> Callsign: $cs <br> Name: $vn <br> <a href=\"$test_link.$name\"> Search </a>',$name);\n");
}
echo ";\n\n";

//Class B query tool

$query = mysqli_query($conn,"select ais_stat_data_temp.mmsi, ais_stat_data_temp.Distance,ais_stat_data_temp.Longitude,ais_stat_data_temp.Latitude, ais_stat_data_name.callsign, ais_stat_data_name.vesselname from ais_stat_data_temp left join ais_stat_data_name on ais_stat_data_temp.mmsi = ais_stat_data_name.mmsi where type =18 and ais_stat_data_temp.timestamp > $timestamp1h   and ais_stat_data_temp.timestamp < $timestamp   group by mmsi;");

while ($row = mysqli_fetch_array($query)){
$name=$row['mmsi'];
$lat=number_format($row['Latitude'],3);
$lon=number_format($row['Longitude'],3);
$dist=$row['Distance'];
$cs=$row['callsign'];
$cs=str_replace("'", "", $cs);
$vn=addslashes($row['vesselname']);
$vn=str_replace("'", "", $vn);
$test_link="https://www.marinetraffic.com/en/ais/index/search/all?keyword=";
echo ("addMarkerCsB($lat, $lon,'MMSI: <b>$name</b><br>Distanza (m): $dist <br> Callsign: $cs <br> Name: $vn <br> <a href=\"$test_link.$name\"> Search </a>',$name);\n");
}
echo ";\n\n";



//Fixed stations query tools

$query = mysqli_query($conn,"SELECT mmsi,Latitude,Longitude,Distance FROM ais_stat_data_temp where type=4 and timestamp > $timestamp1h and timestamp < $timestamp group by mmsi ");

while ($row = mysqli_fetch_array($query)){
$name=$row['mmsi'];
$lat=number_format($row['Latitude'],3);
$lon=number_format($row['Longitude'],3);
$dist=$row['Distance'];
echo ("addMarkerFix($lat, $lon,'MMSI: <b>$name</b><br>Distanza (m): $dist',$name);\n");
}
echo ";\n}\n";


//}
//Once we have all data is type to draw the page frame.

echo "</script>\n";
echo "</head>\n";

echo "<body onload=\"initMap()\">\n";
echo "  <div id=\"map\"></div>\n";
echo "<body STYLE=\"font-family: Arial, Helvetica, Sans Serif\">\n";
echo "<ul id=\"sideContainer\" style></ul>\n";
echo "<div id=\"mapContainer\"></div>\n";

echo "<center> <h1> Ais Ship Tracking </h1> </center>\n";

echo "<form action=../leave.php> ";
echo "<input type=\"submit\" value=\"LOGOUT\">";
echo "</form>";
echo "<br>";

echo "This section shows the results of our decoding and analisys engine. The first half of the page shows a map in which the position of emitting ships is displayed.";
echo "Different colors stands for different sources:";
echo "<ul>";
echo "<li> BLUE represent type A ship </li>";
echo "<li> RED represent type B ship </li>";
echo "<li> GREEN represent FIX stations </li>";
echo "<li> YELLOW represent AtoN stations </li>";
echo "</ul>";
echo "<br>";
echo "<ul>";
echo "<li> <a href=\"#SoD\"> Summary Of Data </a> : displays the results of a brief statistical analisys of received data.</li> ";
echo "<li> <a href=\"#QuE\">Query section</a>: provides some tools to perform queries on data base. You can display the course of a ship by just inserting its callsign or MMSi and choosing the time base. </li> ";
echo "<li> <a href=\"#FiS\">Fixed Stations</a>: let the user access a special set of web pages, by which only fixed stations can be analyzed.</li> ";
echo "<li> <a href=\"#ChK\">Checksum Error</a>: a set of scripts and pages which will let you analyze errored packets distribution.</li> ";
echo "<li> <a href=\"#CaL\">Calendar Tool</a>: a graphical tool in order to focus on the number of fixed stations received day by day.</li> ";
echo "<li> <a href=\"#MrT\">MRTG Section</a>: a graphical tool in order to monitor some imoportant parameters.</li> ";
echo "</ul>";
echo "";

echo "<h3><a id=\"SoD\"> Summary Of Data </a> </h3>\n";
//echo "Version 1.9 \n";
date_default_timezone_set('Europe/Rome');

//
// Retriving SOF - Start of analysis
//

$result_ts1=mysqli_query($conn,"select id,timestamp from ais_stat_data where id=(select min(id) from ais_stat_data where Type=1)");
$risultato_ts1=mysqli_fetch_row($result_ts1);
$start_timestamp=$risultato_ts1[1];
//echo "DEBUG: $start_timestamp\n";
//echo "<br>";
//echo "Start of analysis (table filling):".date('Y-m-d H-i-s',$start_timestamp)."\n";
//echo "<p>";

$result_ts2=mysqli_query($conn,"select max(timestamp) from ais_stat_data");
$risultato_ts2=mysqli_fetch_row($result_ts2);
$stop_timestamp=$risultato_ts2[0];
//echo "DEBUG: $stop_timestamp\n";
//echo "Current Time:".date('Y-m-d H-i-s',$stop_timestamp)."\n";
//echo "<br>";

echo "<p> In this section results of a brief statistical analisys are displayed. Data collection started on ".date('Y-m-d H-i-s',$start_timestamp)." while current time is ".date('Y-m-d H-i-s',$stop_timestamp)."\n";
echo "<br>";

//Insert the file location with database information: username, password, hostname
require "<insert_value_here>";

//Let's prepare some timestamps
$timestamp=time();
$timestamp=$timestamp-60;
$timestamp1h=$timestamp-3600;           // one hour ago
$timestamp1d=$timestamp-86400;          // one day ago 
$timestamp1w=$timestamp-604800;         // one week ago


//echo "<br>\n";
//echo "<br>\n";
//echo "Timestamp (for Debug)- $timestamp - 1h: $timestamp1h - 1d: $timestamp1d - 1w: $timestamp1w ";
//echo "<br> <br>";
//echo "Conversion (for debug) ".date('d-m-Y H-i-s',$timestamp)." 1h ".date('d-m-Y H-i-s',$timestamp1h)." 1d ".date('d-m-Y H-i-s',$timestamp1d)." 1w ".date('d-m-Y H-i-s',$timestamp1w);
//echo "<br>";

$conn=mysqli_connect($hostName, $userName, $passdata);
if (!$conn)
	die("Connection Failure");
if (!(mysqli_select_db($conn,$databaseName)))
  	die("Cannot Change Database");

//
// Table row number
//
$result_sofar=mysqli_query($conn,"select value from ais_max_record where type=\"R1T\" ");
$righe_sofar=mysqli_fetch_row($result_sofar);
echo "<br>";
echo "Row number in MySql table: $righe_sofar[0] \n";
echo "<br>";

$result_sofar_temp=mysqli_query($conn,"select value from ais_max_record where type=\"R2T\" ");
$righe_sofar_temp=mysqli_fetch_row($result_sofar_temp);
echo "<br>";
echo "Row number in MySql temp table: $righe_sofar_temp[0] \n";
echo "<br>";



//
// Unique MMSI in the table
//
$result_mmsi=mysqli_query($conn,"select value from ais_max_record where type=\"M1S\" ");
$righe_mmsi_mt=mysqli_fetch_row($result_mmsi);
//
//
// Unique MMSI in the table
//
$result_mmsi=mysqli_query($conn,"select value from ais_max_record where type=\"M2S\" ");
$righe_mmsi_ta=mysqli_fetch_row($result_mmsi);

//
// Unique MMSI in the table - type B
//
$result_mmsi=mysqli_query($conn,"select value from ais_max_record where type=\"M3S\" ");
$righe_mmsi_tb=mysqli_fetch_row($result_mmsi);

//
// Unique MMSI in the table - AtoN
//
$result_mmsi=mysqli_query($conn,"select value from ais_max_record where type=\"M4S\" ");
$righe_mmsi_tc=mysqli_fetch_row($result_mmsi);

//
// Unique MMSI in the table - Fixed stations
//
$result_mmsi_fix=mysqli_query($conn,"select value from ais_max_record where type=\"M5S\" ");
$righe_mmsi_fix=mysqli_fetch_row($result_mmsi_fix);
echo "<br>";

echo "<table border=\"1\">";
echo "<tr>";
echo "<td> Unique MMSI</td>"; //row 1 cell 1
echo "<td> In main table</td>"; //row 1 cell 2
echo "<td> Type A</td>"; //row 1 cell 3
echo "<td> Type B</td>"; //row 1 cell 4
echo "<td> Aton</td>"; //row 1 cell 5
echo "<td> Fix Stations</td>";//row 1 cell 6
echo "</tr>";
echo "<td> </td>"; //row 2 cell 1
echo "<td> $righe_mmsi_mt[0]</td>"; //row 2 cell 2
echo "<td> $righe_mmsi_ta[0]</td>"; //row 2cell 3
echo "<td> $righe_mmsi_tb[0]</td>"; //row 2 cell 4
echo "<td> $righe_mmsi_tc[0]</td>"; //row 2 cell 5
echo "<td> $righe_mmsi_fix[0]</td>";//row 2 cell 6
echo "</table>";




//
// SHIPS - break down by hour - day - week - absolute
//

//Absolute
$result_s_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"S1A\" ");
$risultato=mysqli_fetch_row($result_s_dist_abs);
$distanza_s_abs=$risultato[0];

$result_s_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"S2A\" ");
$risultato=mysqli_fetch_row($result_s_dist_abs);
$bearing_s_abs=$risultato[0];

$result_s_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"S3A\" ");
$risultato=mysqli_fetch_row($result_s_dist_abs);
$mmsi_s_abs=$risultato[0];

$result_s_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"S4A\" ");
$risultato=mysqli_fetch_row($result_s_dist_abs);
$time_s_abs=$risultato[0];

//1 hour

$result_s_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"S1H\" ");
$risultato=mysqli_fetch_row($result_s_dist_hou);
$distanza_s_hou=$risultato[0];

$result_s_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"S2H\" ");
$risultato=mysqli_fetch_row($result_s_dist_hou);
$bearing_s_hou=$risultato[0];

$result_s_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"S3H\" ");
$risultato=mysqli_fetch_row($result_s_dist_hou);
$mmsi_s_hou=$risultato[0];

$result_s_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"S4H\" ");
$risultato=mysqli_fetch_row($result_s_dist_hou);
$time_s_hou=$risultato[0];

//1 day
$result_s_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"S1D\" ");
$risultato=mysqli_fetch_row($result_s_dist_day);
$distanza_s_day=$risultato[0];

$result_s_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"S2D\" ");
$risultato=mysqli_fetch_row($result_s_dist_day);
$bearing_s_day=$risultato[0];

$result_s_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"S3D\" ");
$risultato=mysqli_fetch_row($result_s_dist_day);
$mmsi_s_day=$risultato[0];

$result_s_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"S4D\" ");
$risultato=mysqli_fetch_row($result_s_dist_day);
$time_s_day=$risultato[0];

//1 week 
$result_s_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"S1W\" ");
$risultato=mysqli_fetch_row($result_s_dist_wek);
$distanza_s_wek=$risultato[0];

$result_s_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"S2W\" ");
$risultato=mysqli_fetch_row($result_s_dist_wek);
$bearing_s_wek=$risultato[0];

$result_s_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"S3W\" ");
$risultato=mysqli_fetch_row($result_s_dist_wek);
$mmsi_s_wek=$risultato[0];

$result_s_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"S4W\" ");
$risultato=mysqli_fetch_row($result_s_dist_wek);
$time_s_wek=$risultato[0];

//
// ClassB - break down by hour - day - week - absolute
//

//Absolute
$result_B_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"B1A\" ");
$risultato=mysqli_fetch_row($result_B_dist_abs);
$distanza_B_abs=$risultato[0];

$result_B_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"B2A\" ");
$risultato=mysqli_fetch_row($result_B_dist_abs);
$bearing_B_abs=$risultato[0];

$result_B_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"B3A\" ");
$risultato=mysqli_fetch_row($result_B_dist_abs);
$mmsi_B_abs=$risultato[0];

$result_B_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"B4A\" ");
$risultato=mysqli_fetch_row($result_B_dist_abs);
$time_B_abs=$risultato[0];

//1 hour
$result_B_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"B1H\" ");
$risultato=mysqli_fetch_row($result_B_dist_hou);
$distanza_B_hou=$risultato[0];

$result_B_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"B2H\" ");
$risultato=mysqli_fetch_row($result_B_dist_hou);
$bearing_B_hou=$risultato[0];

$result_B_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"B3H\" ");
$risultato=mysqli_fetch_row($result_B_dist_hou);
$mmsi_B_hou=$risultato[0];

$result_B_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"B4H\" ");
$risultato=mysqli_fetch_row($result_B_dist_hou);
$time_B_hou=$risultato[0];

//1 day
$result_B_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"B1D\" ");
$risultato=mysqli_fetch_row($result_B_dist_day);
$distanza_B_day=$risultato[0];

$result_B_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"B2D\" ");
$risultato=mysqli_fetch_row($result_B_dist_day);
$bearing_B_day=$risultato[0];

$result_B_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"B3D\" ");
$risultato=mysqli_fetch_row($result_B_dist_day);
$mmsi_B_day=$risultato[0];

$result_B_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"B4D\" ");
$risultato=mysqli_fetch_row($result_B_dist_day);
$time_B_day=$risultato[0];

//1 week 
$result_B_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"B1W\" ");
$risultato=mysqli_fetch_row($result_B_dist_wek);
$distanza_B_wek=$risultato[0];

$result_B_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"B2W\" ");
$risultato=mysqli_fetch_row($result_B_dist_wek);
$bearing_B_wek=$risultato[0];

$result_B_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"B3W\" ");
$risultato=mysqli_fetch_row($result_B_dist_wek);
$mmsi_B_wek=$risultato[0];

$result_B_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"B4W\" ");
$risultato=mysqli_fetch_row($result_B_dist_wek);
$time_B_wek=$risultato[0];

//
// FIX - break down by hour - day - week - absolute
//

//Absolute
$result_f_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"F1A\" ");
$risultato=mysqli_fetch_row($result_f_dist_abs);
$distanza_f_abs=$risultato[0];

$result_f_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"F2A\" ");
$risultato=mysqli_fetch_row($result_f_dist_abs);
$bearing_f_abs=$risultato[0];

$result_f_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"F3A\" ");
$risultato=mysqli_fetch_row($result_f_dist_abs);
$mmsi_f_abs=$risultato[0];

$result_f_dist_abs=mysqli_query($conn,"select value from ais_max_record where type=\"F4A\" ");
$risultato=mysqli_fetch_row($result_f_dist_abs);
$time_f_abs=$risultato[0];

//1 hour
$result_f_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"F1H\" ");
$risultato=mysqli_fetch_row($result_f_dist_hou);
$distanza_f_hou=$risultato[0];

$result_f_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"F2H\" ");
$risultato=mysqli_fetch_row($result_f_dist_hou);
$bearing_f_hou=$risultato[0];

$result_f_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"F3H\" ");
$risultato=mysqli_fetch_row($result_f_dist_hou);
$mmsi_f_hou=$risultato[0];

$result_f_dist_hou=mysqli_query($conn,"select value from ais_max_record where type=\"F4H\" ");
$risultato=mysqli_fetch_row($result_f_dist_hou);
$time_f_hou=$risultato[0];

//1 day
$result_f_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"F1D\" ");
$risultato=mysqli_fetch_row($result_f_dist_day);
$distanza_f_day=$risultato[0];

$result_f_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"F2D\" ");
$risultato=mysqli_fetch_row($result_f_dist_day);
$bearing_f_day=$risultato[0];

$result_f_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"F3D\" ");
$risultato=mysqli_fetch_row($result_f_dist_day);
$mmsi_f_day=$risultato[0];

$result_f_dist_day=mysqli_query($conn,"select value from ais_max_record where type=\"F4D\" ");
$risultato=mysqli_fetch_row($result_f_dist_day);
$time_f_day=$risultato[0];

//1 week 
$result_f_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"F1W\" ");
$risultato=mysqli_fetch_row($result_f_dist_wek);
$distanza_f_wek=$risultato[0];

$result_f_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"F2W\" ");
$risultato=mysqli_fetch_row($result_f_dist_wek);
$bearing_f_wek=$risultato[0];

$result_f_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"F3W\" ");
$risultato=mysqli_fetch_row($result_f_dist_wek);
$mmsi_f_wek=$risultato[0];

$result_f_dist_wek=mysqli_query($conn,"select value from ais_max_record where type=\"F4W\" ");
$risultato=mysqli_fetch_row($result_f_dist_wek);
$time_f_wek=$risultato[0];

//
//Generating table to show previuos results

echo "<br>";
echo "<br>";
echo "<table border=\"1\">";
echo "<tr>";
echo "<td> </td>"; //row 1 cell 1
echo "<td> </td>"; //row 1 cell 2
echo "<td> Max in Last Hour </td>"; //row 1 cell 3
echo "<td> Max in Last Day</td>"; //row 1 cell 4
echo "<td> Max in Last Week</td>"; //row 1 cell 5
echo "<td> Absolute Max</td>";//row 1 cell 6
echo "</tr>";
echo "<tr>";
echo "<td rowspan=\"4\"> SHIPS</td>"; //row 2 cell 1
echo "<td> Distance (m)</td>"; //row 2 cell 2
echo "<td> $distanza_s_hou</td>"; //row 2 cell 3
echo "<td> $distanza_s_day</td>"; //row 2 cell 4
echo "<td> $distanza_s_wek</td>"; //row 2 cell 5
echo "<td> $distanza_s_abs</td>"; //row 2 cell 6
echo "</tr>";
echo "<tr>";
echo "<td> Bearing (Degrees)</td>"; //row 3 cell 2
echo "<td> $bearing_s_hou</td>"; //row 3 cell 3
echo "<td> $bearing_s_day</td>"; //row 3 cell 4
echo "<td> $bearing_s_wek</td>"; //row 3 cell 5
echo "<td> $bearing_s_hou</td>"; //row 3 cell 6
echo "</tr>";
echo "<tr>";
echo "<td> MMSI </td>"; //row 3 cell 2
echo "<td> $mmsi_s_hou</td>"; //row 4 cell 3
echo "<td> $mmsi_s_day</td>"; //row 4 cell 4
echo "<td> $mmsi_s_wek</td>"; //row 4 cell 5
echo "<td> $mmsi_s_abs</td>"; //row 4 cell 6
echo "</tr>";
echo "<tr>";
echo "<td> TimeStamp </td>"; //row 3 cell 2
echo "<td> ".date('d-m-Y H-i-s',$time_s_hou)."</td>"; //row 4 cell 3
echo "<td> ".date('d-m-Y H-i-s',$time_s_day)."</td>"; //row 4 cell 3
echo "<td> ".date('d-m-Y H-i-s',$time_s_wek)."</td>"; //row 4 cell 3
echo "<td> ".date('d-m-Y H-i-s',$time_s_abs)."</td>"; //row 4 cell 3
echo "</tr>";
echo "<tr>";

echo "<td rowspan=\"4\"> Class B</td>"; //row 2 cell 1
echo "<td> Distance (m)</td>"; //row 2 cell 2
echo "<td> $distanza_B_hou</td>"; //row 2 cell 3
echo "<td> $distanza_B_day</td>"; //row 2 cell 4
echo "<td> $distanza_B_wek</td>"; //row 2 cell 5
echo "<td> $distanza_B_abs</td>"; //row 2 cell 6
echo "</tr>";
echo "<tr>";
echo "<td> Bearing (Degrees)</td>"; //row 3 cell 2
echo "<td> $bearing_B_hou</td>"; //row 3 cell 3
echo "<td> $bearing_B_day</td>"; //row 3 cell 4
echo "<td> $bearing_B_wek</td>"; //row 3 cell 5
echo "<td> $bearing_B_hou</td>"; //row 3 cell 6
echo "</tr>";
echo "<tr>";
echo "<td> MMSI </td>"; //row 3 cell 2
echo "<td> $mmsi_B_hou</td>"; //row 4 cell 3
echo "<td> $mmsi_B_day</td>"; //row 4 cell 4
echo "<td> $mmsi_B_wek</td>"; //row 4 cell 5
echo "<td> $mmsi_B_abs</td>"; //row 4 cell 6
echo "</tr>";
echo "<tr>";
echo "<td> TimeStamp </td>"; //row 3 cell 2
echo "<td> ".date('d-m-Y H-i-s',$time_s_hou)."</td>"; //row 4 cell 3
echo "<td> ".date('d-m-Y H-i-s',$time_s_day)."</td>"; //row 4 cell 3
echo "<td> ".date('d-m-Y H-i-s',$time_s_wek)."</td>"; //row 4 cell 3
echo "<td> ".date('d-m-Y H-i-s',$time_s_abs)."</td>"; //row 4 cell 3
echo "</tr>";
echo "<tr>";

echo "<td rowspan=\"4\"> FIX</td>"; //row 5 cell 1
echo "<td> Distance (m)</td>"; //row 5 cell 2
echo "<td> $distanza_f_hou</td>"; //row 5 cell 3
echo "<td> $distanza_f_day</td>"; //row 5 cell 4
echo "<td> $distanza_f_wek</td>"; //row 5 cell 5
echo "<td> $distanza_f_abs</td>"; //row 5 cell 6
echo "</tr>";
echo "<tr>";
echo "<td> Bearing (Degrees)</td>"; //row 6 cell 2
echo "<td> $bearing_f_hou </td>"; //row 6 cell 3
echo "<td> $bearing_f_day </td>"; //row 6 cell 4
echo "<td> $bearing_f_wek </td>"; //row 6 cell 5
echo "<td> $bearing_f_abs </td>"; //row 6 cell 6
echo "</tr>";
echo "<tr>";
echo "<td> MMSI </td>";
echo "<td> $mmsi_f_hou </td>"; //row 7 cell 3
echo "<td> $mmsi_f_day </td>"; //row 7 cell 4
echo "<td> $mmsi_f_wek </td>"; //row 7 cell 5
echo "<td> $mmsi_f_abs </td>"; //row 7 cell 6
echo "</tr>";
echo "<tr>";
echo "<td> TimeStamp </td>"; //row 3 cell 2
echo "<td> ".date('d-m-Y H-i-s',$time_f_hou)."</td>"; //row 4 cell 3
echo "<td> ".date('d-m-Y H-i-s',$time_f_day)."</td>"; //row 4 cell 3
echo "<td> ".date('d-m-Y H-i-s',$time_f_wek)."</td>"; //row 4 cell 3
echo "<td> ".date('d-m-Y H-i-s',$time_f_abs)."</td>"; //row 4 cell 3
echo "</tr>";
echo "</table>";

//


//Query tool

mysqli_close($conn);
echo "<h3><a id=\"QuE\">Query MMSI, Callsign o Vesselname to display its course. Please select a time-base for analysis. </a> </h3>";
echo "<p> Wildcard is \"%\", so <b>%uc% </b>will search for a vessel name whose name contains \"uc\" </p>";

echo "<FORM action=\"file.php\" method=\"post\" target=\"vesselname \">";
echo "	<P>";
echo "	MMSI <INPUT type=\"text\" name=\"mmsi\">OR Vessel Name<INPUT type=\"text\" name=\"vesselname\"> OR CallSign <INPUT type=\"text\" name=\"callsign\"> <br><br>";
echo "	<input type=\"Radio\" name=\"timesearch\" value=\"week\" /> Last Week";
echo "	<input type=\"Radio\" name=\"timesearch\" value=\"day\" /> Last Day ";
echo "	<input type=\"Radio\" name=\"timesearch\" value=\"hour\" /> Last Hour ";
echo "	<input type=\"Radio\" name=\"timesearch\" value=\"lkp\" /> Last Known Position <br> <br>";
echo "	<input type=\"submit\" value=\"submit\">";
echo "	</P>";
echo "</FORM>";

echo "<h3><a id=\"FiX\"> Fixed Stations Monitoring Tool </a></h3>";
echo "<a href=\"../fixed.php\"> Click HERE! </a>\n";

echo "<h3><a id=\"ChK\"> Checksum Error Monitoring (Experimental)</a> </h3>";
echo "<a href=\"error.php\"> Click HERE! </a>\n";

echo "<h3><a id=\"CaL\"> Calendar Analysis Tool </a></h3> \n"; 
echo "<form name=\"yearform\" action=\"calendar.php\" method=\"POST\"> \n";
echo "<select name=\"year\"> \n";
echo "<option value=\"2012\">2012</option> ";
echo "<option value=\"2013\">2013</option> ";
echo "<option value=\"2014\">2014</option> \n";
echo "<option value=\"2015\">2015</option> \n";
echo "<option value=\"2016\">2016</option> \n";
echo "<option value=\"2017\">2017</option> \n";
echo "<option value=\"2018\">2018</option> \n";
echo "</select> \n";
echo "<input type=\"submit\" value=\"submit\">";
echo "</form> \n";



echo "<BR>";
echo "</body>";
echo "</html>";

//Get current time as we did at start
    $mtime = microtime();
    $mtime = explode(" ",$mtime);
    $mtime = $mtime[1] + $mtime[0];
//Store end time in a variable
    $tend = $mtime;
//Calculate the difference
    $totaltime = ($tend - $tstart);
//Output result
    printf ("Page was generated in %f seconds !", $totaltime); 
	printf ("\n\n\n");
echo "<BR>";
echo "<BR>";
echo "<BR>";

?>
