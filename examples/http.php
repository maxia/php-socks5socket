<?php

/**
 * this example demonstrates a http request using 
 * the socks5socket.
 */

require('../Socks5Socket.class.php');

$s = new \Socks5Socket\Client();

$s->configureProxy(array(
	'hostname' => '127.0.0.1',
	'port' => 9150
	/*
	'username' => 'username',
	'password' => 'password',
	'use_dnstunnel' => true
	 */
));

$s->connect('bot.whatismyipaddress.com', 80);

$request = "GET / HTTP/1.1\r\n".
           "Host: bot.whatismyipaddress.com\r\n\r\n";

$s->send($request);

$response = $s->readAll();

echo '<h1>The response was:</h1><pre>'.$response.'</pre>';