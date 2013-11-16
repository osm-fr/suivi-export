suivi-export
============

Suivi/comparaison et exportation de limites administratives et cours d'eau depuis osm

Installation
============

Copier le config-sample.sh vers config.sh à la racine, et adaptez

cron
====
cron à lancer chaque nuit (ou en fait quand on veut, mais attention, ça prend bien 30 minutes au total)

``
0 19 * * * /data/project/suivi-export/etat-suivi.sh 
``

dans /data/other_polygons.sql se trouve le polygone de france metro+dom/tom pour des calculs uniquement sur la france
à charger dans le serveur SQL intérogé

installation adaptée aux serveurs de openstreetmap france 
=========================================================
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

/* 
editer et configurer le compte d'accès postresql "suivi-export" et plusieurs chemins en éditant config.php 
(copie depuis config-sampe.php pour avoir une base)
*/

exit

cp /data/project/suivi-export/config-apache/* /etc/apache2/sites-available/

a2ensite suivi

a2ensite export

a2enmod expires

service apache2 restart

apt-get install php5-pgsql php5-cli


créer le role postgresql "suivi-export" et lui donner les droits de select sur les tables locales de osm2pgsql et les tables geometry_columns et spatial_ref_sys
importer le fichier data/sandre.sql.bz2 dans postgresql, cette unique table doit être accessible en lecture par l'utilisateur "suivi-export" 


Modification du code
====================
Ce dossier est géré par sur https://github.com/osm-fr/suivi-export.git, une modification direct sur le serveur autre que dans config.sh sera écrasée lors de la
prochaine modification, attention donc à contacter sly (sylvain@letuffe.org) avant de tenter des mises à jour "live"
