Commandes à exécuter pour appliquer la mise à jour :

sudo usermod -aG www-data marco
sudo chgrp -R www-data /var/www/html
sudo chmod -R g+rwX /var/www/html
sudo find /var/www/html -type d -exec chmod g+s {} \;

Réaliser la compile des fichiers
Installer les mises à jour et compiler

sudo composer u
sudo npm i
sudo npm run build

Vider le dossier temporaire

sudo rm -R /var/www/html/var/log/*
sudo rm -R /var/www/html/var/cache/prod/*

Remettre les droits corrects partout

sudo chown -R www-data:www-data /var/www/html

sudo find /var/www/html/var -type d -exec chmod 2775 {} \;
sudo find /var/www/html/var -type f -exec chmod 664 {} \;
sudo chmod +x /var/www/html/bin/console
sudo chmod +x /var/www/html/bin/phpunit
sudo chmod +x /var/www/html/bin/migrate-old-database.php