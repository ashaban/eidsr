# eidsr
Install Mongo DB via pecl<br>
```
sudo pecl install mongodb
```
We'll also need to create the ini file to load mongodb into PHP
```
sudo gedit /etc/php5/mods-available/mongodb.ini
```
It should look like this:
```
extension=mongodb.so
```
We'll also need to enable this for Apache and CLI by creating 2 symlinks for the uuid file:
```
sudo ln -s /etc/php5/mods-available/mongodb.ini /etc/php5/apache2/conf.d/20-mongodb.ini
sudo ln -s /etc/php5/mods-available/mongodb.ini /etc/php5/cli/conf.d/20-mongodb.ini
```
Restart Apache
```
sudo service apache2 restart
```
We will now need to install php mongodb library using below commands
```
sudo apt-get install curl php5-cli git
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer<br>
composer require mongodb/mongodb
```
