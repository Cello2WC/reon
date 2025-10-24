<?php
	require_once("../../classes/TemplateUtil.php");
	require_once("../../classes/DBUtil.php");
	require_once("../../classes/SessionUtil.php");
	
	require_once("../../classes/UserUtil.php");
	require_once("../../classes/RelayUtil.php");
	session_start();
	
	if (SessionUtil::getInstance()->isSessionActive()) {
		
		// user clicked "save" on adapter config
		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			if (isset($_POST["adapterModel"])) {
				$result = UserUtil::getInstance()->setAdapterModel($_POST["adapterModel"]);
			} else {
				http_response_code(400);
			}
		}
		$db_util = DBUtil::getInstance();
		
		$db = $db_util->getDB();
		$stmt = $db->prepare("select email, dion_ppp_id, dion_email_local, log_in_password, money_spent, adapter_model from sys_users where id = ?");
		$stmt->bind_param("i", $_SESSION["user_id"]);
		$stmt->execute();
		$result = DBUtil::fancy_get_result($stmt)[0];
		
		$db = $db_util->getDB();
		$stmt = $db->prepare("select count(*) from sys_inbox where recipient = ?");
		$stmt->bind_param("i", $_SESSION["user_id"]);
		$stmt->execute();
		$inbox_size = DBUtil::fancy_get_result($stmt)[0]["count(*)"];
		
		//$relay_util = RelayUtil::getInstance();
		$phone_number = RelayUtil::get_phone_number($_SESSION["user_id"]);
		
		if ($phone_number == -1) {
			$phone_number = "Relay server error...";
		} else {
			$phone_number = substr($phone_number, 0, 2)."-".substr($phone_number, 2, 4)."-".substr($phone_number, 6);
		}
		
		echo TemplateUtil::render("/user/summary", [
			"email" => $result["email"],
			"dion_ppp_id" => $result["dion_ppp_id"],
			"dion_email" => $result["dion_email_local"]."@".ConfigUtil::getInstance()->getConfig()["email_domain_dion"],
			"log_in_password" => $result["log_in_password"],
			"money_spent" => $result["money_spent"],
			"inbox_size" => $inbox_size,
			"phone_number" => $phone_number,
			"adapter_model" => $result["adapter_model"]
		]);
	} else {
		header("Location: /index.php");
	}