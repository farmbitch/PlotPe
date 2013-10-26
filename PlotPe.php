<?php

/*
__PocketMine Plugin__
name=PlotPe
description=PlotMe ported
version=1.0
author=wies
class=PlotPe
apiversion=10
*/
		
class PlotPe implements Plugin{
	private $api;
	public $database;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->path = $this->api->plugin->configPath($this);
		$this->api->console->register("plot", "Plot Commands", array($this, "command"));
		$this->api->ban->cmdWhitelist("plot");
		$this->api->addHandler("player.block.place", array($this, "block"));
		$this->api->addHandler("player.block.break", array($this, "block"));
		$this->api->addHandler("player.block.touch", array($this, "block"));
		$this->config = new Config($this->path."config.yml", CONFIG_YAML, array(
			'PlotSize' => 32,
			'RoadSize' => 3,
			'PlotFloorBlockId' => 2,
			'PlotFillingBlockId' => 3,
			'CornerBlockId' => 44,
			'RoadBlockId' => 5,
		));
		$this->config = $this->api->plugin->readYAML($this->path . "config.yml");
		$this->database = new PDO("sqlite:".$this->api->plugin->configPath($this)."PlotMe.db");  
		$this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		$this->database->exec(
			"CREATE TABLE IF NOT EXISTS plots (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			pid INTEGER NOT NULL,
			owner TEXT,
			helpers TEXT,
			x1 INTEGER NOT NULL,
			z1 INTEGER NOT NULL,
			x2 INTEGER NOT NULL,
			z2 INTEGER NOT NULL,
			level TEXT NOT NULL
		)");
		$this->database->exec(
			"CREATE TABLE IF NOT EXISTS comments (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			pid INTEGER NOT NULL,
			writer TEXT NOT NULL,
			message TEXT NOT NULL
		)");
		$this->numberofworlds = 0;
		for($i = 1;;$i++){
			if(file_exists(DATA_PATH . 'worlds/plotworld'.$i.'/level.pmf')){
				$this->api->level->loadLevel('plotworld'.$i);
			}else{
				$this->numberofworlds = ($i - 1);
				break;
			}
		}
		$this->CreateYBlocks();
		$this->CreateShape();
		$this->CreatePlotTemplate();
		
	}
	
	public function CreateShape(){
		$width = 1;
		$length = 1;
		for($z = 1; $z < 256; $z++){
			if(($z - $length) <= $this->config['PlotSize']){
				$width = 1;
				for($x = 1; $x < 256; $x++){
					if(($x - $width) <= $this->config['PlotSize']){
						$shape[$z][$x] = 0;
					}else{
						$shape[$z][$x] = 2;
						$startx = $x;
						$x++;
						for(; $x <= ($startx + $this->config['RoadSize']); $x++){
							$shape[$z][$x] = 1;
						}
						$shape[$z][$x] = 2;
						$width = $x + 1;
					}
				}
			}else{
				$width = 1;
				for($x = 1; $x < 256; $x++){
					if(($x - $width) <= $this->config['PlotSize']){
						$shape[$z][$x] = 2;
					}else{
						$shape[$z][$x] = 2;
						$startx = $x;
						$x++;
						for(;$x <= ($startx + $this->config['RoadSize']); $x++){
							$shape[$z][$x] = 1;
						}
						$shape[$z][$x] = 2;
						$width = $x + 1;
					}
				}
				$size = $z + $this->config['RoadSize'];
				$z++;
				for(; $z <= $size; $z++){
					for($x = 1; $x < 256; $x++){
						$shape[$z][$x] = 1;
					}
				}
				$width = 1;
				for($x = 1; $x < 256; $x++){
					if(($x - $width) <= $this->config['PlotSize']){
						$shape[$z][$x] = 2;
					}else{
						$shape[$z][$x] = 2;
						$startx = $x;
						$x++;
						for(;$x <= ($startx + $this->config['RoadSize']); $x++){
							$shape[$z][$x] = 1;
						}
						$shape[$z][$x] = 2;
						$width = $x + 1;
					}
				}
				$length = $z + 1;
			}
		}
		$z = 0;
		for($x = 0; $x < 256; $x++){
			$shape[$z][$x] = 2;
		}
		$x = 0;
		for($z = 0; $z < 256; $z++){
			$shape[$z][$x] = 2;
		}
		$this->shape = $shape;
	}
	
	public function CreateYBlocks(){
		$yblocks = array();
		$yblocks[0][0] = 7;
		$yblocks[1][0] = 7;
		$yblocks[2][0] = 7;
		for($i = 1; $i < 128; $i++){
			if($i <= 25){
				$yblocks[0][$i] = $this->config['PlotFillingBlockId'];
				$yblocks[1][$i] = $this->config['PlotFillingBlockId'];
				$yblocks[2][$i] = $this->config['PlotFillingBlockId'];
			}else{
				$yblocks[0][$i] = 0;
				$yblocks[1][$i] = 0;
				$yblocks[2][$i] = 0;
			}
		}
		$yblocks[0][26] = $this->config['PlotFloorBlockId'];
		$yblocks[1][26] = $this->config['RoadBlockId'];
		$yblocks[2][26] = $this->config['RoadBlockId'];
		$yblocks[2][27] = $this->config['CornerBlockId'];
		
		$this->yblocks = $yblocks;
	}
	
	public function CreatePlotTemplate(){
		$totalplotsinrow = floor(256/($this->config['PlotSize'] + 2 + $this->config['RoadSize']));
		$totalplotblocksrow = $totalplotsinrow * ($this->config['PlotSize'] + 2 + $this->config['RoadSize']);
		$i = 1;
		for($z = 1; $z <= $totalplotblocksrow;){
			for($x = 1; $x <= $totalplotblocksrow;){
				$plots[$i]['pos1'][0] = $x+1;
				$plots[$i]['pos1'][1] = $z+1;
				$plots[$i]['pos2'][0] = $x + $this->config['PlotSize'];
				$plots[$i]['pos2'][1] = $z + $this->config['PlotSize'];
				$x = ($x + 2 + $this->config['PlotSize'] + $this->config['RoadSize']);
				$i++;
			}
			$z = ($z + 2 + $this->config['PlotSize'] + $this->config['RoadSize']);
		}
		$this->plottemplate = $plots;
	}
	
	public function command($cmd, $args, $issuer){
		$iusername = $issuer->iusername;
		$output = '';
		switch($args[0]){
			case 'newworld':
				if(!($issuer instanceof Player)){
					//$thread = new CreatePlotWorld($this->numberofworlds, $this->yblocks, $this->config, $this->plottemplate, $this->api);
					//$thread->start();
					$this->createPlotWorld();
				}else{
					$output = "You can only use this command in the console";
				}
				break;
				
			case 'claim':
				$x = $issuer->entity->x;
				$z = $issuer->entity->z;
				$level = $issuer->level->getName();
				$plot = $this->getPlotByPos($x, $z, $level);
				if($plot === false){
					$output = "You need to stand in a plot";
					break;
				}
				if($plot['owner'] === NULL){
					$output = "This plot is already claimed by somebody";
					break;
				}
				$sql = $this->database->prepare("UPDATE plots SET owner = :owner WHERE id = :id");
				$sql->bindValue(':owner', $iusername, PDO::PARAM_STR);
				$sql->bindValue(':id', $plot['id'], PDO::PARAM_INT);
				$sql->execute();
				$this->tpToPlot($plot, $issuer);
				$output = 'You are now the owner of this plot with id: '.$plot['pid'].' in world: '.$level;
				break;
				
			case 'home':
				if(isset($args[1])){
					if(!is_numeric($args[1])){
						$output = 'Usage /plot home <optional-id>';
						break;
					}
					$id = $args[1] - 1;
				}else{
					$id = 0;
				}
				$plot = $this->getPlotByOwner($iusername);
				if($plot === false){
					$output = "You don't have a plot, create one with /plot auto or /plot claim";
					break;
				}elseif(!isset($plot[$id])){
					$output = "The id isn't right. You don't have so many plots.";
					break;
				}
				$this->tpToPlot($plot[$id], $issuer);
				$output = 'You have been teleported to your plot with id: '.$plot[$id]['pid'].' and in the world: '.$plot[$id]['level'];
				break;
				
			case 'auto':
				$sql = $this->database->prepare("SELECT * FROM plots WHERE owner IS NULL");
				$sql->execute();
				$plot = $sql->fetch(PDO::FETCH_ASSOC);
				if($plot === false){
					$output = 'Their are no available plots anymore';
					break;
				}
				$sql = $this->database->prepare("UPDATE plots SET owner = :owner WHERE id = :id");
				$sql->bindValue(':owner', $iusername, PDO::PARAM_STR);
				$sql->bindValue(':id', $plot['id'], PDO::PARAM_INT);
				$sql->execute();
				$this->tpToPlot($plot, $issuer);
				$output = 'You auto-claimed a plot with id:'.$plot['pid'].' in world:'.$plot['level'];
				break;
				
			case 'list':
				$plots = $this->getPlotByOwner($iusername);
				if($plots === false){
					$output = "You don't have a plot, create one with /plot auto or /plot claim";
					break;
				}
				$output = "==========[Your Plots]========== \n";
				foreach($plots as $key => $val){
					$output .= ($key + 1).'. id:'.$val['pid'].' world:'.$val['level']."\n";
				}
				break;
				
			case 'info':
				$plot = $this->getPlotByPos($issuer->entity->x, $issuer->entity->z, $issuer->level->getName());
				if($plot === false){
					$output = 'You need to stand in a plot';
					break;
				}
				if(!isset($plot['helpers'])) $plot['helpers'] = 'none';
				$output = "==========[Plot Info]==========\n";
				$output .= 'Id: '.$plot['id'].' Owner: '.$plot['owner']."\n";
				//$output .= 'Finished: '.$plot['done'].' Expire date: '.$plot['expiredate']."\n";
				$output .= 'Helpers: '.$plot['helpers'];
				break;
				
				
			case 'add':
				if(!isset($args[1])){
					$output = 'Usage: /plot add <player>';
					break;
				}
				$player = strtolower($args[1]);
				$plot = $this->getPlotByPos($issuer->entity->x, $issuer->entity->z, $issuer->level->getName());
				if($plot === false){
					$output = 'You need to stand in a plot';
					break;
				}
				if($plot['owner'] != $iusername){
					$output = "You're not the owner of this plot";
					break;
				}
				$helpers = explode(',',$plot['helpers']);
				if(in_array($player, $helpers)){
					$output = $player.' was already a helper of this plot';
					break;
				}
				array_push($helpers, $player);
				$helpers = implode(',', $helpers);
				$sql = $this->database->prepare("UPDATE plots SET helpers = :helpers WHERE id = :id");
				$sql->bindValue(':helpers', $helpers, PDO::PARAM_STR);
				$sql->bindValue(':id', $plot['id'], PDO::PARAM_INT);
				$sql->execute();
				$output = $player.' is now a helper of this plot';
				break;
				
			case 'remove':
				if(!isset($args[1])){
					$output = 'Usage: /plot remove <player>';
					break;
				}
				$player = strtolower($args[1]);
				$plot = $this->getPlotByPos($issuer->entity->x, $issuer->entity->z, $issuer->level->getName());
				if($plot === false){
					$output = 'You need to stand in a plot';
					break;
				}
				if($plot['owner'] != $iusername){
					$output = "You're not the owner of this plot";
					break;
				}
				$helpers = explode(',',$plot['helpers']);
				$key = array_search($player, $helpers);
				if($key === false){
					$output = $player.' is no helper of your plot';
					break;
				}
				unset($helpers[$key]);
				$helpers = implode(',', $helpers);
				$sql = $this->database->prepare("UPDATE plots SET helpers = :helpers WHERE id = :id");
				$sql->bindValue(':helpers', $helpers, PDO::PARAM_STR);
				$sql->bindValue(':id', $plot['id'], PDO::PARAM_INT);
				$sql->execute();
				$output = $player.' is removed as a helper from this plot';
				break;
			
			case 'clear':
			case 'reset':
				$plot = $this->getPlotByPos($issuer->entity->x, $issuer->entity->z, $issuer->level->getName());
				if($plot === false){
					$output = 'You need to stand in a plot';
					break;
				}
				if($plot['owner'] != $iusername){
					$output = "You're not the owner of this plot";
					break;
				}
				$this->resetplot($plot);
				if($args[0] === 'clear'){
					$output = 'Plot cleared!';
				}else{
					$sql = $this->database->prepare("UPDATE plots SET owner = NULL WHERE id = :id");
					$sql->bindValue(':id', $plot['id'], PDO::PARAM_INT);
					$sql->execute();
					$output = 'Plot deleted';
				}
				break;

			case 'comment':
				if(!isset($args[1])){
					$output = 'Usage: /plot command <message>';
					break;
				}
				array_shift($args);
				$message = implode(' ', $args);
				$plot = $this->getPlotByPos($issuer->entity->x, $issuer->entity->z, $issuer->level->getName());
				if($plot === false){
					$output = 'You need to stand in a plot';
					break;
				}
				$sql = $this->database->prepare("INSERT INTO comments (pid, writer, message) VALUES (:pid, :writer, :message)");
				$sql->bindValue(':pid', $plot['id'], PDO::PARAM_INT);
				$sql->bindValue(':writer', $iusername, PDO::PARAM_STR);
				$sql->bindValue(':message', $message, PDO::PARAM_STR);
				$sql->execute();
				$output = 'Comment added';
				break;
				
			case 'comments':
				$plot = $this->getPlotByPos($issuer->entity->x, $issuer->entity->z, $issuer->level->getName());
				if($plot === false){
					$output = 'You need to stand in a plot';
					break;
				}
				$sql = $this->database->prepare("SELECT * FROM comments WHERE pid = :pid");
				$sql->bindValue(':pid', $plot['id'], PDO::PARAM_INT);
				$sql->execute();
				$result = $sql->fetchAll();
				if(empty($result)){
					$output = 'No comments in this plot';
					break;
				}
				$output = "==========[Comments]==========\n";
				foreach($result as $key => $comment){
					$output .= $comment['writer'].': '.$comment['message']."\n";
				}
				break;
				
			default:
				$output = 'PlotPe v1.0 made by Wies';
				break;
		}
		return $output;
	}
	
	public function resetplot($plot){
		$level = $this->api->level->get($plot['level']);
		for($x = $plot['x1']; $x == $plot['x2']; $x++){
			for($z = $plot['z1']; $z == $plot['z2']; $z++){
				for($y = 0; $y < 128; $y++){
					$shape = $this->shape[$z][$x];
					$level->setBlockRaw(new Vector3($x,$y,$z), BlockAPI::get($this->yblocks[0][$y], 0), false, false);
				}
			}
		}
	}
	
	public function tpToPlot($plot, $player){
		$middle = ceil($plot['x2'] - $plot['x1']) / 2;
		$x = $plot['x1'] + $middle;
		$z = $plot['z1'] + $middle;
		$level = $this->api->level->get($plot['level']);
		$player->teleport(new Position($x, 27, $z, $level));
	}
	
	public function getPlotByOwner($username){
		$sql = $this->database->prepare("SELECT * FROM plots WHERE owner = :owner");
		$sql->bindValue(':owner', $username, PDO::PARAM_STR);
		$sql->execute();
		$plots = $sql->fetchAll();
		if(empty($plots)) return false;
		return $plots;
	}
	
	public function getPlotByPos($x, $z, $level){
		$sql = $this->database->prepare("SELECT * FROM plots WHERE x1 <= :x AND x2 >= :x AND z1 <= :z AND z2 >= :z AND level = :level");
		$sql->bindValue(':x', $x, PDO::PARAM_INT);
		$sql->bindValue(':z', $z, PDO::PARAM_INT);
		$sql->bindValue(':level', $level, PDO::PARAM_STR);
		$sql->execute();
		$plot = $sql->fetch(PDO::FETCH_ASSOC);
		if(empty($plot)) return false;
		return $plot;
	}
	
	public function block($data){
		$level = $data['player']->level->getName();
		if(substr($level, 0, 9) === 'plotworld'){
			if($this->api->ban->isOp($data['player']->username)){
				$iusername = $data['player']->iusername;
				$plot = $this->getPlotByPos($data['target']->x, $data['target']->z, $data['target']->level->getName());
				if(($plot === false) or ($plot['owner'] !== $iusername) or (!in_array($iusername, explode(',',$plot['helpers'])))){
					$data['player']->sendChat("You can't build in this plot");
					return false;
				}
			}
		}
	}
	
	public function createPlotWorld(){
		console('generating level');
		$this->numberofworlds++;
		$this->api->level->generateLevel('plotworld'.($this->numberofworlds), false, false, "flat");
		console('loading level');
		$this->api->level->loadLevel('plotworld'.($this->numberofworlds));
		$level = $this->api->level->get('plotworld'.($this->numberofworlds));
		console('creating plots this takes about 5 minutes so be patient');
		$progressteps = 0;
		for($z = 0; $z < 256; $z++){
			for($x = 0; $x < 256; $x++){
				for($y = 0; $y < 128; $y++){
					$shape = $this->shape[$z][$x];
					$level->setBlockRaw(new Vector3($x,$y,$z), BlockAPI::get($this->yblocks[$shape][$y], 0), false, false);
				}
			}
			if($progressteps === 10){
				console('creating plots '.ceil(($z/256)*100).'%');
				$progressteps = 0;
			}else{
				$progressteps++;
			}
		}
		$totalplotsinrow = floor(256/($this->config['PlotSize'] + 2 + $this->config['RoadSize']));
		$totalplotblocksrow = $totalplotsinrow * ($this->config['PlotSize'] + 2 + $this->config['RoadSize']);
		$middle = $totalplotblocksrow/2;
		$level->setSpawn(new Vector3($middle,27,$middle));
		unset($level);
		console('creating plotdata');
		$level = 'plotworld'.$this->numberofworlds;
		$sql = $this->database->prepare("INSERT INTO plots (pid, x1, z1, x2, z2, level) VALUES (?, ?, ?, ?, ?, ?);");
		foreach($this->plottemplate as $key => $val){
			$sql->execute(array($key, $val['pos1'][0], $val['pos1'][1], $val['pos2'][0], $val['pos2'][1], $level));
		}
		console('plot generated succesfully!');
	}
	
	public function __destruct(){
		unset($this->database);
	}
}

/*
class CreatePlotWorld extends Thread{
	public function __construct($numberofworlds, $yblocks, $config, $plottemplate, $api){
		$this->numberofworlds = $numberofworlds;
		$this->yblocks = $yblocks;
		$this->config = $config;
		$this->plottemplate = $plottemplate;
		$this->api = $api;
	}
	
	public function run(){
		console('generating level');
		$this->numberofworlds++;
		$this->api->level->generateLevel('plotworld'.($this->numberofworlds), false, false, "flat");
		console('loading level');
		$this->api->level->loadLevel('plotworld'.($this->numberofworlds));
		$level = $this->api->level->get('plotworld'.($this->numberofworlds));
		console('creating plots this takes about 5 minutes so be patient');
		$progressteps = 0;
		for($z = 0; $z < 256; $z++){
			for($x = 0; $x < 256; $x++){
				for($y = 0; $y < 128; $y++){
					$shape = $this->shape[$z][$x];
					$level->setBlockRaw(new Vector3($x,$y,$z), $this->api->block->get($this->yblocks[$shape][$y], 0), false, false);
				}
			}
			if($progressteps === 10){
				console('creating plots '.ceil(($z/256)*100).'%');
				$progressteps = 0;
			}else{
				$progressteps++;
			}
		}
		$totalplotsinrow = floor(256/($this->config['PlotSize'] + 2 + $this->config['RoadSize']));
		$totalplotblocksrow = $totalplotsinrow * ($this->config['PlotSize'] + 2 + $this->config['RoadSize']);
		$middle = $totalplotblocksrow/2;
		$level->setSpawn(new Vector3($middle,27,$middle));
		unset($level);
		console('creating plotdata');
		$level = 'plotworld'.($this->numberofworlds);
		$sql = $this->database->prepare("INSERT INTO plots (pid, x1, z1, x2, z2, level) VALUES (:pid, :x1, :z1, :x2, :z2, :level);");
		foreach($this->plottemplate as $key => $val){
			$sql->bindValue(':pid', $key);
			$sql->bindValue(':x1', $val['pos1'][0]);
			$sql->bindValue(':z1', $val['pos1'][1]);
			$sql->bindValue(':x2', $val['pos2'][0]);
			$sql->bindValue(':z2', $val['pos2'][1]);
			$sql->bindValue(':level', $level);
			$sql->execute();
		}
		$sql->close();
		console('plot generated succesfully!');
	}
}
*/


?>