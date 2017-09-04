# eidsr
Install Mongo DB<br>
sudo pecl install mongodb<br>
Opn<br>
sudo gedit /etc/php5/apache2/php.ini<br>
Add extension=mongodb.so<br>
sudo php5enmod mongodb <br>
sudo apt-get install curl php5-cli git
<source lang="bash">
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer<br>
</source>
composer require mongodb/mongodb
