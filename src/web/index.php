<?php

require_once('config.inc.php');

// Set up autoloader for external classes

function autoload($className)
{
	$className = ltrim($className, '\\');
	$fileName  = '';
	$namespace = '';
	if ($lastNsPos = strrpos($className, '\\')) {
		$namespace = substr($className, 0, $lastNsPos);
		$className = substr($className, $lastNsPos + 1);
		$fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
	}
	$fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

	require $fileName;
}

spl_autoload_register('autoload');

use Symfony\Component\Yaml\Yaml;

function getConfigFile($name) {
	global $magicRootFolder;
	$configFile = "$magicRootFolder/$name.yml";
	if (!file_exists($configFile)) {
		$configFile = "$magicRootFolder/$name.defaults.yml";
	}
	if (!file_exists($configFile)) {
		die('Could not locate file: ' . $configFile);
	}
	return $configFile;
}

// Load and parse Magic configuration files
try {
	$spellsConfiguration = Yaml::parse(getConfigFile('spells'));
	$magicConfiguratiom = Yaml::parse(getConfigFile('magic'));
	$wandConfiguratiom = Yaml::parse(getConfigFile('wands'));
	$messagesConfiguratiom = Yaml::parse(getConfigFile('messages'));
} catch (Exception $ex) {
	die($ex->getMessage());
}

$messages = array();
if (isset($messagesConfiguratiom['messages'])) {
	$messages = $messagesConfiguratiom['messages'];
}

$spells = array();
if (isset($spellsConfiguration['spells'])) {
	$spells = $spellsConfiguration['spells'];
}
ksort($spells);

// Look up localizations
foreach ($spells as $key => $spell) {
	$spell['name'] = isset($messages['spells'][$key]['name']) ? $messages['spells'][$key]['name'] : '';
	$spell['description'] = isset($messages['spells'][$key]['description']) ? $messages['spells'][$key]['description'] : '';
	$spell['usage'] = isset($messages['spells'][$key]['usage']) ? $messages['spells'][$key]['usage'] : '';
	$spells[$key] = $spell;
}

$wands = array();
if (isset($wandConfiguratiom['wands'])) {
	$wands = $wandConfiguratiom['wands'];
}
foreach ($wands as $key => $wand) {
	$wand['name'] = isset($messages['wands'][$key]['name']) ? $messages['wands'][$key]['name'] : '';
	$wand['description'] = isset($messages['wands'][$key]['description']) ? $messages['wands'][$key]['description'] : '';
	$wand['spells'] = isset($wand['spells']) ? $wand['spells'] : array();
	$wands[$key] = $wand;
}
ksort($wands);

// Look for important config options
$general = array();

if (isset($magicConfiguratiom['general'])) {
	$general = $magicConfiguratiom['general'];
}
$enchantingEnabled = isset($general['enable_enchanting']) ? $general['enable_enchanting'] : false;
$combiningEnabled = isset($general['enable_combining']) ? $general['enable_combining'] : false;
$blockPopulatorEnabled = isset($general['enable_block_populator']) ? $general['enable_block_populator'] : false;

$craftingMaterialUpper = isset($general['crafting_material_upper']) ? $general['crafting_material_upper'] : '';
$craftingMaterialLower = isset($general['crafting_material_lower']) ? $general['crafting_material_lower'] : '';
$craftingEnabled = isset($general['enable_crafting']) ? $general['enable_crafting'] : false;
$rightClickCycles = isset($general['right_click_cycles']) ? $general['right_click_cycles'] : false;

$eraseMaterial = isset($general['erase_item']) ? $general['erase_item'] : 'sulphur';
$copyMaterial = isset($general['copy_item']) ? $general['copy_item'] : 'sugar';

function underscoreToReadable($s) {
	if (!$s) return $s;
	$convertFunction = create_function('$c', 'return " " . strtoupper($c[1]);');
	return strtoupper($s[0]).  preg_replace_callback('/_([a-z])/', $convertFunction, substr($s, 1));
}

function printMaterial($materialKey, $iconOnly = null) {
	$materialName = underscoreToReadable($materialKey);
	$imagePath = 'image/material';
	$imageDir = dirname(__FILE__) . '/' . $imagePath;
	$materialIcon = str_replace('_', '', $materialKey) . '_icon32.png';
	$materialFilename = $imageDir . '/' . $materialIcon;
	if (file_exists($materialFilename)) {
		return $icon = '<span title="' . $materialName . '" class="materal_icon" style="background-image: url(' . $imagePath . '/' . $materialIcon . ')">&nbsp;</span>';
	} else {
		if ($iconOnly) {
			return '<span title="' . $materialName . '" class="materal_icon">&nbsp;</span>';
		}
	}
	return '<span class="material">' . $materialName . '</span>';
}

?>
<html>
	<head>
		<title><?= $title ?></title>
		<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
		<link rel="stylesheet" href="css/smoothness/jquery-ui-1.10.3.custom.min.css" />
		<link rel="stylesheet" href="css/magic.css" />
		<script src="js/jquery-1.10.2.min.js"></script>
		<script src="js/jquery-ui-1.10.3.custom.min.js"></script>
		<script>
			var spells = <?= json_encode($spells); ?>;
			var wands = <?= json_encode($wands); ?>;
			var eraseMaterial = '<?= $eraseMaterial ?>';
			var copyMaterial = '<?= $copyMaterial ?>';
		</script>
		<script src="js/magic.js"></script>
		<?php if ($analytics) echo $analytics; ?>
	</head>
	<body>
		<div id="heading"><?= $pageOverview ?></div>
		<div id="tabs">
			<ul>
				<li><a href="#overview">Overview</a></li>
				<li><a href="#spells">Spells</a></li>
				<li><a href="#wands">Wands</a></li>
			</ul>
			<div id="overview">
			  <div class="scrollingTab">
				<h2>Obtaining a Wand</h2>
				<div>
				In order to cast spells, you must find a wand. Each wand is unique and knows one or more spells. Wands can also be imbued with
				special properties and materials. Players do not "know" spells- if you lose your wand, you have no magic.<br/><br/>
				You may find a wand in one of the following ways:
				<ul>
					<?php if ($howToGetWands) {
						foreach ($howToGetWands as $item) {
							echo "<li>$item</li>"; 
						}
					}?>
					<?php if ($craftingEnabled) {
						echo '<li>Craft a wand with ' . printMaterial($craftingMaterialUpper) . ' and ' . 
								printMaterial($craftingMaterialLower);
						echo '</li>'; 
					}?>
					<?php if ($blockPopulatorEnabled) echo "<li>Find in random chests (dungeons, fortresses, etc)</li>"; ?>
				</ul>
				</div>
				<?php 
				if ($enchantingEnabled) {
					?>
					<div>You may upgrade your wands on an enchanting table.</div>
				<?php
				} ?>
				<?php 
				if ($combiningEnabled) {
					?>
					<div>You may combine two wands on an anvil (WIP).</div>
				<?php 
				} ?>
				<h2>Using a Wand</h2>
				<div>
					A wand is considered "active" when you are holding it. Any special effects a wand gives are only applied while the wand is active.<br.>
					<br/><br/>
					Swing a wand (left-click) to cast its active spell. Some wands may have more than one spell. If a wand has multiple spells, you use the
					interact (right-click) action to select spells.
					<br/><br/>
					<?php if ($rightClickCycles)  { ?>
						Right-click with your wand to cycle to the next spell.
					<?php } else {?>
						Right-click with your wand to toggle the wand inventory. When the wand's inventory is active, your survival items are stored
						and your player's inventory will change to show the spells and materials bound to your active wand:
						<br/><br/>
						<img src="image/WandHotbar.png" alt="Wand hotbar image"></img>
						<br/><br/>
						With the wand inventory active, each spell is represented by an icon. You can quickly change spells using the hotbar buttons (1-9).
						<br/><br/>
						You can also open your inventory ('E' by default) to see all of the spells and materials your wand has, with detailed descriptions:
						<br/><br/>
						<img src="image/WandInventory.png" alt="Wand inventory image"></img>
						<br/><br/>
						While in this view, you can re-arrange your spells and materials, deciding which ones to put in the hotbar.
						<br/><br/>
						Right-click again to deactive the wand inventory and restore your items. Any items you
						collected while the wand inventory was active will be in your surivival inventory.
						<br/><br/>
						For wands with more than 35 spells, right-clicking an additional time will cycle to the next "page" of spells. You may also
						right-click on an item in the inventory to cycle the inventory page. Renaming a wand on an anvil will also organize its inventory,
						should it get too cluttered.
						<br/><br/>
						A spell or material can be quick-selected from an open wand inventory using shift+click.
						<br/><br/>
						For detailed instructions, see this video: (TODO: Updated Video!)<br/><br/>
						<iframe width="640" height="360" src="//www.youtube.com/embed/<?= $youTubeVideo ?>" frameborder="0" allowfullscreen></iframe>
					<?php } ?>
				</div>
				<h2>Costs</h2>
				<div>
					Casting costs vary by spell, wand, and server configuration.<br/><br/>
					The most common setup is the "mana" system. In this mode, each wand has a mana pool that 
					regenerates over time. While a wand is active, your mana is represented by the XP bar. (Your gathered XP will
					be saved and restored when the wand deactivates).<br/><br/>
					Other configurations could range from consuming actual XP, reagent items, or just being free.
					<br/><br/>
					Some wands may also have a limited number of uses, after which time they will self-destruct.
				</div>
			  </div>
			</div>
			<div id="spells">
			  <div class="scrollingTab">
			  	<div class="navigation">
				<ol id="spellList">
				<?php 
					foreach ($spells as $key => $spell) {
						echo '<li class="ui-widget-content" id="spell-' . $key . '">' . printMaterial($spell['icon'], true) . '<span class="spellTitle">' . $spell['name'] . '</span></li>';
					}
				?>
				</ol>
			  </div>
			  </div>
			  <div class="details" id="spellDetails">
			  	Select a spell for details.
			  </div>
			</div>
			<div id="wands">
			  <div class="scrollingTab">
				<div class="navigation">
				<ol id="wandList">
				<?php 
					foreach ($wands as $key => $wand) {
						echo '<li class="ui-widget-content" id="wand-' . $key . '">' .'<span class="wandTitle">' . $wand['name'] . '</span></li>';
					}
				?>
				</ol>
			  </div>
			  </div>
			  <div class="details" id="wandDetails">
			  	Select a wand for details.
			  </div>
			</div>
		</div>
	</body>
</html>