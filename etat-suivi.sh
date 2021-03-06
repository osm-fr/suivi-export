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
cat $d/templates/HEADER-suivi.html | sed s/@@date@@/"$date_base_monde"/g > "$chemin_suivi_commune/HEADER.html"
cat $d/templates/HEADER-export-admin.html | sed s/@@date@@/"$date_base_monde"/g > "$chemin_export_administratif/HEADER.html"
cat $d/templates/HEADER-export-admin.html | sed s/@@date@@/"$date_base_monde"/g > "$chemin_export_commune/HEADER.html"

#ouais clair, factorisation de factorisation, ça fini en usine à gaz...
param_base_monde="$pg_serveur_monde $pg_serveur_port_monde $pg_role_monde $pg_password_monde $pg_base_monde"
contenu_dans="$table_qui_contient_polygone_englobant"

#export et suivi des communes
php $d/etat-communes/commune_stats.php "$chemin_suivi_commune" "$chemin_export_commune" $param_base_monde $table_qui_contient_polygone_englobant $id_france "$date_base_monde" > $chemin_suivi_commune/errors.log 2>&1

# Export des régions et départements
param_france="$param_base_monde $table_qui_contient_polygone_englobant $id_france"
$d/exports-administratif/export-limites-administratives.sh 6 france-departements "$chemin_export_administratif" $param_france
$d/exports-administratif/export-limites-administratives.sh 4 france-regions "$chemin_export_administratif" $param_france
$d/exports-administratif/export-limites-administratives.sh 7 france-cantons "$chemin_export_administratif" $param_france

param_france_metropolitaine="$param_base_monde $table_qui_contient_polygone_englobant $id_france_metropolitaine"
$d/exports-administratif/export-limites-administratives.sh 6 france-metropolitaine-departements "$chemin_export_administratif" $param_france_metropolitaine
$d/exports-administratif/export-limites-administratives.sh 4 france-metropolitaine-regions "$chemin_export_administratif" $param_france_metropolitaine
$d/exports-administratif/export-limites-administratives.sh 7 france-metropolitaine-cantons "$chemin_export_administratif" $param_france_metropolitaine

#calcul des km par cours d'eau et comparaison au SANDRE
mkdir $chemin_suivi_cours_eau 2>/dev/null
cp "$header_file" "$chemin_suivi_cours_eau"
php $d/longeur-cours-eau-france/suivi-cours-eau.php "$date_base_monde" $param_base_monde $table_qui_contient_polygone_englobant $id_france > "$chemin_suivi_cours_eau"/comparaison-sandre.html
cp $d/longeur-cours-eau-france/sorttable.js "$dossier_cours_eau"

# Export des cours d'eau
cat $d/templates/HEADER-export-cours-eau.html | sed s/@@date@@/"$date_base_monde"/g > "$chemin_export_cours_eau/HEADER.html"    
mkdir "$chemin_export_cours_eau" 2>/dev/null
$d/exports-cours-eau-longs/exportation-shape.sh "$chemin_export_cours_eau" $param_base_monde
