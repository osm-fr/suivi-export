#!/bin/bash

. ./config.sh

mkdir $chemin_suivi 2>/dev/null
mkdir $chemin_export 2>/dev/null

#Les communes
mkdir $chemin_export_administratif 2>/dev/null
mkdir $chemin_suivi_commune 2>/dev/null
mkdir $chemin_export_commune 2>/dev/null

cd etat-communes ; php commune_stats.php $chemin_suivi_commune $chemin_export_commune $pg_serveur_monde $pg_role_monde $pg_password_monde $pg_base_monde > $chemin_suivi_commune/errors.log 2>&1
cd ..

#calcul des km par cours d'eau et comparaison au SANDRE
dossier_cours_eau=$chemin_suivi/longeur-cours-eau-france/
mkdir $dossier_cours_eau 2>/dev/null
cd longeur-cours-eau-france ; php suivi-cours-eau.php $pg_base_france $fichier_state_base_france > $dossier_cours_eau/comparaison-sandre.html
cp sorttable.js $dossier_cours_eau
cd ..

# Export des régions et départements
cd exports-administratif 
./export-limites-administratives.sh 6 96 departements $chemin_export_administratif $pg_base_france
./export-limites-administratives.sh 4 22 regions $chemin_export_administratif $pg_base_france
cd ..

# Export des cours d'eau
cd exports-cours-eau-longs ; ./exportation-shape.sh $chemin_export_administratif $pg_base_france
cd ..


