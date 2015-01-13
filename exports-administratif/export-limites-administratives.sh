#!/bin/bash
dossier_temporaire="/dev/shm/tmp"
mkdir $dossier_temporaire 2>/dev/null

if [ -z ${10} ] ;  then
echo "Utilisation : ./export-limites-administratives.sh <admin_level> <nom du fichier> <dossier où exporter> <host base pg> <pg port tcp> <user pg> <password pg> <pg dbname> <table_qui_contient_la_france> <id_france_dans_cette_table>"
echo ""
echo "Où <admin_level> vaut 6 pour les départements, 4 pour les regions"
echo "Où <nom du fichier> et le nom souhaité pour le fichier d'archive et des shapefiles"
echo "Où <dossier où exporter> est le dossier dans lequel seront créér les shapefiles"
echo "Où <nom de la base locale> est le nom de la base postgresql local, au schéma osm2pgsql contenant la france"
echo "Exemple : ./export-limites-administratives.sh 6 departements"
echo "Exemple : ./export-limites-administratives.sh 4 regions"

exit
fi

# Bidouille car les regions on comme référence principale ref:INSEE, mais les départements ref
if  [ $1 == 4 ] ; then
  champ_contenant_ref="tags->'ref:INSEE'"
else
  champ_contenant_ref="ref"
fi

pgsql2shp -h $4 -p $5 -u $6 -P $7 -f $dossier_temporaire/$2 $8 "select st_transform(admin.way,4326) as way,admin.name as nom,$champ_contenant_ref as numero from planet_osm_polygon as admin,${9} as f where f.id=${10} and st_within(ST_PointOnSurface(admin.way),f.simplified_way) and admin_level='$1' and boundary='administrative' and st_isvalid(admin.way)='t';"  > $dossier_temporaire/resultat 2>&1

cd $dossier_temporaire
tar cvfz $3/$2.tar.gz $2.*
cd -
rm $dossier_temporaire/$2.*
