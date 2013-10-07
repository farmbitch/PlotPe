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
		$this->plotsdata = array();
		if(file_exists($this->path . 'plots.data')){
			$this->plotsdata = json_decode(file_get_contents($this->path . 'plots.data'), true);
		}
		$this->players = array();
		if(file_exists($this->path . 'players.data')){
			$this->players = json_decode(file_get_contents($this->path . 'players.data'), true);
		}
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
				$plots[$i]['pos'][0] = array($x, $z);
				$plots[$i]['pos'][1] = array($x + $this->config['PlotSize'], $z + $this->config['PlotSize']);
				$x = ($x + 2 + $this->config['PlotSize'] + $this->config['RoadSize']);
				$i++;
			}
			$z = ($z + 2 + $this->config['PlotSize'] + $this->config['RoadSize']);
		}
		foreach($plots as $key => $val){
			$plots[$key]['owner'] = false;
			$plots[$key]['members'] = array();
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
			case 'claim':
				if(isset($this->plotsdata[$issuer->level->getName()])){ 
					if(isset($this->players[$iusername])){
						$output = "You already have a plot, it's in the world:".$this->players[$iusername]['level'].' and has the plotid: '.$this->players[$iusername]['plotid']."\n";
						$output .= 'You can use: /plot home to teleport to your plot';
						break;
					}
					$x = ceil($issuer->entity->x - 0.5);
					$z = ceil($issuer->entity->z - 0.5);
					$level = $issuer->level->getName();
					$plots = $this->plotsdata[$level];
					$in_plot = false; 
					foreach($plots as $key => $val){
						if($val['pos'][0][0] <= $x and $x <= $val['pos'][1][0] and $val['pos'][1][0] <= $z and $z <= $val['pos'][1][1]){
							if($val['owner'] === false){
								$this->plotsdata[$level][$key]['owner'] = $iusername;
								$this->players[$iusername] = array('level' => $level, 'plotid' => $key);
								file_put_contents($this->path.'players.data', json_encode($this->players));
								file_put_contents($this->path.'plots.data', json_encode($this->plotsdata));
								$output = 'You are now the owner of this plot with id: '.$key.' in world: '.$level;
								break;
							}else{
								$output = 'The owner of this plot is '.$val['owner'];
								break;
							}
						}
					}
					$output = 'You need to stand in a plot';
					break;
					
				}
				$output = 'Their are no plots in this world';
				break;
			case 'home':
				if(isset($this->players[$iusername])){
					$level = $this->players[$iusername]['level'];
					$id = $this->players[$iusername]['plotid'];
					$middle = $this->config['PlotSize'] / 2;
					$x = $this->plotsdata[$level][$id]['pos'][0][0] + $middle;
					$z = $this->plotsdata[$level][$id]['pos'][0][1] + $middle;
					$issuer->teleport(new Vector3($x, 27, $z));
					$output = 'You have been teleported to your plot';
				}else{
					$output = "You don't have a plot, create one with /plot auto or /plot claim";
				}
				break;
			case 'auto':
				if(isset($this->players[$iusername])){
					$output = "You already have a plot, it's in the world:".$this->players[$iusername]['level'].' and has the plotid: '.$this->players[$iusername]['plotid']."\n";
					$output .= 'You can use: /plot home to teleport to your plot';
					break;
				}
				foreach($this->plotsdata as $key => $val){
					foreach($val as $key2 => $val2){
						if($val2['owner'] === false){
							$this->plotsdata[$key][$key2]['owner'] = $iusername;
							$this->players[$iusername] = array('level' => $key, 'plotid' => $key2);
							$middle = $this->config['PlotSize'] / 2;
							$x = $val2[$key][$key2]['pos'][0][0] + $middle;
							$z = $val2[$key][$key2]['pos'][0][1] + $middle;
							$issuer->teleport(new Vector3($x, 27, $z));
							file_put_contents($this->path.'players.data', json_encode($this->players));
							file_put_contents($this->path.'plots.data', json_encode($this->plotsdata));
							$output = 'You are now the owner of this plot with id: '.$key2.' in world: '.$key;
							return $output;
						}
					}
				}
				$output = 'Their are no available plots anymore';
				break;
			case 'addmember':
				if(!isset($args[0])){
					$output = 'Usage: /plot addmember <player>';
					break;
				}
				$player = strtolower($args[0]);
				if(!isset($this->players[$iusername])){
					$output = "You don't have a plot";
					break;
				}
				$level = $this->players[$iusername]['level'];
				$id = $this->players[$iusername]['plotid'];
				if(in_array($player, $this->plotsdata[$level][$id]['members'])){
					$output = $player.' is already a member of your plot';
					break;
				}
				array_push($player, $this->plotsdata[$level][$id]['members']);
				file_put_contents($this->path.'plots.data', json_encode($this->plotsdata));
				$output = $player.' is now a member of your plot';
				break;
			case 'removemember':
				if(!isset($args[0])){
					$output = 'Usage: /plot removemember <player>';
					break;
				}
				$player = strtolower($args[0]);
				if(!isset($this->players[$iusername])){
					$output = "You don't have a plot";
					break;
				}
				$level = $this->players[$iusername]['level'];
				$id = $this->players[$iusername]['plotid'];
				$key = array_search($player, $this->plotsdata[$level][$id]['members']);
				if($key === false){
					$output = $player." isn't a member of your plot";
					break;
				}
				unset($this->plotsdata[$level][$id]['members'][$key]);
				file_put_contents($this->path.'plots.data', json_encode($this->plotsdata));
				$output = $player.' is now a member of your plot';
				break;
			default:
				$output = "===[plotcommands]===\n";
				$output .= "/plot home - tp to your plot\n";
				$output .= "/plot auto - claim a random free plot\n";
				$output .= "/plot claim - claim the plot your standing in\n";
				$output .= "/plot addmember - add a member to your plot\n";
				$output .= "/plot removemember - remove a member from your plot\n";
				break;
		}
		return $output;
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
		$this->plotsdata['plotworld1'] = $this->plottemplate;
		$file = json_encode($this->plotsdata);
		file_put_contents($this->path.'plots.data', $file);
		console('plot generated succesfully!');
	}
	
	public function block($data){
		if(isset($this->plotsdata[$data['target']->level->getName()]) and !$this->api->ban->isOp($data['player']->username)){
			$iusername = $data['player']->iusername;
			$x = $data['target']->x;
			$z = $data['target']->z;
			$plots = $this->plotsdata[$data['target']->level->getName()];
			foreach($plots as $key => $val){
				if($val['pos'][0][0] <= $x and $x <= $val['pos'][1][0] and $val['pos'][1][0] <= $z and $z <= $val['pos'][1][1]){
					if($val['owner'] === $iusername or in_array($iusername, $plots['members'])){
						return true;
					}
				}
			}
			$data['player']->sendChat("You can't build in this plot");
			return false;
		}
	}
	
	public function __destruct(){}

}
?>