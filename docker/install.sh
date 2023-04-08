#!/bin/bash
# install script for http://wuhu.function.hu/

if [ "$EUID" -ne 0 ]
then 
  echo "[wuhu] ERROR: This script needs to install a bunch of things, so please run as root"
  exit
fi

echo "[wuhu] Installing packages..."

apt-get update

apt-get -y install \
  apache2 \
  php7.3 \
  php7.3-gd \
  php7.3-mysql \
  php7.3-curl \
  php7.3-mbstring \
  mariadb-server \
  libapache2-mod-php7.3 \
  mc \
  git \
  ssh \
  sudo

# -------------------------------------------------
# set up the files / WWW dir

rm -rf /var/www/*
chmod -R g+rw /var/www
chown -R www-data:www-data /var/www

echo "[wuhu] Fetching the latest version of Wuhu..."
git clone https://github.com/piluex/wuhu.git /var/www/

mkdir /var/www/entries_private
mkdir /var/www/entries_public
mkdir /var/www/screenshots

chmod -R g+rw /var/www/*
chown -R www-data:www-data /var/www/*

# -------------------------------------------------
# set up PHP

echo "[wuhu] Setting up PHP..."

for i in /etc/php/7.3/*/php.ini
do
  sed -i -e 's/^upload_max_filesize.*$/upload_max_filesize = 128M/' $i
  sed -i -e 's/^post_max_size.*$/post_max_size = 256M/' $i
  sed -i -e 's/^memory_limit.*$/memory_limit = 512M/' $i
  sed -i -e 's/^session.gc_maxlifetime.*$/session.gc_maxlifetime = 604800/' $i
  sed -i -e 's/^short_open_tag.*$/short_open_tag = On/' $i 
done

# -------------------------------------------------
# set up Apache

echo "[wuhu] Setting up Apache..."

rm /etc/apache2/sites-enabled/*

echo -e \
  "<VirtualHost *:80>\n" \
  "\tDocumentRoot /var/www/www_party\n" \
  "\t<Directory />\n" \
  "\t\tOptions FollowSymLinks\n" \
  "\t\tAllowOverride All\n" \
  "\t</Directory>\n" \
  "\tErrorLog \${APACHE_LOG_DIR}/party_error.log\n" \
  "\tCustomLog \${APACHE_LOG_DIR}/party_access.log combined\n" \
  "\t</VirtualHost>\n" \
  "\n" \
  "<VirtualHost *:80>\n" \
  "\tDocumentRoot /var/www/www_admin\n" \
  "\tServerName admin.lan\n" \
  "\t<Directory />\n" \
  "\t\tOptions FollowSymLinks\n" \
  "\t\tAllowOverride All\n" \
  "\t</Directory>\n" \
  "\tErrorLog \${APACHE_LOG_DIR}/admin_error.log\n" \
  "\tCustomLog \${APACHE_LOG_DIR}/admin_access.log combined\n" \
  "</VirtualHost>\n" \
  > /etc/apache2/sites-available/wuhu.conf

a2ensite wuhu

echo "[wuhu] Restarting Apache..."
service apache2 restart

# -------------------------------------------------
# TODO? set up nameserver / dhcp?

# -------------------------------------------------
# set up MySQL

service mysql restart

echo "[wuhu] Setting up MySQL (MariaDB)..."

echo -e "Enter a MySQL password for the Wuhu user: \c"
read -s WUHU_MYSQL_PASS

echo "Now connecting to MySQL..."
echo -e \
  "CREATE DATABASE wuhu;\n" \
  "GRANT ALL PRIVILEGES ON wuhu.* TO 'wuhu'@'%' IDENTIFIED BY '$WUHU_MYSQL_PASS';\n" \
  | mysql -u root -p 

# -------------------------------------------------
# We're done, wahey!

printf "\n\n\n*** CONGRATULATIONS, Wuhu is now ready to configure at http://admin.lan\n"
