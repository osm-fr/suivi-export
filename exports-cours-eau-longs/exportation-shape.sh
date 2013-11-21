#!/bin/bash
# script d'exportation des cours d'eau français
# - exporter le tout en shapefile

chemin_export="$1/cours-eau"
mkdir $chemin_export 2>/dev/null
dossier_temporaire="/dev/shm/tmp"
mkdir $dossier_temporaire 2>/dev/null

pgsql2shp -h $2 -p$3 -u $4 -P $5 -f $dossier_temporaire/cours_eau_france $6 "select \"ref:sandre\",name,st_transform(way,4326) as way from planet_osm_line where \"ref:sandre\" is not null and waterway='river'"
cd $dossier_temporaire
tar cvfz $chemin_export/cours-eau-france.shp.tar.gz cours_eau_france*
cd -
rm -f  $dossier_temporaire/cours_eau_france.*

  
