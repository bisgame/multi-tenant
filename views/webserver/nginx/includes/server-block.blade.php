
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

    @if(isset($ssl))
    listen {{ $config->port->https or 443 }} ssl http2;
    include snippets/ssl-params.conf;
    ssl_certificate {{ $ssl->pathPem }};
    ssl_certificate_key {{ $ssl->pathKey }};
    @endif

    # server hostnames
    @if(isset($hostname))
        server_name {{ $hostname->hostname }};
    @else
        server_name {{ $hostnames->implode('hostname', ' ') }};
    @endif

    # allow cross origin access
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Request-Method GET;

    # redirect any www domain to non-www
    if ( $host ~* ^www\.(.*) ) {
        set             $host_nowww     $1;
        rewrite         ^(.*)$          $scheme://$host_nowww$1 permanent;
    }

    # root path of website; serve files from here
    root                        {{ public_path() }};
    index                       index.php;


    # log handling
    access_log          {{ $log_path }}.access.log;
    error_log           {{ $log_path }}.error.log notice;

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

    location / {
        index           index.php;
        try_files       $uri $uri/ $uri/index.php?$args /index.php?$args;
    }
    # pass the PHP scripts to FastCGI server from upstream phpfcgi
    location ~ \.php(/|$) {
        fastcgi_pass    unix:/run/php/php7.1-fpm.sock;
        include         fastcgi_params;

        fastcgi_split_path_info ^(.+\.php)(/.*)$;

        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}