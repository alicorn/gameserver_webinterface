<?php
############################################################
# Gameserver Webinterface                                  #
# Copyright (C) 2010 Torsten Amshove <torsten@amshove.net> #
############################################################

// Bezeichnungen der admin-level
$ad_level = array(
  3 => "User",
  4 => "Admin",
  5 => "Superadmin"
);

// Mit MySQL verbinden
mysql_connect($mysql_host,$mysql_user,$mysql_pw) or die(mysql_error());
mysql_select_db($mysql_db) or die(mysql_error());

// Session starten
session_start();
if(!empty($_SESSION["user_id"])) $logged_in = true;
else $logged_in = false;

// Funktion zum auslesen der Variablen aus dem Befehl
function parse_cmd($cmd){
  $vars = array();
  while(true){
    preg_match("/(##[a-zA-Z0-9]*##)/",$cmd,$matches);
    if(!empty($matches[1])){
      if($matches[1] != "##port##"){
        $vars[] = $matches[1];
      }
      $cmd = str_replace($matches[1],"",$cmd);
    }else break;
  }
  return $vars;
}

// Funktion zum bestimmen des naechsten freien Ports
function get_port($server,$port){
  global $ssh_string;
  if(trim(shell_exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"echo 1\"")) == 1){
    for($i=0; $i<=20; $i++){
      exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"netstat -tuln | grep $port\"",$retarr,$rc);
      if($rc == 1) return $port;
      else $port++;
    }
    return false;
  }else return false;
}

// Funktion zum Testen, ob der Screen-Name existiert
function check_screen_exists($server,$screen){
  global $ssh_string;
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"ls -1 /var/run/screen/S-".$server["user"]."/ | cut -d . -f 2 | grep $screen\"",$retarr,$rc);
  if($rc == 0) return true;
  else return false;
}

// Funktion zum Starten des Gameservers
function starte_cmd($server,$cmd,$screen,$folder=""){
  global $ssh_string;
  if(check_screen_exists($server,$screen)){ // Wenn bereits ein Screen mit dem Namen vorhanden ist ...
    if($_SESSION["ad_level"] >= 4) echo "<div class='meldung_error'>Screen-Name <b>$screen</b> bereits vergeben - toter Screen? Port scheint noch nicht belegt, aber Screen vorhanden</div><br>"; // Zusaetzliche Infos fuer Admin
    return false;
  }
  if(!empty($folder)) $folder = "cd $folder && ";
  unset($retarr,$rc);
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"".$folder."screen -dmS $screen $cmd\"",$retarr,$rc);
  if($rc != 0){
    if($_SESSION["ad_level"] >= 4) echo "<div class='meldung_error'><b>ERROR:</b> <pre>"; print_r($retarr); echo "</pre></div><br>"; // Zusaetzliche Infos fuer Admin
    return false;
  }
  if(check_screen_exists($server,$screen)) return true;
  else{
    if($_SESSION["ad_level"] >= 4) echo "<div class='meldung_error'>Screen anscheinend gestorben.</div><br>"; // Zusaetzliche Infos fuer Admin
    return false;
  }
}

// Funktion zum auflisten aller laufenden Screens eines Servers
function list_screens($server){
  global $ssh_string;
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"screen -wipe\"");
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"ls -1 /var/run/screen/S-".$server["user"]."/ | cut -d . -f 2\"",$retarr,$rc);
  return $retarr;
}

// Funktion zum Beenden eines Screens
function kill_screen($server,$screen){
  global $ssh_string;
  // PID bestimmen
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"ps ax --format=#%p#%a | grep '[S]CREEN -dmS $screen' | cut -d '#' -f 2\"",$retarr,$rc);
  $pid = trim($retarr[0]);
  if(!is_numeric($pid) || empty($pid)){
    if($_SESSION["ad_level"] >= 5) echo "<div class='meldung_error'>PID nicht erkannt: $pid</div><br>"; // Zusaetzliche Infos fuer Admin
    return false;
  }
  unset($retarr,$rc);
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"kill $pid\"",$retarr,$rc); // PID killen
  if($rc != 0){
    if($_SESSION["ad_level"] >= 5) echo "<div class='meldung_error'>kill nicht erfolgreich - PID: $pid</div><br>"; // Zusaetzliche Infos fuer Admin
    return false;
  }
  unset($retarr,$rc);
  // Screen wirklich beendet worden?
  if(check_screen_exists($server,$screen)){
    if($_SESSION["ad_level"] >= 4) echo "<div class='meldung_error'>Screen-Name <b>$screen</b> l&auml;uft noch ...</div><br>"; // Zusaetzliche Infos fuer Admin
    return false;
  }
  return true;
}

// Funktion zum beenden eines Gameservers
function kill_server($running_id){
  $query = mysql_query("SELECT screen, serverid FROM running WHERE id = '".$running_id."' LIMIT 1");
  $running = mysql_fetch_assoc($query);
  $query = mysql_query("SELECT * FROM server WHERE id = '".$running["serverid"]."' LIMIT 1");
  $server = mysql_fetch_assoc($query);

  if(!kill_screen($server,$running["screen"])){
    echo "<div class='meldung_error'>Server konnte nicht gestoppt werden.</div><br>";
  }else{
    mysql_query("DELETE FROM running WHERE id = '".$running_id."' LIMIT 1");
    echo "<div class='meldung_ok'>Server gekillt.</div><br>";
  }
}

// Funktion zum restarten eines Gameservers
function restart_server($running_id){
  $query = mysql_query("SELECT * FROM running WHERE id = '".$running_id."' LIMIT 1");
  $running = mysql_fetch_assoc($query);
  $query = mysql_query("SELECT * FROM server WHERE id = '".$running["serverid"]."' LIMIT 1");
  $server = mysql_fetch_assoc($query);

  if(!kill_screen($server,$running["screen"])){
    echo "<div class='meldung_error'>Server konnte nicht gestoppt werden.</div><br>";
    return false;
  }else{
    echo "<div class='meldung_ok'>Server gekillt.</div><br>";
  }

  // Game starten
  $query = mysql_query("SELECT * FROM games WHERE id = '".$running["gameid"]."' LIMIT 1");
  $game = mysql_fetch_assoc($query);
  if(!starte_cmd($server,$running["cmd"],$running["screen"],$game["folder"])){ // Server starten ...
    echo "<div class='meldung_error'>Server konnte nicht gestartet werden.</div><br>";
  }else{
    echo "<div class='meldung_ok'>Server erfolgreich gestartet.</div><br>";
  }
}

// Funktion zum Auflisten aller Server, denen ein bestimmtes Game zugewiesen ist
function get_server_with_game($gameid){
  $query = mysql_query("SELECT * FROM server WHERE games LIKE '".$gameid."' OR games LIKE '".$gameid.",%' OR games LIKE '%,".$gameid.",%' OR games LIKE '%,".$gameid."' ORDER BY name");
  return $query;
}

// Funktion zum pruefen, ob der Login zum Server moeglich ist
function host_check_login($server){
  global $ssh_string;
  exec("$ssh_string ".$server["user"]."@".$server["ip"]." \"exit 0\"",$retarr,$rc);
  if($rc == 0) return true;
  else return false;
}

// Funktion zum pruefen, ob der Server per Ping erreichbar ist
function host_check_ping($server){
  global $ssh_string;
  exec("ping -c 1 -w 1 ".$server["ip"],$retarr,$rc);
  if($rc == 0) return true;
  else return false;
}

// Funktion um den Zugang zu einem neuen Server einzurichten
function install_access($server,$pw){
  global $ssh_pub_key, $ssh_string;
  $pw = escapeshellarg($pw);
  
  // SSH-Pub-Key auf Server kopieren
  exec("sshpass -p '$pw' scp -o StrictHostKeyChecking=no $ssh_pub_key ".$server["user"]."@".$server["ip"].":~/gswi.pub 2>&1",$retarr,$rc);
  if($rc != 0){
    echo "<div class='meldung_error'>SSH-Key konnte nicht kopiert werden:<br>";
    foreach($retarr as $line) echo $line."<br>";
    echo "</div><br>";
    return false;
  }

  // SSH-Key in authorized_key eintragen
  unset($retarr,$rc);
  exec("sshpass -p '$pw' ssh -o StrictHostKeyChecking=no ".$server["user"]."@".$server["ip"]." \"mkdir ~/.ssh > /dev/null 2>&1; cat ~/gswi.pub >> ~/.ssh/authorized_keys; rm ~/gswi.pub; sort ~/.ssh/authorized_keys | uniq > ~/.ssh/authorized_keys.new; mv ~/.ssh/authorized_keys.new ~/.ssh/authorized_keys\" 2>&1",$retarr,$rc);
  if($rc != 0){
    echo "<div class='meldung_error'>SSH-Key konnte nicht eingetragen werden:<br>";
    foreach($retarr as $line) echo $line."<br>";
    echo "</div><br>";
    return false;
  }

  // Ab hier passwortloser Login
  if(!host_check_login($server)){
    echo "<div class='meldung_error'>Login nicht m&ouml;glich - irgendwas ist falsch gelaufen ...</div><br>";
    return false;
  }

  // Screen installieren
  unset($retarr,$rc);
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"echo '$pw' | sudo -S apt-get -y install screen\" 2>&1");
  if($rc != 0){
    echo "<div class='meldung_error'>\"screen\" konnte nicht installiert werden:<br>";
    foreach($retarr as $line) echo $line."<br>";
    echo "</div><br>";
    return false;
  }

  // Shutdown-Sudo einrichten
  unset($retarr,$rc);
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"echo '".$server["user"]." ALL=(root) NOPASSWD: /sbin/shutdown' > /tmp/gameserver_wi; echo '$pw' | sudo -S chown root:root /tmp/gameserver_wi; echo '$pw' | sudo -S chmod 440 /tmp/gameserver_wi; echo '$pw' | sudo -S mv /tmp/gameserver_wi /etc/sudoers.d/gameserver_wi\" 2>&1");
  if($rc != 0){
    echo "<div class='meldung_error'>SuDo zum Herunterfahren des Servers konnte nicht eingerichtet werden - der Server kann nicht &uuml;ber das Webinterface heruntergefahren oder rebootet werden.<br>";
    foreach($retarr as $line) echo $line."<br>";
    echo "</div><br>";
  }

  echo "<div class='meldung_ok'>Zugang zum Server erfolgreich eingerichtet.</div><br>";
  return true;
}

// Funktion zum neustarten des Servers
function reboot_server($server){
  global $ssh_string;
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"sudo shutdown -r now\"");
  echo "<div class='meldung_ok'>Reboot an den Server <b>".$server["name"]."</b> gesendet.</div><br>";
}

// Funktion zum herunterfahren des Servers
function shutdown_server($server){
  global $ssh_string;
  exec($ssh_string." ".$server["user"]."@".$server["ip"]." \"sudo shutdown -h now\"");
  echo "<div class='meldung_ok'>Shutdown an den Server <b>".$server["name"]."</b> gesendet.</div><br>";
}
?>
