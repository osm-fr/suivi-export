#!/bin/bash
dossier_temporaire="/dev/shm/tmp"
mkdir $dossier_temporaire 2>/dev/null

if [ a${10} = "a" ] ;  then
echo "Utilisation : ./export-limites-administratives.sh <admin_level> <nombre_attendu> <nom du fichier> <dossier où exporter> <host base pg> <user pg> <password pg> <pg dbname>"
echo ""
echo "Où <admin_level> vaut 6 pour les départements, 4 pour les regions"
echo "Où <nombre_attendu> et le nombre de contours attendu pour déterminer si l'export est complet ou incomplet"
echo "Où <nom du fichier> et le nom souhaité pour le fichier d'archive et des shapefiles"
echo "Où <dossier où exporter> est le dossier dans lequel seront créér les shapefiles"
echo "Où <nom de la base locale> est le nom de la base postgresql local, au schéma osm2pgsql contenant la france"
echo "Exemple : ./export-limites-administratives.sh 6 101 departements"
echo "Exemple : ./export-limites-administratives.sh 4 27 regions"

exit
fi

# Bidouille car les regions on comme référence principale ref:INSEE, mais les départements ref
if  [ $1 == 4 ] ; then
  champ_contenant_ref="tags->'ref:INSEE'"
else
  champ_contenant_ref="ref"
fi

pgsql2shp -h $5 -p $6 -u $7 -P $8 -f $dossier_temporaire/$3 $9 "select st_transform(admin.way,4326) as way,admin.name as nom,$champ_contenant_ref as numero from planet_osm_polygon as admin,${10} as f where f.id=${11} and st_within(ST_PointOnSurface(admin.way),f.simplified_way) and admin_level='$1' and boundary='administrative' and st_isvalid(admin.way)='t';" > $dossier_temporaire/resultat 2>&1
RES=`cat $dossier_temporaire/resultat | grep "\[$2"` 

if [ "a$RES" = "a" ] ; then
complet_ou_pas="incomplet"
else
complet_ou_pas="complet"
rm $4/$3-incomplet.tar.gz 2>/dev/null
fi

cd $dossier_temporaire
tar cvfz $4/$3-$complet_ou_pas.tar.gz $3.*
cd -
rm $dossier_temporaire/$3.*
