#!/bin/bash
dossier_temporaire="/dev/shm/tmp"
mkdir $dossier_temporaire 2>/dev/null

if [ a$8 = "a" ] ;  then
echo "Utilisation : ./export-limites-administratives.sh <admin_level> <nombre_attendu> <nom du fichier> <dossier où exporter> <host base pg> <user pg> <password pg> <pg dbname>"
echo ""
echo "Où <admin_level> vaut 6 pour les départements, 4 pour les regions"
echo "Où <nombre_attendu> et le nombre de contours attendu pour déterminer si l'export est complet ou incomplet"
echo "Où <nom du fichier> et le nom souhaité pour le fichier d'archive et des shapefiles"
echo "Où <dossier où exporter> est le dossier dans lequel seront créér les shapefiles"
echo "Où <nom de la base locale> est le nom de la base postgresql local, au schéma osm2pgsql contenant la france"
echo "Exemple : ./export-limites-administratives.sh 6 96 departements"
echo "Exemple : ./export-limites-administratives.sh 4 22 regions"

exit
fi

# Bidouille car les regions on comme référence principale ref:INSEE, mais les départements ref
if  [ $1 == 4 ] ; then
  champ_contenant_ref="ref:INSEE"
else
  champ_contenant_ref="ref"
fi

pgsql2shp -h $5 -u $6 -P $7 -f $dossier_temporaire/$3-metropole $8 "select st_transform(admin.way,4326) as way,admin.name as nom,\"$champ_contenant_ref\" as numero from planet_osm_polygon as admin,france_polygon as f where admin.admin_level='$1' and f.osm_id=4  and admin.\"$champ_contenant_ref\" is not null and admin.simplified_way && f.simplified_way and st_within(st_pointonsurface(admin.way),f.simplified_way) and isvalid(admin.way)='t';" > $dossier_temporaire/resultat 2>&1
RES=`cat $dossier_temporaire/resultat | grep "\[$2"` 

if [ "a$RES" = "a" ] ; then
complet_ou_pas="incomplet"
else
complet_ou_pas="complet"
rm $4/$3-metropole-incomplet.tar.gz 2>/dev/null
fi

cd $dossier_temporaire
tar cvfz $4/$3-metropole-$complet_ou_pas.tar.gz $3-metropole.*
cd -
rm $dossier_temporaire/$3-metropole.*
