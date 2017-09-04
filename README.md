# eidsr Install Instructions
You will need to install openHIM core and console. Install instructions are available on below link.
```
sudo add-apt-repository ppa:openhie/release
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv EA312927
sudo echo 'deb http://repo.mongodb.org/apt/ubuntu trusty/mongodb-org/3.2 multiverse' | sudo tee /etc/apt/sources.list.d/mongodb-org-3.2.list
sudo apt-get update
sudo apt-get install openhim-core-js openhim-console
```
Clone github repo
```
git clone https://github.com/ashaban/eidsr.git
```
Change variables in config.php and openHimConfig.php to meet your environments settings <br>

Install Mongo DB via pecl
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
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
composer require mongodb/mongodb
```
