@foreach ($hostnames as $hostname)

server {

    listen 80;
    @if(isset($hostname))
    server_name {{ $hostname->hostname }};
    @else
    server_name {{ $hostnames->implode('hostname', ' ') }};
    @endif
    return 302 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name {{ $hostname->hostname }};
    root {{ public_path() }};

    index index.html index.htm index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # redirect any www domain to non-www
    if ( $host ~* ^www\.(.*) ) {
        set             $host_nowww     $1;
        rewrite         ^(.*)$          $scheme://$host_nowww$1 permanent;
    }

    ssl_certificate ssl/bisgame.crt;
    ssl_certificate_key ssl/bisgame.key;

    ssl_protocols TLSv1 TLSv1.1 TLSv1.2;
    ssl_prefer_server_ciphers on;
    ssl_ciphers "EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH";
    ssl_ecdh_curve secp384r1;
    ssl_session_cache shared:SSL:10m;
    ssl_session_tickets off;
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;
    # Disable preloading HSTS for now.  You can use the commented out header line that includes
    # the "preload" directive if you understand the implications.
    #add_header Strict-Transport-Security "max-age=63072000; includeSubdomains; preload";
    add_header Strict-Transport-Security "max-age=63072000; includeSubdomains";
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;

    ssl_dhparam ssl/dhparam.pem;



    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # allow cross origin access
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Request-Method GET;

    access_log {{ $log_path }}.access.log;
    error_log  {{ $log_path }}.error.log notice;

    @if($website->directory->media())
    # attempt to passthrough to image service
    location ~* ^/media/(.+)$ {
        alias       {{ $website->directory->media() }}$1;
    }
    @endif

    @if($website->directory->cache())
    # map public cache folder to private domain folder
    location /cache/ {
        alias       {{ $website->directory->cache() }};
    }
    @endif

    sendfile off;

    client_max_body_size 100m;

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9100;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

    }

    location ~ /\.ht {
        deny all;
    }

    location /assets {
        alias {{ base_path('storage\app\assets') }};
        autoindex on;
    }
}
@endforeach
