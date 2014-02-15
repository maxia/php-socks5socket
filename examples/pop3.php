<?php

/**
 * this example shows how to connect to gmail's
 * pop3 server, using SSL and tunnelled through a socks5 server.
 */

require('../Socks5Socket.class.php');

$s = new \Socks5Socket\Client();

$s->configureProxy(array(
	'hostname' => '127.0.0.1',
	'port' => 9150
));

$s->connect('pop.gmail.com', 995, true);

$s->send("QUIT\r\n");

echo '<pre>'.$s->readAll().'</pre>';
