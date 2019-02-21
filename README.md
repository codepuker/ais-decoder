# ais-decoder
Decoder for Automatic Identification System - Written in php.

Description
Ais-decoder is an award-not-winning script, to decode AIS protocols. It was developed with "vim" under linux to accomplish some research goals. The complete system is made up of three parts:
- the dispatcher: opens a socket, receives packets from the AIS receiver and dispatch them to many IPs;
- the decoder: opens a socket, receives packets from the dispatcher and decodesthe protocol. All information are stored in a database;
- the database: stores all information for further processing, web-site presentation and storage waste.

Why Php
As I stated in my profile, I don't like programming. This is why I don't know many programming/scripting languages. At the time I wrote this decoder I had never user php before. So I choose it to force me learning something new. 

Project Status
The project is frozen. I'm not going (unless forced) to develop the code further. Occasionally I'll correct some bugs or clean the code. Current version is "j".

What's in here
