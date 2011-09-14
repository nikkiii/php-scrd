<?php
$args = arguments($argv);

if(isset($args['conf'])) {
	$conf = readconf($args['conf']);
} else {
	$conf = readconf("/usr/local/etc/scrd.conf");
}

$sock = socket_create(AF_INET, SOCK_STREAM, 0);
socket_bind($sock, 0, 12909) or die('Could not bind to address');
socket_listen($sock);

echo "Listening\n";

while(true) {
	$client = socket_accept($sock);
	$addr = "";
	socket_getpeername($client, $addr);
	echo "Socket opened: $addr\n";
	if(in_array($addr, $conf['white'])) {
		$resp = array();
		$resp['hostname'] = hostname();
		$resp['who'] = who();
		$resp['uplo'] = array_merge(array("uptime" => uptime()), loadavg());
		$resp['ram'] = memory();
		$resp['ips'] = ip_addresses();
		$reps['disk'] = diskusage();
		socket_write($client, json_encode($resp));
	} else {
		socket_write($client, json_encode(array("error" => "Unauthorized")));
	}
	socket_close($client);
}

socket_close($sock);

function hostname() {
	return trim(file_get_contents("/proc/sys/kernel/hostname"));
}

function who() {
	$users = array();
	exec("/usr/bin/who -q", $lines);
	foreach($lines as $line) {
		$line = trim($line);
		$split = explode(" ", $line);
		if($line[0] == "#") continue;
		$users = array_merge($users, $split);
	}
	return $users;
}

function diskusage() {
	$disks = array("single" => array(), "total" => "");
	exec("/bin/df -hTP", $lines);
	foreach($lines as $line) {
		preg_match("'(.+?)\s+([\w\-]+)\s+([\d.]+\w?)\s+([0-9.]+\w?)\s+([0-9.]+\w?)\s+(?:\d+%)\s*(.*)'", $line, $match);
		if($match[5] != '') {
			$disks['single'][] = array("fs" => $match[1], "type" => $match[2], "total" => $match[3], "used" => $match[4], "mount" => $match[6]);
		}
	}
	return $disks;
}

function ip_addresses() {
	$addresses = array();
	exec("/sbin/ifconfig | grep 'inet addr:' | grep -v '127.0.0.1' | cut -d: -f2 | awk '{print $1}'", $lines);
	foreach($lines as $line) {
		$line = trim($line);
		$addresses[] = array("ip" => $line, gethostbyaddr($line));
	}
	return $addresses;
}

function memory() {
	exec("/usr/bin/free -m", $lines);
	preg_match("'Mem:\s*(?P<total>\d+)\s*(?P<used>\d+)\s*(?P<free>\d+)\s*\d+\s*(?P<buffers>\d+)\s*(?P<cached>\d+)'", $lines[1], $match);
	return array("used" => intval($match['used']), "free" => intval($match['free']), "total" => intval($match['total']), "bufcac" => intval($match['buffers']+$match['cached']));
}

function loadavg() {
	$loads = array();
	$avg = explode(" ", file_get_contents("/proc/loadavg"));
	$loads["load1"] = doubleval($avg[0]);
	$loads["load5"] = doubleval($avg[1]);
	$loads["load15"] = doubleval($avg[2]);
	return $loads;
}

function uptime() {
	$time = explode(" ", file_get_contents("/proc/uptime"));
	return duration($time[0]);
}

function readconf($file) {
	$lines = explode("\n", file_get_contents($file));
	$conf = array();
	foreach($lines as $line) {
		$line = trim($line);
		if(empty($line) || $line[0] == "#") continue;

		if(preg_match("/(.*?)\[(.*?)\]:\s*(.*)/", $line, $match)) {
			$key = $match[1];
			if(!isset($conf[$key])) {
				$conf[$key] = array();
			}
			if(!empty($match[2])) {
				$conf[$key][$match[2]] = $match[3];
			} else {
				$conf[$key][] = $match[3];
			}
		} else {
			$key = substr($line, 0, strpos($line, ":"));
			$value = trim(substr($line, strpos($line, ":")+1));
			$conf[$key] = $value;
		}
	}
	return $conf;
}

function arguments($argv) {
	$out = array();
	foreach ($argv as $arg) {
		if (strpos($arg, '--') === 0) {
			$compspec = explode('=', $arg);
			$key = str_replace('--', '', array_shift($compspec));
			$value = join('=', $compspec);
			$out[$key] = $value;
		} elseif (strpos($arg, '-') === 0) {
			$key = str_replace('-', '', $arg);
			if (!isset($out[$key])) $out[$key] = true;
		}
	}
	return $out;
}

function duration($seconds_count) {
	$delimiter  = ':';
	$minutes = floor($seconds_count/60);
	$hours   = floor($seconds_count/3600);
	$days	 = floor($seconds_count/86400);
	if($days > 0) {
		return "$days day".($days == 1 ? "" : "s");
	} else if($hours > 0) {
		return "$hours hour".($hours == 1 ? "" : "s");
	} else if($minutes > 0) {
		return "$minutes minute".($minutes == 1 ? "" : "s");
	} else {
		return "$second_count second".($second_count == 0 ? "" : "s");
	}
}
?>