<?php

/*
__PocketMine Plugin__
name=PlotPe
description=PlotMe ported
version=1.0
author=wies
class=Plot
apiversion=10
*/
		
class Plot implements Plugin{
	private $api;
	private $database;
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
		$this->database = new SQLite3($this->api->plugin->configPath($this) . 'PlotMe.sqlite3');
		$sql = $this->database->prepare(
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
		$sql->execute();
		$sql->close();
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
				$width = 0;
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
				$width = 0;
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
				$width = 0;
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
		$totalplots = pow($totalplotsinrow, 2);
		$i = 1;
		for($z = 1; $z <= $totalplotblocksrow;){
			for($x = 1; $x <= $totalplotblocksrow;){
				$plots[$i]['pos1'][0] = $x;
				$plots[$i]['pos1'][1] = $z;
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
					$this->CreateNewWorld();
				}else{
					$output = "You can only use this command in the console";
				}
				break;
				
			case 'test':
				$plot = $this->getPlotByPos(2, 2, 'plotworld1');
				$plot = $plot[0];
				$output = 'id: '.$plot['id'].' pid:'.$plot['pid'].' level:'.$plot['level'];
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
				$plot = $plot[0];
				if($plot['owner'] != NULL){
					$output = "This plot is already claimed by somebody";
					break;
				}
				$sql = $this->database->prepare("UPDATE plots SET owner = ? WHERE id = ?");
				$sql->execute(array($iusername, $plot['id']));
				$sql->close();
				$this->tpToPlot($plot, $issuer);
				$output = 'You are now the owner of this plot with id: '.$plot['pid'].' in world: '.$level;
				break;
				
			case 'home':
				$plot = $this->getPlotByOwner($iusername);
				if($plot === false){
					$output = "You don't have a plot, create one with /plot auto or /plot claim";
					break;
				}
				$id = 0;
				if(isset($args[1]) and is_numeric($args[1])){
					$id = $args[1] - 1;
				}
				$this->tpToPlot($plot[$id], $issuer);
				$output = 'You have been teleported to your plot with id:'.($id + 1);
				break;
				
			case 'auto':
				$sql = $this->database->prepare("SELECT * FROM plots WHERE owner IS NULL");
				$result = $sql->execute()->fetchAll(SQLITE3_ASSOC);
				$sql->close();
				if(!isset($result[0])){
					$output = 'Their are no available plots anymore';
					break;
				}
				$sql = $this->database->prepare("UPDATE plots SET owner = ? WHERE id = ?");
				$sql->execute(array($iusername, $result[0]['id']));
				$sql->close();
				$this->tpToPlot($result[0], $issuer);
				$output = 'You auto-claimed a plot with id:'.$result[0]['pid'].' in world:'.$result[0]['level'];
				break;
				
			case 'list':
				$plots = $this->getPlotByOwner($iusername);
				if($plots === false){
					$output = "You don't have a plot, create one with /plot auto or /plot claim";
					break;
				}
				$output = '==========[Your Plots]==========';
				foreach($plots as $key => $val){
					$output .= ' '.($key + 1).'. id:'.$val['pid'].' world:'.$val['level']."\n";
				}
				break;
				
			case 'add':
				if(!isset($args[1])){
					$output = 'Usage: /plot add <player>';
					break;
				}
				$player = strtolower($args[0]);
				$plot = $this->getPlotByPos($issuer->entity->x, $issuer->entity->z, $issuer->level->getName());
				if($plot === false){
					$output = 'You need to stand in a plot';
					break;
				}
				$plot = $plot[0];
				if($plot['owner'] != $iusername){
					$output = "You're not the owner of this plot";
					break;
				}
				if($plot['helpers'] == NULL){
					$helpers = $player;
				}else{
					if(in_array($player, explode(',',$plot['helpers']))){
						$output = $player.' was already a helper of this plot';
						break;
					}
					$helpers = $plot['helpers'].','.$player;
				}
				$sql = $this->database->prepare("UPDATE plots SET helpers = ? WHERE id = ?");
				$sql->execute(array($helpers, $plot['id']));
				$sql->close();
				$output = $player.' is now a helper of this plot';
				break;
				
			case 'remove':
				if(!isset($args[1])){
					$output = 'Usage: /plot remove <player>';
					break;
				}
				$player = strtolower($args[0]);
				$plot = $this->getPlotByPos($issuer->entity->x, $issuer->entity->z, $issuer->level->getName());
				if($plot === false){
					$output = 'You need to stand in a plot';
					break;
				}
				$plot = $plot[0];
				if($plot['owner'] != $iusername){
					$output = "You're not the owner of this plot";
					break;
				}
				$helpers = explode(',',$plot['helpers']);
				if($key = array_search($player, $helpers)){
					unset($helpers[$key]);
					$helpers = implode(',', $helpers);
					$sql = $this->database->prepare("UPDATE plots SET helpers = ? WHERE id = ?");
					$sql->execute(array($helpers, $plot['id']));
					$sql->close();
					$output = $player.' is no helper of this plot anymore';
				}else{
					$output = $player.' is no helper of this plot';
				}
				break;
			default:
				return false;
				break;
		}
		return $output;
	}
	
	public function tpToPlot($plot, $player){
		$middle = ceil($plot['x2'] - $plot['x1']) / 2;
		$x = $plot['x1'] + $middle;
		$z = $plot['z1'] + $middle;
		$level = $this->api->level->get($plot['level']);
		$player->teleport(new Position($x, 27, $z, $level));
	}
	
	public function CreateNewWorld(){
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
		$level = 'plotworld'.($this->numberofworlds);
		$sql = $this->database->prepare("INSERT INTO plots (pid, x1, z1, x2, z2, level) VALUES (?, ?, ?, ?, ?, ?);");
		foreach($this->plottemplate as $key => $val){
			$sql->execute(array($key, $val['pos1'][0], $val['pos1'][1], $val['pos2'][0], $val['pos2'][1], $level));
		}
		$sql->close();
		console('plot generated succesfully!');
	}
	
	public function getPlotByOwner($username){
		$sql = $this->database->prepare("SELECT * FROM plots WHERE owner = ?");
		$result = $sql->execute(array($username))->fetchAll(SQLITE3_ASSOC);
		$sql->close();
		$i = 0;
		if(!isset($result[0])){
			$result = false;
		}
		return $result;
	}
	
	public function getPlotByPos($x, $z, $level){
		$sql = $this->database->prepare("SELECT * FROM plots WHERE x1 <= ? AND x2 <= ? AND z1 <= ? AND z2 <= ? AND level = ?");
		$result = $sql->execute(array($x, $x, $z, $z, $level))->fetchAll(SQLITE3_ASSOC);
		$sql->close();
		if(!isset($result[0])){
			$result = false;
		}
		return $result;
	}
	
	public function block($data){
		if($this->api->ban->isOp($data['player']->username)) return;
		$iusername = $data['player']->iusername;
		$plot = $this->getPlotByPos($data['target']->x, $data['target']->z, $data['target']->level->getName());
		$plot = $plot[0];
		if(($plot === false) or ($plot['owner'] == $iusername) or (in_array($iusername, explode(',',$plot['helpers'])))) return;
		$data['player']->sendChat("You can't build in this plot");
		return false;
	}
	
	public function __destruct(){
		$this->database->close();
	}

}