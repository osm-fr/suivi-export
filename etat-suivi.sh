#!/bin/bash

d=$(dirname $0)

. $d/config.sh

mkdir $chemin_suivi 2>/dev/null
mkdir $chemin_export 2>/dev/null

#Les communes
mkdir $chemin_export_administratif 2>/dev/null
mkdir $chemin_suivi_commune 2>/dev/null
mkdir $chemin_export_commune 2>/dev/null

cp $d/HEADER.html $chemin_export_commune
cp $d/HEADER.html $chemin_suivi_commune
cp $d/HEADER.html $chemin_export_administratif

#ouais clair, factorisation de factorisation, ça fini en usine à gaz...
param_base_monde="$pg_serveur_monde $pg_serveur_port_monde $pg_role_monde $pg_password_monde $pg_base_monde $table_qui_contient_la_france $table_qui_contient_la_france"

date_base_monde="`wget $fichier_state_base_monde -q -O - | grep timestamp | sed s/timestamp=// | sed s/[TZ]/" "/g | sed s/\\\\\\\\//g`"
php $d/etat-communes/commune_stats.php $chemin_suivi_commune $chemin_export_commune $param_base_monde "$date_base_monde" > $chemin_suivi_commune/errors.log 2>&1


#calcul des km par cours d'eau et comparaison au SANDRE
dossier_cours_eau=$chemin_suivi/longeur-cours-eau-france/
mkdir $dossier_cours_eau 2>/dev/null

param_base_france="$pg_serveur_france $pg_serveur_port_france $pg_role_france $pg_password_france $pg_base_france"
date_base_france="`wget $fichier_state_base_france -q -O - | grep timestamp | sed s/timestamp=// | sed s/[TZ]/" "/g | sed s/\\\\\\\\//g`"

# Export des régions et départements
$d/exports-administratif/export-limites-administratives.sh 6 96 departements $chemin_export_administratif $param_base_france
$d/exports-administratif/export-limites-administratives.sh 4 22 regions $chemin_export_administratif $param_base_france

php $d/longeur-cours-eau-france/suivi-cours-eau.php $pg_serveur_france $pg_serveur_port_france $pg_role_france $pg_password_france $pg_base_france "$date_base_france" > $dossier_cours_eau/comparaison-sandre.html
cp $d/longeur-cours-eau-france/sorttable.js $dossier_cours_eau
cp $d/HEADER.html $dossier_cours_eau


# Export des cours d'eau
mkdir $chemin_export_cours_eau 2>/dev/null
$d/exports-cours-eau-longs/exportation-shape.sh $chemin_export_cours_eau $param_base_france


