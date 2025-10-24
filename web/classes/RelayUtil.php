<?php
	require_once("../../classes/DBUtil.php");

	class RelayUtil {
		
		private static $instance;
		private static $protocol_version = "\x00";
		
		private final function  __construct() {
		}
		
		public static function getInstance() {
			if(!isset(self::$instance)) {
				self::$instance = new RelayUtil();
			}
			return self::$instance;
		}
		
		private static function new_relay_socket() {
			// connect to relay server
			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			socket_connect($socket, 'localhost', 31227);
			return $socket;
		}
		
		private static function exchange_handshake($socket, $user_id) {
			
			$handshake_magic = self::$protocol_version."MOBILE";
			
			$db_util = DBUtil::getInstance();
			$db = $db_util->getDB();
			
			// get relay token
			$stmt = $db->prepare("select relay_token from sys_users where id = ?");
			$stmt->bind_param("i", $user_id);
			$stmt->execute();
			$token = DBUtil::fancy_get_result($stmt)[0]["relay_token"];
			
			// send handshake
			$relay_command = "".$handshake_magic;
			if ($token) {
				$relay_command .= "\x01".$token;
			} else {
				$relay_command .= "\x00";
			}
			if (!socket_write($socket, $relay_command, strlen($relay_command))) {
				return -1;
			}
			$relay_reply = socket_read($socket, strlen($handshake_magic));
			
			// receive handshake
			if ($relay_reply != $handshake_magic) {
				//print "relay handshake error<BR>";
				//print bin2hex($relay_reply)."<BR>";
				return -1;
			}
			
			$auth = socket_read($socket, 1);
			
			if (substr($auth,0,1) == "\x00") {
				// pass
			} else if (substr($auth,0,1) == "\x01") {
				$token = socket_read($socket, 16);
				$stmt = $db->prepare("update sys_users set relay_token = UNHEX(?) where id = ?");
				$stmt->bind_param("si", bin2hex($token), $user_id);
				$stmt->execute();
			} else {
				//print "relay auth error<BR>";
				//print bin2hex($relay_reply)."<BR>";
				return -1;
			}
		}

		// get user's phone number from local relay server
		public static function get_phone_number($user_id) {
			
			$socket = self::new_relay_socket();
			
			self::exchange_handshake($socket, $user_id);
			
			// ask for phone number
			$relay_command = self::$protocol_version."\x02";
			if (!socket_write($socket, $relay_command, strlen($relay_command))) {
				return -1;
			}
			$relay_reply = socket_read($socket, 2);
			
			if ($relay_reply != self::$protocol_version."\x02") {
				//print "relay response error<BR>";
				//print bin2hex($relay_reply)."<BR>";
				return -1;
			}
			
			$number_len = unpack("Cara",socket_read($socket, 1))["ara"];
			if ($number_len == 0 || $number_len > 16) {
				//print "your phone number exceeded the digits and then fell off the end error<BR>";
				//print $number_len."<BR>";
				return -1;
			}
			
			return socket_read($socket, $number_len);
		}
	}
?>	