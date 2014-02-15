<?php

/** 
 * A simple socket client that supports SOCKS5 proxies.
 * 
 * @author Maxi Arnicke <maxi.arnicke@gmail.com>
 * @license MIT License (http://opensource.org/licenses/MIT)
 */

namespace Socks5Socket;

class Exception extends \Exception {}
class IOException extends Exception {}
class ConnectionException extends Exception {}
class SSLException extends ConnectionException {}
class Socks5Exception extends ConnectionException {}
class Socks5AuthException extends Socks5Exception {}
class Socks5ConnectionException extends Socks5Exception {}

class Client
{
	/**
	 * Contains the php socket resource, or null if not connected. 
	 * @var resource socket
	 */
	protected $socket;

	/**
	 * Contains the timeout to use for connections.
	 * @var int $timeout
	 */
	protected $timeout = 15;

	/**
	 * Contains the SOCKS5 connection config, if used.
	 * @var array $socks_config
	 */
	protected $socks_config;

	/**
	 * Constuctor
	 */
	public function __construct($config = null)
	{
		if (is_array($config))
			$this->configureProxy($config);
	}
	
	/**
	 * Destructor
	 */
	public function __destruct()
	{
		$this->disconnect();
	}

	/**
	 * Sets the proxy config for future connections.
	 * 
	 * @param array $config Proxy config array
	 * @access public
	 */
	public function configureProxy(array $config)
	{
		if ($config === false)
			$this->socks_config = null;

		$this->socks_config = array_merge(array(
			'hostname' => '',
			'port' => 1080,
			'dns_tunnel' => true,
			'username' => '',
			'password' => ''
		), $config);
	}
	
	/**
	 * Sets the timeout to use for future connections.
	 * 
	 * @param int $seconds Timeout in seconds
	 * @access public
	 */
	public function setTimeout($seconds)
	{
		$this->timeout = (int)$seconds;
	}

	/**
	 * Disconnects from the server.
	 * 
	 * @access public
	 */
	public function disconnect()
	{
		if (is_resource($this->socket))
			fclose($this->socket);
	}
	
	/**
	 * Connects to the given server.
	 * 
	 * @param string  $host      Hostname
	 * @param int     $port      Port
	 * @param boolean $ssl       Optional: Wheter to use SSL encryption or not for this connection
	 * @param int     $ssl_type  Optional: SSL encryption type (see: php.net/manual/en/function.stream-socket-enable-crypto.php)
	 * @access public
	 */
	public function connect($host, $port, $ssl = false, $ssl_type = STREAM_CRYPTO_METHOD_SSLv3_CLIENT)
	{
		$this->disconnect();

		if ($this->socks_config !== null)
		{
			// connect to socks server
			$this->socket = $this->createSocket("tcp://{$this->socks_config['hostname']}:{$this->socks_config['port']}");

			$method = empty($this->socks_config['username']) ? 0x00 : 0x02;
			$this->send($this->buildSocksGreeting($method));

			// check if this auth method is supported
			$response = unpack("Cversion/Cmethod", $this->read(3));

			if ($response['version'] != 0x05)
				throw new Socks5Exception('SOCKS version is not supported.');

			if ($response['method'] != $method)
				throw new Socks5AuthException('SOCKS authentication method not supported.');

			// authenticate, if necessary
			if ($method == 0x02)
			{
				$this->send($this->buildSocksAuth($this->socks_config['username'], $this->socks_config['password']));
				$response = unpack("Cversion/Cstatus", $this->read(3));
				
				if($response['status'] != 0x00)
					throw new Socks5AuthException('SOCKS username/password authentication failed.');
			}

			// send connection request
			$this->send($this->buildSocksConnectionRequest($host, $port, $this->socks_config['dns_tunnel']));
			
			$response = unpack("Cversion/Cresult/Creg/Ctype/Lip/Sport", $this->read(11));

			if ($response['result'] != 0x00)
			{
				throw new Socks5ConnectionException('SOCKS connection request failed: '.static::getSocksRefusalMsg($respone['result']), $response['result']);
			}
		}
		else
		{
			// use direct connection
			$this->socket = $this->createSocket("tcp://$host:$port");
		}
		
		// enable ssl, if required
		if ($ssl)
		{
			if (stream_socket_enable_crypto($this->socket, TRUE, $ssl_type) !== true)
				throw new SSLException('Could not enable socket encryption.');
		}
	}

	/**
	 * Returns the native PHP socket resource.
	 * (only available when already connected)
	 * 
	 * @access public
	 * @return resource
	 */
	public function getNativeSocket()
	{
		return $this->socket;
	}
	
	/**
	 * Returns the complete response.
	 * 
	 * @param int $maxlength Optional: maximum length to read
	 * @param int $offset    Optional: offset
	 * @access public
	 * @return string
	 */
	public function readAll($maxlength = -1, $offset = -1)
	{
		$data = stream_get_contents($this->socket, $maxlength, $offset);

		if ($data === false)
			throw new IOException('Failed reading response.');

		return $data;
	}
	
	
	/**
	 * Reads the first line of the response.
	 *
	 * @param int $len
	 * @access public
	 * @return string
	 */
	public function readLine($size = 4096)
	{	
		$data = stream_get_line($this->socket, $size);

		if ($data === false)
			throw new IOException('Failed reading response.');

		return $data;
	}
	
	/**
	 * Reads bytes from the response.
	 *
	 * @param int $size
	 * @access public
	 * @return string
	 */
	public function read($size)
	{
		$data = fgets($this->socket, $size);

		if ($data === false)
			throw new IOException('Failed reading response.');

		return $data;
	}
	
	/**
	 * Sends data
	 *
	 * @param string $data
	 * @access public
	 */
	public function send($data)
	{
		$size = fputs($this->socket, $data);

		if ($size === false)
			throw new IOException('Error sending data.');
	}
	
	/**
	 * Creates a socket stream client
	 * 
	 * @param string $url php url formatted string (transport://host:port)
	 * @access protected
	 * @return bool
	 */
	protected function createSocket($url)
	{
		$socket = stream_socket_client($url, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
		
		if( ! is_resource($socket))
			throw new ConnectionException('Failed creating socket client: '.$errstr, $errno);

		return $socket;
	}
	
	/**
	 * Builds a SOCKS5 authentication request
	 * 
	 * @param string $username
	 * @param string $password
	 * @access protected
	 * @return string
	 */
	private function buildSocksAuth($username, $password)
	{
		return pack("CC", 0x01, strlen($username)).$username.pack("C", strlen($password)).$password;
	}

	/**
	 * Builds the intial SOCKS5 greeting request
	 *
	 * @param boolean $method Supported auth method
	 * @access protected
	 * @return string
	 */
	protected function buildSocksGreeting($method)
	{
		return pack("C3", 0x05, 0x01, $method);
	}
	
	/**
	 * Builds a SOCKS5 connection request
	 *
	 * @param string $host Hostname
	 * @param int    $port Port
	 * @access protected
	 * @return string
	 */
	protected function buildSocksConnectionRequest($host, $port, $dnstunnel)
	{
		if ($dnstunnel)
			return pack("C5", 0x05, 0x01, 0x00, 0x03, strlen($host)).$host.pack("n", $port);
		else
			return pack("C4Nn", 0x05, 0x01, 0x00, 0x01, ip2long(gethostbyname($host)), $port);
	}

	protected static function getSocksRefusalMsg($code)
	{
		switch ($code)
		{
			case 0x01:
				return 'General failure';
			case 0x02:
				return 'Connection not allowed by ruleset';
			case 0x03:
				return 'Network unreachable';
			case 0x04:
				return 'Host unreachable';
			case 0x05:
				return 'Connection refused by destination host';
			case 0x06:
				return 'TTL expired';
			case 0x07:
				return 'command not supported / protocol error';
			case 0x08:
				return 'address type not supported';
			default:
				return 'Unknown error';
		}
	}
}
