suivi-export
============

Suivi/comparaison et exportation de limites administratives et cours d'eau depuis osm

Installation
============

Pensez à remplir le config.php à la racine, en vous aidant du config-sample.php

Note: hélas, la plupart des chemins utilisés pour crééer les fichiers est en dur un peu partout dans le code, et entre shell et php, ben, c'est pas facile de 
tout fusionner, mais ça serait bien de tout mettre dans le config.php (en factorisant)
Mais en attendant, à vous de fouiller les scripts et mettre à jour les chemins qui pointent vers les fichiers contenant les résultats

installation adapté aux serveurd de openstreetmap france 
========================================================
Voici, totalement en vrac, les commandes linux que j'ai du taper la dernière fois pour installer ces programmes dans :
/data/project/suivi-export
et fournir le résultat dans 
/data/work/suivi
et
/data/work/export
mkdir /data/project/suivi-export

useradd suivi-export -d /data/project/suivi-export -s /bin/bash
chown suivi-export.suivi-export /data/project/suivi-export
mkdir /data/work/suivi
mkdir /data/work/export
chown suivi-export.suivi-export /data/work/suivi
chown suivi-export.suivi-export /data/work/export
su - suivi-export
cd /data/project/
git clone https://github.com/osm-fr/suivi-export.git
exit
cp /data/project/suivi-export/config-apache/* /etc/apache2/sites-available/
a2ensite suivi
a2ensite export
a2enmod expires
service apache2 restart
apt-get install php5-pgsql php5-cli



Modification du code
====================
Ce dossier est géré par sur https://github.com/osm-fr/suivi-export.git, une modification direct sur le serveur autre que dans config.php sera écrasée lors de la
prochaine modification, attention donc à contacter sly (sylvain@letuffe.org) avant de tenter des mises à jour "live"