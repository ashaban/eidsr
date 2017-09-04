# eidsr
Install Mongo DB
sudo pecl install mongodb
Opn
sudo gedit /etc/php5/apache2/php.ini
Add extension=mongodb.so
sudo php5enmod mongodb
sudo apt-get install curl php5-cli git
<source lang="bash">
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer<br>
</source>
composer require mongodb/mongodb
