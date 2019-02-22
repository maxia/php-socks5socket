php-socks5socket
================

A simple SOCKS5 client implementation in PHP.

## Usage

<pre>
require '../Client.php';

$s = new Maxia\Socks5\Client();

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
           "Host: bot.whatismyipaddress.com".
           "Connection: close\r\n\r\n";

$s->send($request);

$response = $s->readAll();

echo "The response was:\n" . $response;
</pre>

## License
MIT