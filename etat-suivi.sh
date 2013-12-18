#!/bin/bash

d=$(dirname $0)

. $d/config.sh

mkdir "$chemin_suivi" 2>/dev/null
mkdir "$chemin_export" 2>/dev/null

#Les communes
mkdir "$chemin_export_administratif" 2>/dev/null
mkdir "$chemin_suivi_commune" 2>/dev/null
mkdir "$chemin_export_commune" 2>/dev/null

date_base_monde="`wget $fichier_state_base_monde -q -O - | grep timestamp | sed s/timestamp=// | sed s/[TZ]/" "/g | sed s/\\\\\\\\//g` GMT"
header_file="$dossier_temporaire/HEADER.html"
cat $d/HEADER.html | sed s/@@date@@/"$date_base_monde"/g > $header_file

cp "$header_file" "$chemin_suivi_commune"
cp "$header_file" "$chemin_export_administratif"

#ouais clair, factorisation de factorisation, ça fini en usine à gaz...
param_base_monde="$pg_serveur_monde $pg_serveur_port_monde $pg_role_monde $pg_password_monde $pg_base_monde $table_qui_contient_la_france $id_france_dans_cette_table"
#export et suivi des communes
php $d/etat-communes/commune_stats.php "$chemin_suivi_commune" "$chemin_export_commune" $param_base_monde "$date_base_monde" > $chemin_suivi_commune/errors.log 2>&1

# Export des régions et départements
$d/exports-administratif/export-limites-administratives.sh 6 101 departements "$chemin_export_administratif" $param_base_monde
$d/exports-administratif/export-limites-administratives.sh 4 27 regions "$chemin_export_administratif" $param_base_monde

#calcul des km par cours d'eau et comparaison au SANDRE
mkdir $chemin_suivi_cours_eau 2>/dev/null

php $d/longeur-cours-eau-france/suivi-cours-eau.php "$date_base_monde" $param_base_monde > "$chemin_suivi_cours_eau"/comparaison-sandre.html
cp $d/longeur-cours-eau-france/sorttable.js "$dossier_cours_eau"

# Export des cours d'eau
cp "$header_file" "$chemin_export_cours_eau"
mkdir "$chemin_export_cours_eau" 2>/dev/null
$d/exports-cours-eau-longs/exportation-shape.sh "$chemin_export_cours_eau" $param_base_monde


