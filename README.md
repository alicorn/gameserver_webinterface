Gameserver Webinterface
=======================

Features
--------
Das Gameserver Webinterface ist daf�r gedacht, auf einer LAN-Party bestimmte Gameserver (CS, COD, ..) auf Linux-Servern zu starten.
Vorteil daran ist, man kann das Webinterface f�r etliche Server nutzen und muss nicht f�r jeden Server einzeln auf die Konsole gehen.
Au�erdem kann die Webseite z.B. an den Turnier-Support �bergeben werden, damit diese ohne Linux- und Server-Kenntnisse mit einem Knopfdruck Gameserver bereitstellen k�nnen.
Deswegen auch die unterschiedlichen Rechte f�r die Benutzer:
 - **User** - darf Server anlegen, killen und sich die �bersicht angucken
 - **Admin** - darf zus�tzlich Server und Games einrichten - und darf unter "home" die Liste von gestorbenen Servern aufr�umen - und ihm werden weitere Informationen angezeigt (z.B. beim Starten der Befehl, der ausgef�hrt wird) - und er darf die Server rebooten und herunterfahren
 - **Superadmin** - darf zus�tzlich User anlegen und editieren
Dabei richtet sich der User an den Typischen Turnier-Support, der Gameserver bereitstellt, aber nichts mit den eigentlichen Servern zu tun hat. Dank des Score-Systems braucht der User kein Hintergrundwissen - Wenn er ein Gameserver starten will, werden ihm nur Server zur Auswahl gegeben, auf denen das Game auch l�uft, auf denen noch genug Ressourcen frei sind und die erreichbar sind.
Die Admins sind dann diejenigen, die die Server einrichten und administrieren. Der Cleanup z.B. darf nur von diesen ausgef�hrt werden, damit sie die M�glichkeit haben, nach Ursachen zu forschen.
�brigens: Beim Anlegen eines Users kann kein Passwort gesetzt werden - es wird ein default-Passwort gesetzt, was beim Anmelden vom User ge�ndert werden muss.

### Featureliste
 - Einfaches Starten und Stoppen von Gameservern per Webinterface
 - Stoppen mehrerer Gameserver gleichzeitig
 - Unterschiedliche Berechtigungen
 - Benutzerdefenierte Variablen in den Start-Befehlen f�r die Gameserver
 - Default-Werte f�r die benutzerdefenierten Variablen
 - Zuweisung der Games zu den Servern -> Games k�nnen nur auf den passenden Servern gestartet werden
 - Score-System, damit nicht zu viele Gameserver auf einem Server gestartet werden k�nnen
 - �bersicht mit laufenden Servern, Games und Scores, sowie farbliche Anzeige freier und ausgefallener Server
 - Abgeschmierte Gameserver werden farblich markiert
 - Nicht erreichbare Server werden farblich markiert und stehen nicht zum Starten von Games zur Verf�gung
 - Alle Gameserver werden mit dem Programm "screen" gestartet
 - Passwortlose SSH-Verbindung vom Webinterface per SSH-Key auf die Server
 - Herunterfahren und rebooten einzelner oder aller Server (Linux shutdown)
 - Syncronisieren einer Gameserver-Installation auf beliebige andere Server (Nur ein Server muss aktualisiert werden - danach syncen)
 - Anbindung an das dotlan-Turniersystem: Starten von Gameservern, wenn beide Teams bereit sind, stoppen wenn das Ergebnis eingetragen ist


Installation
------------
1. Pakete nachinstallieren
``apt-get install apache2 libapache2-mod-php5 php5-mysql mysql-server mysql-client sshpass screen``
1. Daten in das htdocs Verzeichnis entpacken
2. SSH-Keys erzeugen (Passphrase leer lassen):  
``ssh-keygen -f /etc/apache2/ssh_key_gswi``  
``chown www-data:www-data /etc/apache2/ssh_key_gswi*``
5. In der config.inc.php die MySQL-Daten anpassen (alles andere kann bleiben)
6. DB-Struktur einspielen: DB.sql  
``mysql -u mysql_user -p mysql_db < DB.sql``
7. Seite aufrufen  
> User: superadmin  
> PW: default

Anbindung an dotlan Turniersystem
---------------------------------
1. Auf dem dotlan-Server in der /etc/mysql/my.cnf einstellen, dass der Server auch auf externe IPs lauscht (bind-address)
2. Auf dem dotlan-Server einen MySQL-User anlegen:
``GRANT USAGE ON *.* TO 'gameserver_wi'@'%' IDENTIFIED BY PASSWORD '<HIER EIN SICHERES PW ...>';  
GRANT SELECT ON `dotlan`.`t_teilnehmer` TO 'gameserver'@'%';  
GRANT SELECT ON `dotlan`.`t_turnier` TO 'gameserver'@'%';  
GRANT SELECT (nick, id) ON `dotlan`.`user` TO 'gameserver'@'%';  
GRANT SELECT ON `dotlan`.`events` TO 'gameserver'@'%';  
GRANT SELECT ON `dotlan`.`t_teilnehmer_part` TO 'gameserver'@'%';  
GRANT SELECT ON `dotlan`.`t_contest` TO 'gameserver'@'%';``  
3. Auf dem Gameserver Webinterface Server in der config.inc.php die MySQL-Daten von dem dotlan-Server eintragen  
WICHTIG: Nutzt den oben eingerichteten User mit den wenigen Rechten und mit gutem Passwort!!
4. Auf dem Gameserver Webinterface Server einen Cronjob in die /etc/crontab eintragen:
``*/1 *	* * *	www-data	/usr/bin/php /var/www/cronjob_turniere.php > /dev/null 2>&1``

Einrichtung 
-----------
### Server einrichten
 - Hier gibt man Name, IP, User und Score an
 - Wenn schon Games vorhanden sind, kann man dem Server games zuweisen - nur diese Games k�nnen dann auch auf dem Server gestartet werden
 - Nach dem Hinzufuegen des Servers, in der Tabelle auf "Zugriff einrichten" klicken - danach kann das Webinterface auf den Server zugreifen
   
### Games einrichten
 - Einzugeben ist:
   - Icon (Auflistung aller Dateien im Ordner "images/")
   - Name (Kurzname, ohne sonderzeichen etc - wird f�r den Screen-Namen benutzt) - z.B. "cs16" oder "css" oder "cod4" oder ...
   - CMD (Befehl zum Starten des Gameservers - hier k�nnen Variablen im Format ##varname## benutzt werden)
     - ##port## gibt den Server-Port an - der wird automatisch vergeben beim Starten
     - Ansonsten kann man eigene Variablen vergeben, z.B. ##Servername## f�r den Namen und ##MaxPlayers## f�r die Anzahl der Spieler
     - Die eigenen Variablen werden beim Starten alle abgefragt
   - Defaults (Default-Werte f�r die eigenen(!) Variablen getrennt durch Semikolon ";")
     - Wenn man z.B. ##Servername## und ##MaxPlayers## defeniert hat, kann man als Defaults folgendes angeben: Servername xyz cs Server;11
     - Damit w�rden die Felder beim Starten automatisch mit "Servername xyz cs Server" und "11" gef�llt werden
     - Wichtig: Die Defaults m�ssen in der Reihenfolge angegeben werden, in der die variablen im CMD auftauchen
     - F�r ##port## kann man keine Defaults angeben
   - Startport (Port, ab dem der n�chste freie Port f�r den Server verwendet wird)
   - Score (Score-Wert, die dieses Game kostet)
   - Server (Liste der Server, auf denen dieses Game laufen kann)
 - Ein Game kann nur auf den Servern gestartet werden, auf denen das Game zugewiesen ist

### Turniere einrichten
 - Hier wird die Verkn�pfung zwischen Gameserver und dotlan-Turnier eingestellt
 - Man kann �ber das Textfeld bestimmen, welche Variable mit welchem Wert ersetzt werden soll
 - Die Variablen ##team1## und ##team2## werden automatisch durch den Wert aus dotlan ersetzt
 - Der Cronjob l�uft jede Minute, d.h. es kann bis zu einer Minute dauern, bis ein Server gestartet/gestoppt wird
 - Die Turnierserver werden auf irgendeinem Server gestartet, wo das Game laufen kann (siehe Games einrichten) und wo noch genug Score frei ist

### Die Sache mit den Scores
 - Die Server bekommen bestimmte Scores (z.B. 100)
 - Die Games bekommen bestimmte Scores (z.B. cs16 = 10, css = 20)  

 Dann hat der Server 100 Score-Punkte zu verf�gung - die Games kosten 20 bzw 10 Score-Punkte
 In diesem Beispiel k�nnen also maximal f�nf css Gameserver auf dem Server gestartet werden, da 5 x 20 = 100
 Wenn nur vier css Gameserver gestartet werden (4 x 20 = 80) k�nnen zus�tzlich noch zwei cs16 auf diesem Server gestartet werden (2 x 10 = 20) - Zusammen macht das dann wieder 100
 Wenn auf einem Server nicht mehr genug Score-Punkte f�r ein Gameserver verf�gbar sind, steht dieser nicht zur Verf�gung.
 Damit kann man die Last der Server automatisch beschr�nken und kontrollieren.
