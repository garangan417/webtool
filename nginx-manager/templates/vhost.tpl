# ============================================================
# Template Vhost - Nginx Manager
# ============================================================
#
# File ini adalah dokumentasi template. Logika generate
# sebenarnya ada di lib/nginx.php (fungsi nginx_build_vhost).
#
# Placeholder yang tersedia:
#
#   {{SERVER_NAME}}   : domain, misal "toko.example.com"
#   {{ROOT}}          : document root, misal "/var/www/html"
#   {{PHP_SOCKET}}    : socket PHP-FPM, misal "unix:/run/php/php8.3-fpm.sock"
#   {{CREATED_AT}}    : timestamp pembuatan
#   {{SSL_CERT}}      : path sertifikat SSL (kalau ada)
#   {{SSL_KEY}}       : path private key SSL (kalau ada)
#
# Blok HTTP (selalu ada):
#
#   server {
#       listen 80;
#       server_name {{SERVER_NAME}};
#       root {{ROOT}};
#       index index.php index.html;
#
#       location / {
#           try_files $uri $uri/ /index.php?$query_string;
#       }
#
#       location ~ \.php$ {
#           include snippets/fastcgi-php.conf;
#           fastcgi_pass {{PHP_SOCKET}};
#       }
#   }
#
# Blok HTTPS (kalau SSL aktif):
#
#   server {
#       listen 443 ssl;
#       server_name {{SERVER_NAME}};
#       root {{ROOT}};
#       index index.php index.html;
#
#       ssl_certificate     {{SSL_CERT}};
#       ssl_certificate_key {{SSL_KEY}};
#
#       location / { ... }
#       location ~ \.php$ { ... }
#   }
#
# ============================================================