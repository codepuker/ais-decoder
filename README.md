# ais-decoder
Decoder for Automatic Identification System - Written in php.

## Why Php
As I stated in my profile, I don't like programming. This is why I don't know many programming/scripting languages. At the time I wrote this decoder I had never user php before. So I choose it to force me learning something new. 

## Project Status
The project is frozen. I'm not going (unless forced) to develop the code further. Occasionally I'll correct some bugs or clean the code. Current version is "j".

## Description
Ais-decoder is an award-not-winning script, to decode AIS protocols. It was developed with "vim" under linux to accomplish some research goals. The complete system is made up of three parts:
* the dispatcher: opens a socket, receives packets from the AIS receiver and dispatch them to many IPs;
* the decoder: opens a socket, receives packets from the dispatcher and decodesthe protocol. All information are stored in a database;
* the database: stores all information for further processing, web-site presentation and storage waste.
* utils: a collection of bash programs to populate database tables and perform some dirty jobs.

## The Dispatcher
The code is pretty easy. It opens a socket, receives a packet and forward it to different IPs. Duplicated packets are deleted to offload target node. This script allows you to have a production and develop station booth receiving the same AIS messages. More over it allows you to feed MarineTraffic.com without delay and 24x7.

## The Decoder
It takes a message from a socket and decodes it. Well, not that simply. MEssage type is identiified looking at first byte. Then a specific decoding function is called. When a message arrives which carries GPS position, a "distance calculator" is started which estimates the distance between receiver and ship. Code was ported from a Javascript and is based on a WGS84 geoid, to maximize accuracy. Data are stored in a database, using different tables: a temporary one, which is cleaned everyday and the "storage" which is used only to archive data. 

## The database
Database name is "ais" and pretty full of tables:
* ais_fix_day_xxxx     - one table per year. It stores the number of different fixed stations received per day;
* ais_max_record       - updated daily from a bash script, it stores the highest values of many interesting variables;
* ais_stat_data        - the working table. Web site works on this table to build the page;
* ais_stat_data_xxxx   - archived data;
* ais_stat_login       - login counter for web site;
* ais_stat_no_checksum - bad checksum packets;
* ais_type_21_data     - long range packets, require special care;
* ais_type_4_11_data   - temporary data.

