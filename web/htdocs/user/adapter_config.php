<?php
	require_once("../../classes/TemplateUtil.php");
	require_once("../../classes/DBUtil.php");
	require_once("../../classes/SessionUtil.php");
	session_start();
	
	function skip_to($out, $offset) {
		return $out.str_repeat(hex2bin("00"), $offset - (strlen($out)));
	}
	
	function encode_phone_number($phone_number) {
		$ret = "";
		$nybbles = "0123456789#*";
		$pos = 0;
		$hi = 0;
		if (strlen($phone_number) > 0) {
			// phone number in modified BCD
			foreach (str_split($phone_number) as $num) {
				if ($pos % 2 == 0) {
					$hi = strpos($nybbles, $num) << 4;
					if ($hi === false) {
						$hi = 0xE0; // failsafe for invalid char. should this error?
					}
				} else {
					$lo = strpos($nybbles, $num);
					if ($lo === false) {
						$lo = 0x0E; // failsafe for invalid char. should this error?
					}
					$ret .= pack('C', $hi | $lo);
				}
				$pos += 1;
			}
			// terminator
			if (strlen($phone_number) < 16) {
				if ($pos % 2 == 0) {
					$ret .= pack('C', 0xF0);
				} else {
					$ret .= pack('C', $hi | 0x0F);
				}
			}
		} else {
			$ret .= hex2bin("FFFFFFFFFFFFFFFF");
		}
		
		return skip_to($ret, 8);
	}
	
	function config_slot($name, $phone_number) {
		return encode_phone_number($phone_number).$name;
	}
	
	function checksum($data) {
		$sum = 0;
		foreach (str_split($data) as $byte) {
			$sum += ord($byte);
		}
		return $sum;
	}

	if (SessionUtil::getInstance()->isSessionActive()) {
		$db_util = DBUtil::getInstance();
		
		$db = $db_util->getDB();
		
		$stmt = $db->prepare("select email, dion_ppp_id, dion_email_local, adapter_model, relay_token from sys_users where id = ?");
		$stmt->bind_param("i", $_SESSION["user_id"]);
		$stmt->execute();
		$result = DBUtil::fancy_get_result($stmt)[0];

		// magic
		$out = "MA";
		// dont worry mobile games, its already set up 4 u :3
		$out .= hex2bin("8100");

		// DNS server 1
		$remoteIP = $_SERVER['REMOTE_ADDR'];
		if (strstr($remoteIP, ', ')) {
			$ips = explode(', ', $remoteIP);
			$remoteIP = $ips[0];
		}
		$remoteIP = explode('.', $remoteIP);
		$out .= pack('C', $remoteIP[0]).pack('C', $remoteIP[1]).pack('C', $remoteIP[2]).pack('C', $remoteIP[3]);

		// DNS server 2
		$out .= hex2bin("00000000");

		// gID
		$out .= $result["dion_ppp_id"];//"g".sprintf('%09d',$result["dion_ppp_id"]);
		
		// email
		$out = skip_to($out, 0x2C);
		$out .= $result["dion_email_local"]."@".ConfigUtil::getInstance()->getConfig()["email_domain_dion"];
		
		// SMTP server
		$out = skip_to($out, 0x4A);
		$out .= "mail.".ConfigUtil::getInstance()->getConfig()["email_domain_dion"];
		
		// POP3 server
		$out = skip_to($out, 0x5E);
		$out .= "pop.".ConfigUtil::getInstance()->getConfig()["email_domain_dion"];
		
		// Config slot 1
		$out = skip_to($out, 0x76);
		$out .= config_slot(ConfigUtil::getInstance()->getConfig()["mobile_center_name"], ConfigUtil::getInstance()->getConfig()["mobile_center_numb"]);
		
		// Config slot 2
		$out = skip_to($out, 0x8E);
		$out .= config_slot("", "");
		
		// Config slot 3
		$out = skip_to($out, 0xA6);
		$out .= config_slot("", "");
		
		// checksum
		$out = skip_to($out, 0xBE);
		$out .= pack('n', checksum($out) % 0x10000);
		
		
		
		// additional data used by libmobile
		$out = skip_to($out, 0x100);
		// magic
		$out .= "LM";
		$out .= hex2bin("00");
		
		$lib_data = "";
		
		// adapter model
		$lib_data .= pack('C', $result["adapter_model"] + 8);
		
		// dns types
		$lib_data .= hex2bin("00"); // DNS1
		$lib_data .= hex2bin("00"); // DNS2
		
		// P2P port
		$lib_data .= pack('v', 1027);
		
		// relay type
		$lib_data .= hex2bin("01"); // IPv4
		
		// relay token init flag
		if ($result["relay_token"]) {
			$lib_data .= hex2bin("01");
		} else {
			$lib_data .= hex2bin("00");
		}
		
		// 0x0c - 0x19 unused
		$lib_data = skip_to($lib_data, 0x1A - 5);
		
		// dns1 port
		$lib_data .= pack('v', 0);
		// dns2 port
		$lib_data .= pack('v', 0);
		// relay port
		$lib_data .= pack('v', 31227);
		// dns1 addr
		$lib_data = skip_to($lib_data, 0x30 - 5);
		// dns2 addr
		$lib_data = skip_to($lib_data, 0x40 - 5);
		// relay addr
		$lib_data = pack('C', $remoteIP[0]).pack('C', $remoteIP[1]).pack('C', $remoteIP[2]).pack('C', $remoteIP[3]);
		$lib_data = skip_to($lib_data, 0x50 - 5);
		// relay token
		if ($result["relay_token"]) {
			$lib_data .= $result["relay_token"];
		}
		
		$lib_data = skip_to($lib_data, 0x60 - 5);
		
		$out .= pack('v', checksum($lib_data) % 0x10000);
		$out .= $lib_data;
		
		$out = skip_to($out, 0x200);
		
		print $out;
		
	} else {
		header("Location: /index.php");
	}
