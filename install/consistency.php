<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

if (php_sapi_name() != 'cli' || isset($_SERVER['REQUEST_METHOD']) || !isset($_SERVER['argc'])) {
	header("Status: 404 Not Found");
	header('HTTP/1.0 404 Not Found');
	$_SERVER['REDIRECT_STATUS'] = 404;
	echo "<h1>404 Not Found</h1>";
	echo "The page that you have requested could not be found.";
	exit();
}
set_time_limit(1800);

echo "[START CONSISTENCY]\n";
if (isset($argv)) {
	foreach ($argv as $arg) {
		$argList = explode('=', $arg);
		if (isset($argList[0]) && isset($argList[1])) {
			$_GET[$argList[0]] = $argList[1];
		}
	}
}

try {
	require_once dirname(__FILE__) . '/../core/php/core.inc.php';

	if (config::byKey('object:summary') == '' || !is_array(config::byKey('object:summary'))) {
		config::save('object:summary',
			array('security' => array('key' => 'security', 'name' => 'Alerte', 'calcul' => 'sum', 'icon' => '<i class="icon jeedom-alerte2"></i>', 'unit' => '', 'count' => 'binary', 'allowDisplayZero' => false),
				'motion' => array('key' => 'motion', 'name' => 'Mouvement', 'calcul' => 'sum', 'icon' => '<i class="icon jeedom-mouvement"></i>', 'unit' => '', 'count' => 'binary', 'allowDisplayZero' => false),
				'door' => array('key' => 'door', 'name' => 'Porte', 'calcul' => 'sum', 'icon' => '<i class="icon jeedom-porte-ouverte"></i>', 'unit' => '', 'count' => 'binary', 'allowDisplayZero' => false),
				'windows' => array('key' => 'windows', 'name' => 'Fenêtre', 'calcul' => 'sum', 'icon' => '<i class="icon jeedom-fenetre-ouverte"></i>', 'unit' => '', 'count' => 'binary', 'allowDisplayZero' => false),
				'shutter' => array('key' => 'shutter', 'name' => 'Volet', 'calcul' => 'sum', 'icon' => '<i class="icon jeedom-volet-ouvert"></i>', 'unit' => '', 'count' => 'binary', 'allowDisplayZero' => false),
				'light' => array('key' => 'light', 'name' => 'Lumière', 'calcul' => 'sum', 'icon' => '<i class="icon jeedom-lumiere-on"></i>', 'unit' => '', 'count' => 'binary', 'allowDisplayZero' => false),
				'outlet' => array('key' => 'outlet', 'name' => 'Prise', 'calcul' => 'sum', 'icon' => '<i class="icon jeedom-prise"></i>', 'unit' => '', 'count' => 'binary', 'allowDisplayZero' => false),
				'temperature' => array('key' => 'temperature', 'name' => 'Température', 'calcul' => 'avg', 'icon' => '<i class="icon divers-thermometer31"></i>', 'unit' => '°C', 'allowDisplayZero' => true),
				'humidity' => array('key' => 'humidity', 'name' => 'Humidité', 'calcul' => 'avg', 'icon' => '<i class="fa fa-tint"></i>', 'unit' => '%', 'allowDisplayZero' => true),
				'luminosity' => array('key' => 'luminosity', 'name' => 'Luminosité', 'calcul' => 'avg', 'icon' => '<i class="icon meteo-soleil"></i>', 'unit' => 'lx', 'allowDisplayZero' => false),
				'power' => array('key' => 'power', 'name' => 'Puissance', 'calcul' => 'sum', 'icon' => '<i class="fa fa-bolt"></i>', 'unit' => 'W', 'allowDisplayZero' => false),
			)
		);
	}

	$crons = cron::all();
	if (is_array($crons)) {
		if (class_exists('Cron\CronExpression')) {
			foreach ($crons as $cron) {
				$c = new Cron\CronExpression($cron->getSchedule(), new Cron\FieldFactory);
				try {
					if (!$c->isDue()) {
						$c->getNextRunDate();
					}
				} catch (Exception $ex) {
					echo "Suppression de  : " . $cron->getName() . ' car pas de lancement prévu';
					$cron->remove();
				}
			}
		}
	}

	$cron = cron::byClassAndFunction('jeedom', 'persist');
	if (is_object($cron)) {
		$cron->remove();
	}

	$cron = cron::byClassAndFunction('history', 'historize');
	if (is_object($cron)) {
		$cron->remove();
	}
	$cron = cron::byClassAndFunction('cmd', 'collect');
	if (is_object($cron)) {
		$cron->remove();
	}

	$cron = cron::byClassAndFunction('jeedom', 'updateSystem');
	if (is_object($cron)) {
		$cron->remove();
	}

	$cron = cron::byClassAndFunction('jeedom', 'checkAndCollect');
	if (is_object($cron)) {
		$cron->remove();
	}

	$cron = cron::byClassAndFunction('DB', 'optimize');
	if (is_object($cron)) {
		$cron->remove();
	}

	$cron = cron::byClassAndFunction('plugin', 'cronDaily');
	if (!is_object($cron)) {
		echo "Création de plugin::cronDaily\n";
		$cron = new cron();
	}
	$cron->setClass('plugin');
	$cron->setFunction('cronDaily');
	$cron->setSchedule('00 00 * * * *');
	$cron->setTimeout(240);
	$cron->setEnable(1);
	$cron->setDeamon(0);
	$cron->save();

	$cron = cron::byClassAndFunction('jeedom', 'backup');
	if (!is_object($cron)) {
		echo "Création de jeedom::backup\n";
		$cron = new cron();
	}
	$cron->setClass('jeedom');
	$cron->setFunction('backup');
	$cron->setSchedule(rand(10, 59) . ' 0' . rand(0, 7) . ' * * *');
	$cron->setEnable(1);
	$cron->setDeamon(0);
	$cron->setTimeout(60);
	$cron->save();

	$cron = cron::byClassAndFunction('plugin', 'cronHourly');
	if (!is_object($cron)) {
		echo "Création de plugin::cronHourly\n";
		$cron = new cron();
	}
	$cron->setClass('plugin');
	$cron->setFunction('cronHourly');
	$cron->setSchedule('00 * * * * *');
	$cron->setEnable(1);
	$cron->setDeamon(0);
	$cron->setTimeout(60);
	$cron->save();

	$cron = cron::byClassAndFunction('scenario', 'check');
	if (!is_object($cron)) {
		echo "Création de scenario::check\n";
		$cron = new cron();
	}
	$cron->setClass('scenario');
	$cron->setFunction('check');
	$cron->setSchedule('* * * * * *');
	$cron->setEnable(1);
	$cron->setDeamon(0);
	$cron->setTimeout(30);
	$cron->save();

	$cron = cron::byClassAndFunction('jeedom', 'cronDaily');
	if (!is_object($cron)) {
		echo "Création de jeedom::cronDaily\n";
		$cron = new cron();
	}
	$cron->setClass('jeedom');
	$cron->setFunction('cronDaily');
	$cron->setSchedule('00 00 * * * *');
	$cron->setEnable(1);
	$cron->setDeamon(0);
	$cron->setTimeout(240);
	$cron->save();

	$cron = cron::byClassAndFunction('jeedom', 'cronHourly');
	if (!is_object($cron)) {
		echo "Création de jeedom::cronHourly\n";
		$cron = new cron();
	}
	$cron->setClass('jeedom');
	$cron->setFunction('cronHourly');
	$cron->setSchedule('00 * * * * *');
	$cron->setEnable(1);
	$cron->setDeamon(0);
	$cron->setTimeout(60);
	$cron->save();

	$cron = cron::byClassAndFunction('jeedom', 'cron5');
	if (!is_object($cron)) {
		echo "Création de jeedom::cron5\n";
		$cron = new cron();
	}
	$cron->setClass('jeedom');
	$cron->setFunction('cron5');
	$cron->setSchedule('*/5 * * * * *');
	$cron->setEnable(1);
	$cron->setDeamon(0);
	$cron->setTimeout(5);
	$cron->save();

	$cron = cron::byClassAndFunction('jeedom', 'cron');
	if (!is_object($cron)) {
		echo "Création de jeedom::cron\n";
		$cron = new cron();
	}
	$cron->setClass('jeedom');
	$cron->setFunction('cron');
	$cron->setSchedule('* * * * * *');
	$cron->setTimeout(2);
	$cron->setDeamon(0);
	$cron->save();

	$cron = cron::byClassAndFunction('plugin', 'cron');
	if (!is_object($cron)) {
		echo "Création de plugin::cron\n";
		$cron = new cron();
	}
	$cron->setClass('plugin');
	$cron->setFunction('cron');
	$cron->setSchedule('* * * * * *');
	$cron->setTimeout(2);
	$cron->setDeamon(0);
	$cron->save();

	$cron = cron::byClassAndFunction('plugin', 'cron5');
	if (!is_object($cron)) {
		echo "Création de plugin::cron5\n";
		$cron = new cron();
	}
	$cron->setClass('plugin');
	$cron->setFunction('cron5');
	$cron->setSchedule('*/5 * * * * *');
	$cron->setTimeout(5);
	$cron->setDeamon(0);
	$cron->save();

	$cron = cron::byClassAndFunction('plugin', 'cron15');
	if (!is_object($cron)) {
		echo "Création de plugin::cron15\n";
		$cron = new cron();
	}
	$cron->setClass('plugin');
	$cron->setFunction('cron15');
	$cron->setSchedule('*/15 * * * * *');
	$cron->setTimeout(15);
	$cron->setDeamon(0);
	$cron->save();

	$cron = cron::byClassAndFunction('plugin', 'cron30');
	if (!is_object($cron)) {
		echo "Création de plugin::cron30\n";
		$cron = new cron();
	}
	$cron->setClass('plugin');
	$cron->setFunction('cron30');
	$cron->setSchedule('*/30 * * * * *');
	$cron->setTimeout(30);
	$cron->setDeamon(0);
	$cron->save();

	$cron = cron::byClassAndFunction('plugin', 'checkDeamon');
	if (!is_object($cron)) {
		echo "Création de plugin::checkDeamon\n";
		$cron = new cron();
	}
	$cron->setClass('plugin');
	$cron->setFunction('checkDeamon');
	$cron->setSchedule('*/5 * * * * *');
	$cron->setTimeout(5);
	$cron->setDeamon(0);
	$cron->save();

	$cron = cron::byClassAndFunction('cache', 'persist');
	if (!is_object($cron)) {
		echo "Création de cache::persist\n";
		$cron = new cron();
	}
	$cron->setClass('cache');
	$cron->setFunction('persist');
	$cron->setSchedule('*/30 * * * * *');
	$cron->setTimeout(30);
	$cron->setDeamon(0);
	$cron->save();

	$cron = cron::byClassAndFunction('history', 'archive');
	if (!is_object($cron)) {
		echo "Création de history::archive\n";
		$cron = new cron();
	}
	$cron->setClass('history');
	$cron->setFunction('archive');
	$cron->setSchedule('00 5 * * * *');
	$cron->setTimeout(240);
	$cron->setDeamon(0);
	$cron->save();

	if (!file_exists(dirname(__FILE__) . '/../plugins')) {
		mkdir(dirname(__FILE__) . '/../plugins');
	}
	try {
		echo 'Check jeedom file right...';
		jeedom::cleanFileSytemRight();
		echo "OK\n";
	} catch (Exception $e) {
		echo "NOK\n";
	}

	config::save('hardware_name', '');
	if (config::byKey('api') == '') {
		config::save('api', config::genKey());
	}
	if (file_exists(dirname(__FILE__) . '/../../core/nodeJS')) {
		shell_exec(system::getCmdSudo() . 'rm -rf ' . dirname(__FILE__) . '/../../core/nodeJS');
	}
	if (file_exists(dirname(__FILE__) . '/../../script/ngrok')) {
		shell_exec(system::getCmdSudo() . 'rm -rf ' . dirname(__FILE__) . '/../../script/ngrok');
	}
	try {
		foreach (eqLogic::all() as $eqLogic) {
			$eqLogic->emptyCacheWidget();
		}
	} catch (Exception $e) {

	}

	try {
		foreach (object::all() as $object) {
			$object->save();
		}
	} catch (Exception $exc) {

	}

	foreach (cmd::all() as $cmd) {
		$change = false;
		if ($cmd->getConfiguration('jeedomCheckCmdCmdActionId') != '') {
			$cmd->setConfiguration('jeedomCheckCmdCmdActionId', '');
			$change = true;
		}
		if ($change) {
			$cmd->save();
		}
	}
} catch (Exception $e) {
	echo "Error : ";
	echo $e->getMessage();
}
echo "[END CONSISTENCY]\n";
