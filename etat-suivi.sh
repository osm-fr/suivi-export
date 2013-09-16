#!/bin/bash

#Les communes
cd etat-communes ; php commune_stats.php >commune_stat.log 2>&1
cd ..

#calcul des km par cours d'eau et comparaison au SANDRE
dossier_cours_eau=/data/work/suivi/longeur-cours-eau-france/
mkdir $dossier_cours_eau 2>/dev/null
cd longeur-cours-eau-france ; php  suivi-cours-eau.php > $dossier_cours_eau/comparaison-sandre.html
cd ..

# Export des régions et départements
cd exports-administratif 
./export-limites-administratives.sh 6 96 departements
./export-limites-administratives.sh 4 22 regions
cd ..

# Export des cours d'eau
cd exports-cours-eau-longs ; ./exportation-shape.sh
cd ..


