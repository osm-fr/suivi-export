#!/bin/bash

d=$(dirname $0)

. $d/config.sh

mkdir $chemin_suivi 2>/dev/null
mkdir $chemin_export 2>/dev/null

#Les communes
mkdir $chemin_export_administratif 2>/dev/null
mkdir $chemin_suivi_commune 2>/dev/null
mkdir $chemin_export_commune 2>/dev/null

php $d/etat-communes/commune_stats.php $chemin_suivi_commune $chemin_export_commune $pg_serveur_monde $pg_role_monde $pg_password_monde $pg_base_monde $fichier_state_base_monde > $chemin_suivi_commune/errors.log 2>&1

#calcul des km par cours d'eau et comparaison au SANDRE
dossier_cours_eau=$chemin_suivi/longeur-cours-eau-france/
mkdir $dossier_cours_eau 2>/dev/null
php $d/longeur-cours-eau-france/suivi-cours-eau.php $pg_base_france $fichier_state_base_france > $dossier_cours_eau/comparaison-sandre.html
cp $d/longeur-cours-eau-france/sorttable.js $dossier_cours_eau

# Export des régions et départements
$d/exports-administratif/export-limites-administratives.sh 6 96 departements $chemin_export_administratif $pg_base_france
$d/exports-administratif/export-limites-administratives.sh 4 22 regions $chemin_export_administratif $pg_base_france

# Export des cours d'eau
mkdir $chemin_export_cours_eau 2>/dev/null
$d/exports-cours-eau-longs/exportation-shape.sh $chemin_export_cours_eau $pg_base_france


