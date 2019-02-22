<?php

require('../Client.php');

$s = new Maxia\Socks5\Client([
    'hostname' => '127.0.0.1',
    'port' => 9150
    /*
    'username' => 'username',
    'password' => 'password',
    'use_dnstunnel' => true
     */
]);

$s->connect('pop.gmail.com', 995, true);

$s->send("QUIT\r\n");

echo '<pre>'.$s->readAll().'</pre>';
