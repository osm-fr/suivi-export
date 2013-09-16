suivi-export
============

Suivi/comparaison et exportation de limites administratives et cours d'eau depuis osm

Installation
============

Pensez à remplir le config.php à la racine, en vous aidant du config-sample.php

Note: hélas, la plupart des chemins utilisés pour crééer les fichiers est en dur un peu partout dans le code, et entre shell et php, ben, c'est pas facile de 
tout fusionner, mais ça serait bien de tout mettre dans le config.php (en factorisant)
Mais en attendant, à vous de fouiller les scripts et mettre à jour les chemins qui pointent vers les fichiers contenant les résultats


Modification du code
====================
Ce dossier est géré par sur https://github.com/osm-fr/suivi-export.git, une modification direct sur le serveur autre que dans config.php sera écrasée lors de la
prochaine modification, attention donc à contacter sly (sylvain@letuffe.org) avant de tenter des mises à jour "live"