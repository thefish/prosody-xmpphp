<?php
/**
 * XMPPHP: The PHP XMPP Library
 * Copyright (C) 2008  Nathanael C. Fritz
 * This file is part of SleekXMPP.
 * 
 * XMPPHP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * XMPPHP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with XMPPHP; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category   xmpphp 
 * @package	XMPPHP
 * @author	 Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author	 Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author	 Michael Garvin <JID: gar@netflint.net>
 * @copyright  2008 Nathanael C. Fritz
 */

/** XMPPHP_Exception */
require_once dirname(__FILE__) . '/Exception.php';

/** XMPPHP_XMLObj */
require_once dirname(__FILE__) . '/XMLObj.php';

/** XMPPHP_Log */
require_once dirname(__FILE__) . '/Log.php';

/**
 * XMPPHP XML Stream
 * 
 * @category   xmpphp 
 * @package	XMPPHP
 * @author	 Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author	 Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author	 Michael Garvin <JID: gar@netflint.net>
 * @copyright  2008 Nathanael C. Fritz
 * @version	$Id$
 */
class XMPPHP_XMLStream {
	/**
	 * @var resource
	 */
	protected $socket;
	/**
	 * @var resource
	 */
	protected $parser;
	/**
	 * @var string
	 */
	protected $buffer;
	/**
	 * @var integer
	 */
	protected $xmlDepth = 0;
	/**
	 * @var string
	 */
	protected $host;
	/**
	 * @var integer
	 */
	protected $port;
	/**
	 * @var string
	 */
	protected $streamStart = '<stream>';
	/**
	 * @var string
	 */
	protected $streamEnd = '</stream>';
	/**
	 * @var boolean
	 */
	protected $disconnected = false;
	/**
	 * @var boolean
	 */
	protected $sentDisconnect = false;
	/**
	 * @var array
	 */
	protected $nsMap = array();
	/**
	 * @var array
	 */
	protected $currentNs = array();
	/**
	 * @var array
	 */
	protected $xmlobj = null;
	/**
	 * @var array
	 */
	protected $nshandlers = array();
	/**
	 * @var array
	 */
	protected $xpathhandlers = array();
	/**
	 * @var array
	 */
	protected $idhandlers = array();
	/**
	 * @var array
	 */
	protected $eventhandlers = array();
	/**
	 * @var integer
	 */
	protected $lastid = 0;
	/**
	 * @var string
	 */
	protected $defaultNs;
	/**
	 * @var string
	 */
	protected $until = '';
	/**
	 * @var string
	 */
	protected $untilCount = '';
	/**
	 * @var array
	 */
	protected $untilPayload = array();
	/**
	 * @var XMPPHP_Log
	 */
	protected $log;
	/**
	 * @var boolean
	 */
	protected $reconnect = true;
	/**
	 * @var boolean
	 */
	protected $beenReset = false;
	/**
	 * @var boolean
	 */
	protected $isServer;
	/**
	 * @var boolean
	 */
	protected $useSSL = false;
	/**
	 * @var integer
	 */
	protected $reconnectTimeout = 30;

	/**
	 * Constructor
	 *
	 * @param string  $host
	 * @param string  $port
	 * @param boolean $printlog
	 * @param string  $loglevel
	 * @param boolean $isServer
	 */
	public function __construct($host = null, $port = null, $printlog = true, $loglevel = XMPPHP_Log::LEVEL_DEBUG, $isServer = false) {
		$this->reconnect = !$isServer;
		$this->isServer = $isServer;
		$this->host = $host;
		$this->port = $port;
		$this->setupParser();
		$this->log = new XMPPHP_Log($printlog, $loglevel);
	}

	/**
	 * Destructor
	 * Cleanup connection
	 */
	public function __destruct() {
		if(!$this->disconnected && $this->socket) {
			$this->disconnect();
		}
	}
	
	/**
	 * Return the log instance
	 *
	 * @return XMPPHP_Log
	 */
	public function getLog() {
		return $this->log;
	}
	
	/**
	 * Get next ID
	 *
	 * @return integer
	 */
	public function getId() {
		$this->lastid++;
		return $this->lastid;
	}

	/**
	 * Set SSL
	 *
	 * @return integer
	 */
	public function useSSL($use=true) {
		$this->useSSL = $use;
	}

	/**
	 * Add ID Handler
	 *
	 * @param integer $idHandler
	 * @param string  $pointer
	 * @param string  $obj
	 */
	public function addIdHandler($idHandler, $pointer, $obj = null) {
		$this->idhandlers[$idHandler] = array($pointer, $obj);
	}

	/**
	 * Add Handler
	 *
	 * @param string $name
	 * @param string  $nameSpace
	 * @param string  $pointer
	 * @param string  $obj
	 * @param integer $depth
	 */
	public function addHandler($name, $nameSpace, $pointer, $obj = null, $depth = 1) {
		#TODO deprication warning
		$this->nshandlers[] = array($name,$nameSpace,$pointer,$obj, $depth);
	}

	/**
	 * Add XPath Handler
	 *
	 * @param string $xpath
	 * @param string $pointer
	 * @param
	 */
	public function addXPathHandler($xpath, $pointer, $obj = null) {
		if (preg_match_all("/\(?{[^\}]+}\)?(\/?)[^\/]+/", $xpath, $regs)) {
			$nsTags = $regs[0];
		} else {
			$nsTags = array($xpath);
		}
		foreach($nsTags as $nsTag) {
            // PHP 5.3+ Fix
			list($l, $r) = preg_split("/}/", $nsTag);
			if ($r != null) {
				$xpart = array(substr($l, 1), $r);
			} else {
				$xpart = array(null, $l);
			}
			$xpathArray[] = $xpart;
		}
		$this->xpathhandlers[] = array($xpathArray, $pointer, $obj);
	}

	/**
	 * Add Event Handler
	 *
	 * @param integer $name
	 * @param string  $pointer
	 * @param string  $obj
	 */
	public function addEventHandler($name, $pointer, $obj) {
		$this->eventhandlers[] = array($name, $pointer, $obj);
	}

	/**
	 * Connect to XMPP Host
	 *
	 * @param integer $timeout
	 * @param boolean $persistent
	 * @param boolean $sendinit
	 */
	public function connect($timeout = 30, $persistent = false, $sendinit = true) {
		$this->sentDisconnect = false;
		$starttime = time();
		
		do {
			$this->disconnected = false;
			$this->sentDisconnect = false;
			if($persistent) {
				$conflag = STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;
			} else {
				$conflag = STREAM_CLIENT_CONNECT;
			}
			$conntype = 'tcp';
            $errno = 0;
            $errstr = "";
			if($this->useSSL) $conntype = 'ssl';
			$this->log->log("Connecting to $conntype://{$this->host}:{$this->port}");
			try {
				$this->socket = @stream_socket_client("$conntype://{$this->host}:{$this->port}", $errno, $errstr, $timeout, $conflag);
			} catch (Exception $e) {
				throw new XMPPHP_Exception($e->getMessage());
			}
			if(!$this->socket) {
				$this->log->log("Could not connect.",  XMPPHP_Log::LEVEL_ERROR);
				$this->disconnected = true;
				# Take it easy for a few seconds
				sleep(min($timeout, 5));
			}
		} while (!$this->socket && (time() - $starttime) < $timeout);
		
		if ($this->socket) {
			stream_set_blocking($this->socket, 1);
			if($sendinit) $this->send($this->streamStart);
		} else {
			throw new XMPPHP_Exception("Could not connect before timeout.");
		}
	}

	/**
	 * Reconnect XMPP Host
	 */
	public function doReconnect() {
		if(!$this->isServer) {
			$this->log->log("Reconnecting ($this->reconnectTimeout)...",  XMPPHP_Log::LEVEL_WARNING);
			$this->connect($this->reconnectTimeout, false, false);
			$this->reset();
			$this->event('reconnect');
		}
	}

	public function setReconnectTimeout($timeout) {
		$this->reconnectTimeout = $timeout;
	}
	
	/**
	 * Disconnect from XMPP Host
	 */
	public function disconnect() {
		$this->log->log("Disconnecting...",  XMPPHP_Log::LEVEL_VERBOSE);
		if(false == (bool) $this->socket) {
			return;
		}
		$this->reconnect = false;
		$this->send($this->streamEnd);
		$this->sentDisconnect = true;
		$this->processUntil('end_stream', 5);
		$this->disconnected = true;
	}

	/**
	 * Are we are disconnected?
	 *
	 * @return boolean
	 */
	public function isDisconnected() {
		return $this->disconnected;
	}

	/**
	 * Core reading tool
	 * 0 -> only read if data is immediately ready
	 * NULL -> wait forever and ever
	 * integer -> process for this amount of time 
	 */
	
	private function __process($maximum=5) {
		
		$remaining = $maximum;
		do {
			$starttime = (microtime(true) * 1000000);
			$read = array($this->socket);
			$write = array();
			$except = array();
			if (is_null($maximum)) {
				$secs = NULL;
				$usecs = NULL;
			} else if ($maximum == 0) {
				$secs = 0;
				$usecs = 0;
			} else {
				$usecs = $remaining % 1000000;
				$secs = floor(($remaining - $usecs) / 1000000);
			}
			$updated = @stream_select($read, $write, $except, $secs, $usecs);
			if ($updated === false) {
				$this->log->log("Error on stream_select()",  XMPPHP_Log::LEVEL_VERBOSE);
				if ($this->reconnect) {
					$this->doReconnect();
				} else {
					@fclose($this->socket);
					$this->socket = NULL;
					return false;
				}
			} else if ($updated > 0) {
				# XXX: Is this big enough?
				$buff = @fread($this->socket, 4096);
				if(!$buff) { 
					if($this->reconnect) {
						$this->doReconnect();
					} else {
						fclose($this->socket);
						$this->socket = NULL;
						return false;
					}
				}
				$this->log->log("RECV: $buff",  XMPPHP_Log::LEVEL_VERBOSE);
				xml_parse($this->parser, $buff, false);
			} else {
				# $updated == 0 means no changes during timeout.
			}
			$endtime = (microtime(true)*1000000);
			$timePast = $endtime - $starttime;
			$remaining = $remaining - $timePast;
		} while (is_null($maximum) || $remaining > 0);
		return true;
	}
	
	/**
	 * Process
	 *
	 * @return string
	 */
	public function process() {
		$this->__process(NULL);
	}

	/**
	 * Process until a timeout occurs
	 *
	 * @param integer $timeout
	 * @return string
	 */
	public function processTime($timeout=NULL) {
		if (is_null($timeout)) {
			return $this->__process(NULL);
		} else {
			return $this->__process($timeout * 1000000);
		}
	}

	/**
	 * Process until a specified event or a timeout occurs
	 *
	 * @param string|array $event
	 * @param integer $timeout
	 * @return string
	 */
	public function processUntil($event, $timeout=-1) {
		$start = time();
		if(!is_array($event)) $event = array($event);
		$this->until[] = $event;
		end($this->until);
		$eventKey = key($this->until);
		reset($this->until);
		$this->untilCount[$eventKey] = 0;
		while(!$this->disconnected and $this->untilCount[$eventKey] < 1 and (time() - $start < $timeout or $timeout == -1)) {
			$this->__process();
		}
		if(array_key_exists($eventKey, $this->untilPayload)) {
			$payload = $this->untilPayload[$eventKey];
			unset($this->untilPayload[$eventKey]);
			unset($this->untilCount[$eventKey]);
			unset($this->until[$eventKey]);
		} else {
			$payload = array();
		}
		return $payload;
	}

	/**
	 * Obsolete?
	 */
	public function Xapply_socket($socket) {
		$this->socket = $socket;
	}

	/**
	 * XML start callback
	 * 
	 * @see xml_set_element_handler
	 *
	 * @param resource $parser
	 * @param string   $name
	 */
	public function startXML($parser, $name, $attr) {
        $parser = null; // cause not used so far
		if($this->beenReset) {
			$this->beenReset = false;
			$this->xmlDepth = 0;
		}
		$this->xmlDepth++;
		if(array_key_exists('XMLNS', $attr)) {
			$this->currentNs[$this->xmlDepth] = $attr['XMLNS'];
		} else {
			$this->currentNs[$this->xmlDepth] = $this->currentNs[$this->xmlDepth - 1];
			if(!$this->currentNs[$this->xmlDepth]) $this->currentNs[$this->xmlDepth] = $this->defaultNs;
		}
		$nameSpace = $this->currentNs[$this->xmlDepth];
		foreach($attr as $key => $value) {
			if(strstr($key, ":")) {
				$key = explode(':', $key);
				$key = $key[1];
				$this->nsMap[$key] = $value;
			}
		}
		if(!strstr($name, ":") === false)
		{
			$name = explode(':', $name);
			$nameSpace = $this->nsMap[$name[0]];
			$name = $name[1];
		}
		$obj = new XMPPHP_XMLObj($name, $nameSpace, $attr);
		if($this->xmlDepth > 1) {
			$this->xmlobj[$this->xmlDepth - 1]->subs[] = $obj;
		}
		$this->xmlobj[$this->xmlDepth] = $obj;
	}

	/**
	 * XML end callback
	 * 
	 * @see xml_set_element_handler
	 *
	 * @param resource $parser
	 * @param string   $name
	 */
	public function endXML($parser, $name) {
        $parser = null; // cause not used so far
        $name   = null; // cause not used so far
		if($this->beenReset) {
			$this->beenReset = false;
			$this->xmlDepth = 0;
		}
		$this->xmlDepth--;
		if($this->xmlDepth == 1) {
			#clean-up old objects
			#$found = false; #FIXME This didn't appear to be in use --Gar
			foreach($this->xpathhandlers as $handler) {
				if (is_array($this->xmlobj) && array_key_exists(2, $this->xmlobj)) {
					$searchxml = $this->xmlobj[2];
					$nstag = array_shift($handler[0]);
					if (($nstag[0] == null or $searchxml->nameSpace == $nstag[0]) and ($nstag[1] == "*" or $nstag[1] == $searchxml->name)) {
						foreach($handler[0] as $nstag) {
							if ($searchxml !== null and $searchxml->hasSub($nstag[1], $nameSpace=$nstag[0])) {
								$searchxml = $searchxml->sub($nstag[1], $nameSpace=$nstag[0]);
							} else {
								$searchxml = null;
								break;
							}
						}
						if ($searchxml !== null) {
							if($handler[2] === null) $handler[2] = $this;
							$this->log->log("Calling {$handler[1]}",  XMPPHP_Log::LEVEL_DEBUG);
							$handler[2]->$handler[1]($this->xmlobj[2]);
						}
					}
				}
			}
			foreach($this->nshandlers as $handler) {
				if($handler[4] != 1 and array_key_exists(2, $this->xmlobj) and  $this->xmlobj[2]->hasSub($handler[0])) {
					$searchxml = $this->xmlobj[2]->sub($handler[0]);
				} elseif(is_array($this->xmlobj) and array_key_exists(2, $this->xmlobj)) {
					$searchxml = $this->xmlobj[2];
				}
				if($searchxml !== null and $searchxml->name == $handler[0] and ($searchxml->nameSpace == $handler[1] or (!$handler[1] and $searchxml->nameSpace == $this->defaultNs))) {
					if($handler[3] === null) $handler[3] = $this;
					$this->log->log("Calling {$handler[2]}",  XMPPHP_Log::LEVEL_DEBUG);
					$handler[3]->$handler[2]($this->xmlobj[2]);
				}
			}
			foreach($this->idhandlers as $idHandler => $handler) {
				if(array_key_exists('id', $this->xmlobj[2]->attrs) and $this->xmlobj[2]->attrs['id'] == $idHandler) {
					if($handler[1] === null) $handler[1] = $this;
					$handler[1]->$handler[0]($this->xmlobj[2]);
					#id handlers are only used once
					unset($this->idhandlers[$idHandler]);
					break;
				}
			}
			if(is_array($this->xmlobj)) {
				$this->xmlobj = array_slice($this->xmlobj, 0, 1);
				if(isset($this->xmlobj[0]) && $this->xmlobj[0] instanceof XMPPHP_XMLObj) {
					$this->xmlobj[0]->subs = null;
				}
			}
			unset($this->xmlobj[2]);
		}
		if($this->xmlDepth == 0 and !$this->beenReset) {
			if(!$this->disconnected) {
				if(!$this->sentDisconnect) {
					$this->send($this->streamEnd);
				}
				$this->disconnected = true;
				$this->sentDisconnect = true;
				fclose($this->socket);
				if($this->reconnect) {
					$this->doReconnect();
				}
			}
			$this->event('end_stream');
		}
	}

	/**
	 * XML character callback
	 * @see xml_set_character_data_handler
	 *
	 * @param resource $parser
	 * @param string   $data
	 */
	public function charXML($parser, $data) {
        $parser = null; // cause not used so far
		if(array_key_exists($this->xmlDepth, $this->xmlobj)) {
			$this->xmlobj[$this->xmlDepth]->data .= $data;
		}
	}

	/**
	 * Event?
	 *
	 * @param string $name
	 * @param string $payload
	 */
	public function event($name, $payload = null) {
		$this->log->log("EVENT: $name",  XMPPHP_Log::LEVEL_DEBUG);
		foreach($this->eventhandlers as $handler) {
			if($name == $handler[0]) {
				if($handler[2] === null) {
					$handler[2] = $this;
				}
				$handler[2]->$handler[1]($payload);
			}
		}
		foreach($this->until as $key => $until) {
			if(is_array($until)) {
				if(in_array($name, $until)) {
					$this->untilPayload[$key][] = array($name, $payload);
					if(!isset($this->untilCount[$key])) {
						$this->untilCount[$key] = 0;
					}
					$this->untilCount[$key] += 1;
					#$this->until[$key] = false;
				}
			}
		}
	}

	/**
	 * Read from socket
	 */
	public function read() {
		$buff = @fread($this->socket, 1024);
		if(!$buff) { 
			if($this->reconnect) {
				$this->doReconnect();
			} else {
				fclose($this->socket);
				return false;
			}
		}
		$this->log->log("RECV: $buff",  XMPPHP_Log::LEVEL_VERBOSE);
		xml_parse($this->parser, $buff, false);
	}

	/**
	 * Send to socket
	 *
	 * @param string $msg
	 */
	public function send($msg, $timeout=NULL) {

		if (is_null($timeout)) {
			$secs = NULL;
			$usecs = NULL;
		} else if ($timeout == 0) {
			$secs = 0;
			$usecs = 0;
		} else {
			$maximum = $timeout * 1000000;
			$usecs = $maximum % 1000000;
			$secs = floor(($maximum - $usecs) / 1000000);
		}
		
		$read = array();
		$write = array($this->socket);
		$except = array();
		
		$select = @stream_select($read, $write, $except, $secs, $usecs);
		
		if($select === False) {
			$this->log->log("ERROR sending message; reconnecting.");
			$this->doReconnect();
			# TODO: retry send here
			return false;
		} elseif ($select > 0) {
			$this->log->log("Socket is ready; send it.", XMPPHP_Log::LEVEL_VERBOSE);
		} else {
			$this->log->log("Socket is not ready; break.", XMPPHP_Log::LEVEL_ERROR);
			return false;
		}
		
		$sentbytes = @fwrite($this->socket, $msg);
		$this->log->log("SENT: " . mb_substr($msg, 0, $sentbytes, '8bit'), XMPPHP_Log::LEVEL_VERBOSE);
		if($sentbytes === FALSE) {
			$this->log->log("ERROR sending message; reconnecting.", XMPPHP_Log::LEVEL_ERROR);
			$this->doReconnect();
			return false;
		}
		$this->log->log("Successfully sent $sentbytes bytes.", XMPPHP_Log::LEVEL_VERBOSE);
		return $sentbytes;
	}

	public function time() {
		list($usec, $sec) = explode(" ", microtime());
		return (float)$sec + (float)$usec;
	}

	/**
	 * Reset connection
	 */
	public function reset() {
		$this->xmlDepth = 0;
		unset($this->xmlobj);
		$this->xmlobj = array();
		$this->setupParser();
		if(!$this->isServer) {
			$this->send($this->streamStart);
		}
		$this->beenReset = true;
	}

	/**
	 * Setup the XML parser
	 */
	public function setupParser() {
		$this->parser = xml_parser_create('UTF-8');
		xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, 'startXML', 'endXML');
		xml_set_character_data_handler($this->parser, 'charXML');
	}

	public function readyToProcess() {
		$read = array($this->socket);
		$write = array();
		$except = array();
		$updated = @stream_select($read, $write, $except, 0);
		return (($updated !== false) && ($updated > 0));
	}
}
