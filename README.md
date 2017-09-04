# eidsr
Install Mongo DB<br>
sudo pecl install mongodb<br>
Opn<br>
sudo gedit /etc/php5/mods-available/mongodb.ini<br>
Add extension=mongodb.so<br>
sudo php5enmod mongodb <br>
sudo service apache2 restart <br>
sudo apt-get install curl php5-cli git <br>
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer<br>
composer require mongodb/mongodb
