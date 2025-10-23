<?php
require_once(CORE_PATH."/database.php");

function get_game_parameters($region) {
	$db = connectMySQL();
	$stmt = $db->prepare("select id, octet_length(game_binary), genre, level_react, level_smart, level_sense, level_hidden, title, description, price from bmv".$region."_games order by id desc");
	$stmt->execute();
	return fancy_get_result($stmt);
}

function get_game_parameters_bin($region) {
	$db = connectMySQL();
	$stmt = $db->prepare("select count(*) from bmv".$region."_games");
	$stmt->execute();
	$num_games = min(fancy_get_result($stmt)[0]['count(*)'], 255);
	
	$out = pack('C', $num_games);

	$game_pointers = "";
	$game_data = "";

	$games = get_game_parameters($region);
	$game_num = 0;
	foreach ($games as $game) {
		
		// pointer table entry to following data
		$game_pointers .= pack('v', ((($num_games * 2)+1)+strlen($game_data))-4);
		
		
		// blocks used
		$game_data .= pack('C', ceil($game["octet_length(game_binary)"] / (float)0x2000) % 100);
		// genre
		$game_data .= pack('C', $game["genre"]);
		// G### ID
		$game_data .= "G".sprintf('%03d',$game["id"]);
		// ???
		$game_data .= hex2bin('0000');
		// level requirements
		$game_data .= pack('C', $game['level_react']);
		$game_data .= pack('C', $game['level_smart']);
		$game_data .= pack('C', $game['level_sense']);
		$game_data .= hex2bin('00'); // level type 4?
		$game_data .= hex2bin('00'); // level type 5?
		$game_data .= hex2bin('00'); // level type 6?
		$game_data .= pack('C', $game['level_hidden']); // hidden level
		$game_data .= hex2bin('00'); // level type 8?
		// title
		$game_data .= pack('C', strlen($game['title']));
		$game_data .= $game['title'];
		// description
		$game_data .= pack('C', strlen($game['description']));
		$game_data .= $game['description'];
		// ???
		$game_data .= hex2bin('00');
		// price in yen
		$game_data .= sprintf('%04d',$game["price"]);
		// game id
		$game_data .= "G".sprintf('%03d',$game["id"]).".cgb\x00";
		
		
		$game_num += 1;
		if ($game_num >= 256) {
			break;
		}
	}

	$out .= $game_pointers;
	$out .= $game_data;
	
	return $out;
}

function download_game($region, $game_id) {
	$db = connectMySQL();
	$stmt = $db->prepare("select octet_length(game_binary), game_binary from bmv".strtolower($region)."_games where id = ".$game_id."");
	$stmt->execute();
	$game_data = fancy_get_result($stmt)[0];
	
	// wrapper header
	// offset - 1
	$out = hex2bin('03000000');
	// ???
	$out .= hex2bin('00');
	// block-fill flag
	$out .= hex2bin('00');
	// ???
	$out .= hex2bin('0000');
	// size
	$data_size = max(0x100, $game_data['octet_length(game_binary)']);
	$out .= pack('v', $data_size);
	// ???
	$out .= hex2bin('0000');
	
	$game_binary = substr($game_data['game_binary'], 0, 10).sprintf('%03d',$game_id).substr($game_data['game_binary'], 13);
	
	while (strlen($game_binary) > 0) {
		// game binary block
		$block_size = min(0x200, strlen($game_binary));
		$out .= substr($game_binary, 0, $block_size);
		$game_binary = substr($game_binary, $block_size);
		
		// wrapper footer
		$footer_sequence = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 255, 
								 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,  0,  0,  0,  0,  0,   0, 
							     0, 0, 0, 0, 0, 0, 0, 0, 0, 0,  0,  0,  0,  0,  0,   0, 
							     0, 0, 0, 0, 0, 0, 0, 0, 0, 0,  0,  0,  0,  0,  0,   0, 
							     0, 0, 0, 0, 0, 0, 0, 0, 0, 0,  0,  0,  0,  0,  0,   0, 
								 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,  0,  0,  0,  0,  0,   0, 
							     0, 0, 0, 0, 0, 0, 0, 0, 0, 0,  0,  0,  0,  0,  0,   0, 
							     0, 0, 0, 0, 0, 0, 0, 0, 0, 0,  0,  0,  0,  0,  0,   0);
		foreach ($footer_sequence as $foot_num) {
			$out .= pack('v', $foot_num);
		}
	}
	
	print $out;
	//print $game_data['game_binary'];
}
